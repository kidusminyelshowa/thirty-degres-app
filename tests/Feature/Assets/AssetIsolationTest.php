<?php

namespace Tests\Feature\Assets;

use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\BrandRole;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * The three locks for brands and files. An Owner of company A tries every
 * brand and file route with company B's ids; each answers "not found" and
 * changes nothing. Then raw database queries try the same.
 */
class AssetIsolationTest extends TestCase
{
    use CreatesAssets, CreatesWorkspaces, RefreshDatabase;

    private Workspace $companyA;

    private Workspace $companyB;

    private User $ownerOfA;

    private Brand $brandOfA;

    private Brand $brandOfB;

    private Asset $assetOfB;

    private Asset $draftOfB;

    private BrandRole $roleOfB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $this->companyA = $this->createWorkspace('Company A');
        $this->companyB = $this->createWorkspace('Company B');
        $this->ownerOfA = $this->ownerOf($this->companyA);
        $this->brandOfA = $this->createBrand($this->companyA, 'Brand A');
        $this->brandOfB = $this->createBrand($this->companyB, 'Brand B');
        $this->assetOfB = $this->uploadAsset($this->brandOfB, file: UploadedFile::fake()->image('secret-launch.png'), tags: ['Launch'], status: AssetStatus::Approved);
        $this->draftOfB = $this->uploadAsset($this->brandOfB, file: UploadedFile::fake()->image('draft.png'));
        $this->roleOfB = $this->giveBrandRole($this->brandOfB, $this->addMember($this->companyB), BrandRoleName::Viewer);
    }

    public function test_another_companys_brands_and_files_are_not_found_at_either_address()
    {
        $this->actingAs($this->ownerOfA);

        foreach ([$this->companyB, $this->companyA] as $workspace) {
            $this->get(route('workspaces.brands.show', [$workspace, $this->brandOfB]))->assertNotFound();
            $this->patch(route('workspaces.brands.update', [$workspace, $this->brandOfB]), ['name' => 'Taken'])->assertNotFound();
            $this->get(route('workspaces.brands.access.index', [$workspace, $this->brandOfB]))->assertNotFound();
            $this->post(route('workspaces.brands.access.store', [$workspace, $this->brandOfB]), ['membership_id' => $this->roleOfB->membership_id, 'role' => 'manager'])->assertNotFound();
            $this->delete(route('workspaces.brands.access.destroy', [$workspace, $this->brandOfB, $this->roleOfB]))->assertNotFound();
            $this->post(route('workspaces.brands.assets.store', [$workspace, $this->brandOfB]), ['file' => UploadedFile::fake()->image('x.png')])->assertNotFound();

            foreach ([$this->assetOfB, $this->draftOfB] as $asset) {
                $this->get(route('workspaces.assets.show', [$workspace, $asset]))->assertNotFound();
                $this->get(route('workspaces.assets.download', [$workspace, $asset]))->assertNotFound();
                $this->get(route('workspaces.assets.preview', [$workspace, $asset]))->assertNotFound();
                $this->patch(route('workspaces.assets.update', [$workspace, $asset]), ['name' => 'Taken', 'kind' => 'logo'])->assertNotFound();
                $this->post(route('workspaces.assets.approve', [$workspace, $asset]))->assertNotFound();
                $this->post(route('workspaces.assets.archive', [$workspace, $asset]))->assertNotFound();
                $this->post(route('workspaces.assets.restore', [$workspace, $asset]))->assertNotFound();
                $this->delete(route('workspaces.assets.destroy', [$workspace, $asset]))->assertNotFound();
            }
        }

        // A's own brand page cannot be pointed at B's role either.
        $this->delete(route('workspaces.brands.access.destroy', [$this->companyA, $this->brandOfA, $this->roleOfB]))->assertNotFound();

        $this->inWorkspace($this->companyB, function () {
            $this->assertSame('Brand B', $this->brandOfB->fresh()?->name);
            $this->assertSame(2, Asset::count());
            $this->assertSame(AssetStatus::Draft, $this->draftOfB->fresh()?->status);
            $this->assertSame('secret-launch', $this->assetOfB->fresh()?->name);
            $this->assertSame(BrandRoleName::Viewer, $this->roleOfB->fresh()?->role);
        });
    }

    public function test_company_as_library_search_and_tags_never_show_company_bs_files()
    {
        $this->uploadAsset($this->brandOfA, file: UploadedFile::fake()->image('our-logo.png'), tags: ['Launch']);

        $this->actingAs($this->ownerOfA)
            ->get(route('workspaces.library', [$this->companyA, 'q' => 'secret']))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 0));

        $this->actingAs($this->ownerOfA)
            ->get(route('workspaces.library', [$this->companyA, 'tag' => 'Launch']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('assets.data', 1)
                ->where('assets.data.0.name', 'our-logo')
                ->has('brands', 1)
                ->where('tags', ['Launch']));
    }

    public function test_the_database_keeps_brands_files_and_tags_apart_even_without_app_filters()
    {
        $this->inWorkspace($this->companyA, function () {
            foreach (['brands', 'brand_roles', 'assets', 'tags', 'asset_tag'] as $table) {
                $this->assertSame(0, DB::table($table)->where('workspace_id', $this->companyB->id)->count(), $table);
            }

            $this->assertSame(0, Asset::withoutGlobalScopes()->count());
            $this->assertSame(0, DB::table('assets')->where('id', $this->assetOfB->id)->update(['name' => 'Taken']));
            $this->assertSame(0, DB::table('assets')->where('id', $this->assetOfB->id)->delete());
        });
    }

    public function test_rows_cannot_be_written_into_another_company_or_point_at_its_data()
    {
        $tagOfB = $this->inWorkspace($this->companyB, fn () => Tag::sole());

        $this->inWorkspace($this->companyA, function () use ($tagOfB) {
            // A row filed under company B.
            $this->assertRefused(fn () => DB::table('brands')->insert([
                'id' => (string) Str::uuid7(),
                'workspace_id' => $this->companyB->id,
                'name' => 'Planted',
            ]));

            // A's file pointing at B's brand.
            $this->assertRefused(fn () => DB::table('assets')->insert([
                'id' => (string) Str::uuid7(),
                'workspace_id' => $this->companyA->id,
                'brand_id' => $this->brandOfB->id,
                'name' => 'Planted',
                'kind' => 'logo',
                'original_name' => 'x.png',
                'extension' => 'png',
                'mime_type' => 'image/png',
                'size' => 1,
                'disk' => 'local',
                'path' => 'x',
            ]));

            // B's tag on A's file, and A's sub-brand under B's brand.
            $assetOfA = $this->uploadAsset($this->brandOfA);
            $this->assertRefused(fn () => DB::table('asset_tag')->insert([
                'workspace_id' => $this->companyA->id,
                'asset_id' => $assetOfA->id,
                'tag_id' => $tagOfB->id,
            ]));
            $this->assertRefused(fn () => DB::table('brands')->insert([
                'id' => (string) Str::uuid7(),
                'workspace_id' => $this->companyA->id,
                'parent_id' => $this->brandOfB->id,
                'name' => 'Planted',
            ]));
        });
    }

    private function assertRefused(callable $query): void
    {
        try {
            DB::transaction(fn () => $query());
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/row-level security|foreign key|violates/', $e->getMessage());

            return;
        }

        $this->fail('The database allowed a write it should have refused.');
    }
}
