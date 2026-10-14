<?php

namespace Tests\Feature\Assets;

use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Enums\MembershipKind;
use App\Models\Brand;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * Finding files: search, tags, filters, and who sees which files.
 */
class AssetLibraryTest extends TestCase
{
    use CreatesAssets, CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $this->workspace = $this->createWorkspace('Ethio Coffee');
        $this->brand = $this->createBrand($this->workspace);
    }

    public function test_files_are_found_by_name_by_tag_and_by_type()
    {
        $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('primary-logo.png'), tags: ['Primary logo']);
        $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('harvest.jpg'), tags: ['ቡና', 'Campaign']);
        $this->uploadAsset($this->brand, file: UploadedFile::fake()->create('Brand-Sans.otf', 50), tags: ['Typeface']);

        $owner = $this->ownerOf($this->workspace);
        $this->actingAs($owner);

        $this->get(route('workspaces.library', [$this->workspace, 'q' => 'PRIMARY']))
            ->assertInertia(fn (Assert $page) => $page->component('assets/index')->has('assets.data', 1)->where('assets.data.0.name', 'primary-logo'));

        // Part of a tag, in Ge'ez.
        $this->get(route('workspaces.library', [$this->workspace, 'q' => 'ቡ']))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 1)->where('assets.data.0.name', 'harvest'));

        // An exact tag, whatever the capitals.
        $this->get(route('workspaces.library', [$this->workspace, 'tag' => 'campaign']))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 1)->where('assets.data.0.tags', ['Campaign', 'ቡና']));

        $this->get(route('workspaces.library', [$this->workspace, 'kind' => 'font']))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 1)->where('assets.data.0.name', 'Brand-Sans'));

        $this->get(route('workspaces.library', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 3)->has('tags', 4));
    }

    public function test_search_wildcards_are_taken_literally()
    {
        $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('logo.png'));

        $this->actingAs($this->ownerOf($this->workspace))
            ->get(route('workspaces.library', [$this->workspace, 'q' => '%']))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 0));
    }

    public function test_pages_link_to_light_previews_never_to_originals()
    {
        $asset = $this->uploadAsset($this->brand);

        $this->actingAs($this->ownerOf($this->workspace))
            ->get(route('workspaces.library', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page->where(
                'assets.data.0.previewUrl',
                fn (string $url) => str_contains($url, "/assets/{$asset->id}/preview"),
            ));
    }

    public function test_viewers_see_approved_files_only()
    {
        $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('approved.png'), status: AssetStatus::Approved);
        $draft = $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('draft.png'));
        $archived = $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('old.png'), status: AssetStatus::Archived);

        $viewer = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $viewer, BrandRoleName::Viewer);
        $this->actingAs($viewer->user);

        $this->get(route('workspaces.library', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 1)->where('assets.data.0.name', 'approved'));
        $this->get(route('workspaces.library', [$this->workspace, 'status' => 'archived']))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 0)->where('canSeeArchived', false));

        $this->get(route('workspaces.assets.show', [$this->workspace, $draft]))->assertNotFound();
        $this->get(route('workspaces.assets.show', [$this->workspace, $archived]))->assertNotFound();
    }

    public function test_staff_editors_see_drafts_and_partner_editors_only_their_own()
    {
        $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('approved.png'), status: AssetStatus::Approved);
        $staffDraft = $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('staff-draft.png'));

        $editor = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $editor, BrandRoleName::Editor);
        $partner = $this->addMember($this->workspace, kind: MembershipKind::Partner);
        $this->giveBrandRole($this->brand, $partner, BrandRoleName::Editor);
        $this->uploadAsset($this->brand, uploader: $partner->user, file: UploadedFile::fake()->image('agency-draft.png'));

        $this->actingAs($editor->user)
            ->get(route('workspaces.library', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 3));

        $this->actingAs($partner->user)
            ->get(route('workspaces.library', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page
                ->has('assets.data', 2)
                ->where('assets.data.0.name', 'agency-draft')
                ->where('assets.data.1.name', 'approved'));

        $this->actingAs($partner->user)
            ->get(route('workspaces.assets.show', [$this->workspace, $staffDraft]))
            ->assertNotFound();
    }

    public function test_brand_managers_find_archived_files_when_they_ask_for_them()
    {
        $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('current.png'), status: AssetStatus::Approved);
        $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('old.png'), status: AssetStatus::Archived);

        $this->actingAs($this->ownerOf($this->workspace));

        $this->get(route('workspaces.library', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 1)->where('canSeeArchived', true));
        $this->get(route('workspaces.library', [$this->workspace, 'status' => 'archived']))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 1)->where('assets.data.0.name', 'old'));
    }

    public function test_a_brand_page_shows_that_brands_files_only()
    {
        $other = $this->createBrand($this->workspace, 'Sister brand');
        $this->uploadAsset($this->brand, file: UploadedFile::fake()->image('ours.png'));
        $this->uploadAsset($other, file: UploadedFile::fake()->image('theirs.png'));

        $this->actingAs($this->ownerOf($this->workspace))
            ->get(route('workspaces.brands.show', [$this->workspace, $this->brand]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('brand.name', 'Yirgacheffe Gold')
                ->has('assets.data', 1)
                ->where('assets.data.0.name', 'ours'));
    }

    public function test_people_without_brand_roles_see_an_empty_library()
    {
        $this->uploadAsset($this->brand, status: AssetStatus::Approved);
        $member = $this->addMember($this->workspace);

        $this->actingAs($member->user)
            ->get(route('workspaces.library', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page->has('assets.data', 0)->has('brands', 0)->has('tags', 0));
    }

    public function test_expired_partners_see_nothing_at_all()
    {
        $partner = $this->addMember($this->workspace, kind: MembershipKind::Partner, accessEndsAt: now()->addDay());
        $this->giveBrandRole($this->brand, $partner, BrandRoleName::Viewer);
        $asset = $this->uploadAsset($this->brand, status: AssetStatus::Approved);

        $this->travel(2)->days();

        $this->actingAs($partner->user)->get(route('workspaces.library', $this->workspace))->assertNotFound();
        $this->actingAs($partner->user)->get(route('workspaces.assets.download', [$this->workspace, $asset]))->assertNotFound();
    }
}
