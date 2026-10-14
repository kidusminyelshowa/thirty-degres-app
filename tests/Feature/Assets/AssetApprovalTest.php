<?php

namespace Tests\Feature\Assets;

use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\Membership;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * Drafts, approval and archiving, with "nobody approves their own work"
 * (docs/roles-and-permissions.md, segregation of duties 1).
 */
class AssetApprovalTest extends TestCase
{
    use CreatesAssets, CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    private Brand $brand;

    private Membership $manager;

    private Membership $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $this->workspace = $this->createWorkspace('Ethio Coffee');
        $this->brand = $this->createBrand($this->workspace);
        $this->manager = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $this->manager, BrandRoleName::Manager);
        $this->editor = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $this->editor, BrandRoleName::Editor);
    }

    public function test_a_brand_manager_approves_someone_elses_upload()
    {
        $asset = $this->uploadAsset($this->brand, uploader: $this->editor->user);

        $this->actingAs($this->manager->user)
            ->post(route('workspaces.assets.approve', [$this->workspace, $asset]))
            ->assertRedirect(route('workspaces.assets.show', [$this->workspace, $asset]));

        $this->inWorkspace($this->workspace, function () use ($asset) {
            $fresh = Asset::findOrFail($asset->id);
            $this->assertSame(AssetStatus::Approved, $fresh->status);
            $this->assertSame($this->manager->user_id, $fresh->approved_by);
            $this->assertNotNull($fresh->approved_at);
            $this->assertFalse(ActivityLog::where('action', 'asset.approved')->sole()->details['self_approved']);
        });
    }

    public function test_nobody_approves_their_own_upload()
    {
        $asset = $this->uploadAsset($this->brand, uploader: $this->manager->user);

        $this->actingAs($this->manager->user)
            ->get(route('workspaces.assets.show', [$this->workspace, $asset]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('assets/show')
                ->where('can.approve', false)
                ->where('can.approveReason', 'Someone else must approve your upload.'));

        $this->actingAs($this->manager->user)
            ->post(route('workspaces.assets.approve', [$this->workspace, $asset]))
            ->assertForbidden();

        $this->inWorkspace($this->workspace, fn () => $this->assertSame(AssetStatus::Draft, $asset->fresh()?->status));
    }

    public function test_the_database_refuses_self_approval_whatever_the_app_does()
    {
        $asset = $this->uploadAsset($this->brand, uploader: $this->manager->user);

        $this->inWorkspace($this->workspace, function () use ($asset) {
            try {
                DB::transaction(fn () => DB::table('assets')->where('id', $asset->id)->update([
                    'status' => 'approved',
                    'approved_by' => $this->manager->user_id,
                    'approved_at' => now(),
                ]));
                $this->fail('The database allowed someone to approve their own upload.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('self-approval is not allowed', $e->getMessage());
            }

            $this->assertSame(AssetStatus::Draft, $asset->fresh()?->status);
        });
    }

    public function test_an_owner_can_allow_self_approval_for_a_one_person_workspace_and_it_is_logged()
    {
        $owner = $this->ownerOf($this->workspace);
        $asset = $this->uploadAsset($this->brand, uploader: $owner);

        $this->actingAs($owner)
            ->patch(route('workspaces.settings.approval', $this->workspace), ['allow_self_approval' => true])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('workspaces.assets.approve', [$this->workspace, $asset]))
            ->assertRedirect();

        $this->inWorkspace($this->workspace, function () use ($asset) {
            $this->assertSame(AssetStatus::Approved, $asset->fresh()?->status);
            $this->assertTrue(ActivityLog::where('action', 'asset.approved')->sole()->details['self_approved']);
            $this->assertTrue(ActivityLog::where('action', 'workspace.self_approval_changed')->exists());
        });
    }

    public function test_only_an_owner_changes_the_self_approval_setting()
    {
        $admin = $this->addMember($this->workspace, WorkspaceRole::Admin);

        $this->actingAs($admin->user)
            ->patch(route('workspaces.settings.approval', $this->workspace), ['allow_self_approval' => true])
            ->assertForbidden();

        $this->assertFalse($this->workspaceOf($this->brand)->allow_self_approval);
    }

    public function test_editors_cannot_approve_archive_or_restore()
    {
        $draft = $this->uploadAsset($this->brand, uploader: $this->manager->user);
        $approved = $this->uploadAsset($this->brand, status: AssetStatus::Approved);
        $archived = $this->uploadAsset($this->brand, status: AssetStatus::Archived);

        $this->actingAs($this->editor->user);

        $this->post(route('workspaces.assets.approve', [$this->workspace, $draft]))->assertForbidden();
        $this->post(route('workspaces.assets.archive', [$this->workspace, $approved]))->assertForbidden();
        // An archived file is hidden from Editors altogether.
        $this->post(route('workspaces.assets.restore', [$this->workspace, $archived]))->assertNotFound();
    }

    public function test_archiving_hides_a_file_from_viewers_and_restoring_brings_it_back()
    {
        $asset = $this->uploadAsset($this->brand, status: AssetStatus::Approved);
        $viewer = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $viewer, BrandRoleName::Viewer);

        $this->actingAs($this->manager->user)->post(route('workspaces.assets.archive', [$this->workspace, $asset]))->assertRedirect();
        $this->actingAs($viewer->user)->get(route('workspaces.assets.download', [$this->workspace, $asset]))->assertNotFound();

        $this->actingAs($this->manager->user)->post(route('workspaces.assets.restore', [$this->workspace, $asset]))->assertRedirect();
        $this->actingAs($viewer->user)->get(route('workspaces.assets.download', [$this->workspace, $asset]))->assertOk();
    }

    public function test_editors_change_drafts_but_only_managers_change_approved_files()
    {
        $draft = $this->uploadAsset($this->brand, uploader: $this->editor->user);
        $approved = $this->uploadAsset($this->brand, status: AssetStatus::Approved);

        $this->actingAs($this->editor->user)
            ->patch(route('workspaces.assets.update', [$this->workspace, $draft]), [
                'name' => 'Primary logo, green',
                'kind' => 'logo',
                'tags' => ['Primary', 'ዋና'],
            ])
            ->assertRedirect();

        $this->actingAs($this->editor->user)
            ->patch(route('workspaces.assets.update', [$this->workspace, $approved]), ['name' => 'Changed', 'kind' => 'logo'])
            ->assertForbidden();

        $this->actingAs($this->manager->user)
            ->patch(route('workspaces.assets.update', [$this->workspace, $approved]), ['name' => 'Changed', 'kind' => 'photo', 'tags' => []])
            ->assertRedirect();

        $this->inWorkspace($this->workspace, function () use ($draft, $approved) {
            $draft = Asset::with('tags')->findOrFail($draft->id);
            $this->assertSame('Primary logo, green', $draft->name);
            $this->assertSame(['Primary', 'ዋና'], $draft->tags->pluck('name')->all());
            $this->assertSame('Changed', $approved->fresh()?->name);
        });
    }

    public function test_drafts_are_deleted_by_their_uploader_or_a_manager_and_approved_files_are_not()
    {
        $mine = $this->uploadAsset($this->brand, uploader: $this->editor->user);
        $someoneElses = $this->uploadAsset($this->brand, uploader: $this->manager->user);
        $approved = $this->uploadAsset($this->brand, status: AssetStatus::Approved);

        $this->actingAs($this->editor->user)->delete(route('workspaces.assets.destroy', [$this->workspace, $someoneElses]))->assertForbidden();
        $this->actingAs($this->manager->user)->delete(route('workspaces.assets.destroy', [$this->workspace, $approved]))->assertForbidden();

        $this->actingAs($this->editor->user)->delete(route('workspaces.assets.destroy', [$this->workspace, $mine]))->assertRedirect();

        $this->inWorkspace($this->workspace, function () use ($mine) {
            $this->assertNull($mine->fresh());
            $this->assertSame(2, Asset::count());
        });
        Storage::disk($mine->disk)->assertMissing($mine->path);
        Storage::disk($mine->disk)->assertMissing((string) $mine->preview_path);
    }
}
