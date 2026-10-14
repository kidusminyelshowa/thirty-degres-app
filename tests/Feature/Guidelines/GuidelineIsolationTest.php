<?php

namespace Tests\Feature\Guidelines;

use App\Enums\AssetStatus;
use App\Guidelines\GuidelineBlocks;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\GuidelinePage;
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
use Tests\Concerns\CreatesGuidelines;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * The three locks for guideline pages. An Owner of company A tries every
 * guideline route with company B's ids, and tries to put B's files on A's
 * pages; then raw database queries try the same.
 */
class GuidelineIsolationTest extends TestCase
{
    use CreatesAssets, CreatesGuidelines, CreatesWorkspaces, RefreshDatabase;

    private Workspace $companyA;

    private Workspace $companyB;

    private User $ownerOfA;

    private Brand $brandOfA;

    private Brand $brandOfB;

    private GuidelinePage $pageOfB;

    private GuidelinePage $draftOfB;

    private Asset $logoOfB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $this->companyA = $this->createWorkspace('Company A');
        $this->companyB = $this->createWorkspace('Company B');
        $this->ownerOfA = $this->ownerOf($this->companyA);
        $this->brandOfA = $this->createBrand($this->companyA, 'Brand A');
        $this->brandOfB = $this->createBrand($this->companyB, 'Brand B');
        $this->logoOfB = $this->uploadAsset($this->brandOfB, file: UploadedFile::fake()->image('secret-logo.png'), status: AssetStatus::Approved);
        $this->pageOfB = $this->createGuidelinePage($this->brandOfB, title: 'Secret launch colours', publisher: User::factory()->create());
        $this->draftOfB = $this->createGuidelinePage($this->brandOfB, title: 'Draft', position: 1);
    }

    public function test_another_companys_guideline_pages_are_not_found_at_either_address()
    {
        $this->actingAs($this->ownerOfA);

        foreach ([$this->companyB, $this->companyA] as $workspace) {
            $this->get(route('workspaces.brands.guidelines.index', [$workspace, $this->brandOfB]))->assertNotFound();
            $this->post(route('workspaces.brands.guidelines.store', [$workspace, $this->brandOfB]), ['template' => 'logo'])->assertNotFound();

            foreach ([$this->pageOfB, $this->draftOfB] as $page) {
                $this->get(route('workspaces.guidelines.show', [$workspace, $page]))->assertNotFound();
                $this->get(route('workspaces.guidelines.edit', [$workspace, $page]))->assertNotFound();
                $this->put(route('workspaces.guidelines.update', [$workspace, $page]), ['title_en' => 'Taken', 'title_am' => '', 'blocks' => []])->assertNotFound();
                $this->post(route('workspaces.guidelines.publish', [$workspace, $page]))->assertNotFound();
                $this->post(route('workspaces.guidelines.move', [$workspace, $page]), ['direction' => 'up'])->assertNotFound();
                $this->delete(route('workspaces.guidelines.destroy', [$workspace, $page]))->assertNotFound();
            }

            $this->get(route('workspaces.assets.font', [$workspace, $this->logoOfB]))->assertNotFound();
        }

        $this->inWorkspace($this->companyB, function () {
            $this->assertSame(2, GuidelinePage::count());
            $this->assertSame('Secret launch colours', GuidelinePage::findOrFail($this->pageOfB->id)->title_en);
            $this->assertFalse(GuidelinePage::findOrFail($this->draftOfB->id)->isPublished());
        });
    }

    public function test_company_as_guidelines_list_never_shows_company_bs_brands_or_pages()
    {
        $this->createGuidelinePage($this->brandOfA, title: 'Our logo');

        $this->actingAs($this->ownerOfA)
            ->get(route('workspaces.guidelines.index', $this->companyA))
            ->assertInertia(fn (Assert $page) => $page
                ->component('guidelines/index')
                ->has('brands', 1)
                ->where('brands.0.name', 'Brand A')
                ->has('brands.0.pages', 1)
                ->where('brands.0.pages.0.title.en', 'Our logo'));
    }

    public function test_a_page_cannot_show_another_companys_files()
    {
        $page = $this->createGuidelinePage($this->brandOfA);

        $this->actingAs($this->ownerOfA)
            ->put(route('workspaces.guidelines.update', [$this->companyA, $page]), [
                'title_en' => 'Logo',
                'title_am' => '',
                'blocks' => [[
                    'type' => 'logos',
                    'items' => [['assetId' => $this->logoOfB->id, 'caption' => ['en' => '', 'am' => ''], 'background' => 'light']],
                ]],
            ])
            ->assertSessionHasErrors('blocks');

        $this->inWorkspace($this->companyA, fn () => $this->assertSame([], GuidelineBlocks::assetIds(GuidelinePage::findOrFail($page->id)->blocks)));
    }

    public function test_the_database_keeps_guideline_pages_apart_even_without_app_filters()
    {
        $this->inWorkspace($this->companyA, function () {
            $this->assertSame(0, DB::table('guideline_pages')->where('workspace_id', $this->companyB->id)->count());
            $this->assertSame(0, GuidelinePage::withoutGlobalScopes()->count());
            $this->assertSame(0, DB::table('guideline_pages')->where('id', $this->pageOfB->id)->update(['title_en' => 'Taken']));
            $this->assertSame(0, DB::table('guideline_pages')->where('id', $this->pageOfB->id)->delete());

            // A row filed under company B, and A's page on B's brand.
            foreach ([$this->companyB->id, $this->companyA->id] as $workspaceId) {
                try {
                    DB::transaction(fn () => DB::table('guideline_pages')->insert([
                        'id' => (string) Str::uuid7(),
                        'workspace_id' => $workspaceId,
                        'brand_id' => $this->brandOfB->id,
                        'blocks' => '[]',
                    ]));
                    $this->fail('The database allowed a guideline page it should have refused.');
                } catch (QueryException $e) {
                    $this->assertMatchesRegularExpression('/row-level security|foreign key|violates/', $e->getMessage());
                }
            }
        });
    }
}

