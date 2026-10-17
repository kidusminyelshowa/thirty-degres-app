<?php

namespace Tests\Feature\Guidelines;

use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Enums\MembershipKind;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\GuidelinePage;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesGuidelines;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * Writing, publishing and reading bilingual guideline pages (THI-37), with
 * the role table in docs/roles-and-permissions.md.
 */
class GuidelinePageTest extends TestCase
{
    use CreatesAssets, CreatesGuidelines, CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    private Brand $brand;

    private Membership $manager;

    private Membership $editor;

    private Membership $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $this->workspace = $this->createWorkspace('Ethio Coffee');
        $this->brand = $this->createBrand($this->workspace, 'Yirgacheffe Gold');
        $this->manager = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $this->manager, BrandRoleName::Manager);
        $this->editor = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $this->editor, BrandRoleName::Editor);
        $this->viewer = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $this->viewer, BrandRoleName::Viewer);
    }

    public function test_an_editor_starts_a_page_from_a_template_and_lands_in_the_editor()
    {
        $this->actingAs($this->editor->user)
            ->post(route('workspaces.brands.guidelines.store', [$this->workspace, $this->brand]), ['template' => 'colour'])
            ->assertRedirect();

        $this->actingAs($this->editor->user)
            ->post(route('workspaces.brands.guidelines.store', [$this->workspace, $this->brand]), ['template' => 'type']);

        $this->inWorkspace($this->workspace, function () {
            $pages = GuidelinePage::orderBy('position')->get();
            $this->assertSame(['Colour', 'Typography'], $pages->map(fn (GuidelinePage $page) => $page->title['en'])->all());
            $this->assertSame([0, 1], $pages->pluck('position')->all());
            $this->assertSame('colours', $pages[0]->blocks[1]['type']);
            $this->assertSame('type_pairing', $pages[1]->blocks[1]['type']);
            $this->assertFalse($pages[0]->isPublished());
            $this->assertSame($this->editor->user_id, $pages[0]->edited_by);
            $this->assertSame(2, ActivityLog::where('action', 'guideline.created')->count());
        });

        $page = $this->inWorkspace($this->workspace, fn () => GuidelinePage::whereRaw("title->>'en' = 'Colour'")->sole());

        $this->actingAs($this->editor->user)
            ->get(route('workspaces.guidelines.edit', [$this->workspace, $page]))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('guidelines/edit')
                ->where('page.title.en', 'Colour')
                ->has('page.blocks', 2)
                ->has('pages', 2));
    }

    public function test_a_brand_with_no_pages_shows_where_to_start()
    {
        $this->actingAs($this->editor->user)
            ->get(route('workspaces.brands.guidelines.index', [$this->workspace, $this->brand]))
            ->assertInertia(fn (Assert $page) => $page->component('guidelines/empty')->where('canCreate', true));

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.brands.guidelines.index', [$this->workspace, $this->brand]))
            ->assertInertia(fn (Assert $page) => $page->component('guidelines/empty')->where('canCreate', false));
    }

    public function test_a_brands_guidelines_open_on_the_first_page_the_person_can_see()
    {
        $this->createGuidelinePage($this->brand, title: 'Draft colour', position: 0);
        $published = $this->createGuidelinePage($this->brand, title: 'Logo', position: 1, publisher: $this->manager->user);

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.brands.guidelines.index', [$this->workspace, $this->brand]))
            ->assertRedirect(route('workspaces.guidelines.show', [$this->workspace, $published]));
    }

    public function test_saving_cleans_and_keeps_both_languages()
    {
        $page = $this->createGuidelinePage($this->brand, author: $this->editor->user);

        $this->actingAs($this->editor->user)
            ->put(route('workspaces.guidelines.update', [$this->workspace, $page]), [
                'title' => ['en' => '  Colour ', 'am' => 'ቀለም', 'om' => 'Halluu', 'ar' => ''],
                'blocks' => [
                    ['type' => 'heading', 'text' => ['en' => 'Our colours', 'am' => 'ቀለሞቻችን']],
                    ['id' => 'swatches', 'type' => 'colours', 'swatches' => [
                        ['name' => ['en' => 'Coffee', 'am' => 'ቡና'], 'hex' => '#6f4e37', 'note' => ['en' => null, 'am' => '']],
                    ]],
                ],
            ])
            ->assertRedirect(route('workspaces.guidelines.edit', [$this->workspace, $page]))
            ->assertSessionHasNoErrors();

        $this->inWorkspace($this->workspace, function () use ($page) {
            $fresh = GuidelinePage::findOrFail($page->id);
            $this->assertSame(['am' => 'ቀለም', 'en' => 'Colour', 'om' => 'Halluu'], $fresh->title);
            $this->assertCount(2, $fresh->blocks);
            $this->assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $fresh->blocks[0]['id']);
            $this->assertSame(['am' => 'ቀለሞቻችን', 'en' => 'Our colours'], $fresh->blocks[0]['text']);
            $this->assertSame('swatches', $fresh->blocks[1]['id']);
            $this->assertSame('#6F4E37', $fresh->blocks[1]['swatches'][0]['hex']);
            $this->assertSame([], $fresh->blocks[1]['swatches'][0]['note']);
            $this->assertSame($this->editor->user_id, $fresh->edited_by);
        });
    }

    public function test_saving_refuses_blocks_that_do_not_fit()
    {
        $page = $this->createGuidelinePage($this->brand, author: $this->editor->user);
        $save = fn (array $blocks, string $title = 'Logo') => $this->actingAs($this->editor->user)
            ->put(route('workspaces.guidelines.update', [$this->workspace, $page]), ['title' => ['en' => $title], 'blocks' => $blocks]);

        $save([['type' => 'script', 'text' => ['en' => '<script>', 'am' => '']]])->assertSessionHasErrors('blocks.0.type');
        $save([['type' => 'colours', 'swatches' => [['name' => ['en' => 'Red', 'am' => ''], 'hex' => 'red', 'note' => ['en' => '', 'am' => '']]]]])
            ->assertSessionHasErrors('blocks.0.swatches.0.hex');
        $save([['type' => 'heading', 'text' => ['en' => str_repeat('a', 301), 'am' => '']]])->assertSessionHasErrors('blocks.0.text.en');
        $save([['type' => 'heading', 'text' => ['en' => 'Hi', 'am' => ''], 'html' => '<b>']])->assertSessionHasErrors('blocks.0');
        $save(array_fill(0, 61, ['type' => 'heading', 'text' => ['en' => 'Hi', 'am' => '']]))->assertSessionHasErrors('blocks');
        $save([], '')->assertSessionHasErrors('title');
        $save([['type' => 'heading', 'text' => ['xx' => 'Unknown language']]])->assertSessionHasErrors('blocks.0.text');

        $this->inWorkspace($this->workspace, fn () => $this->assertSame('text', GuidelinePage::findOrFail($page->id)->blocks[0]['type']));
    }

    public function test_typefaces_can_use_the_brands_own_font_files_only()
    {
        $page = $this->createGuidelinePage($this->brand, author: $this->editor->user);
        $font = $this->uploadAsset($this->brand, file: UploadedFile::fake()->create('NotoSerifEthiopic.woff2', 40, 'font/woff2'), status: AssetStatus::Approved);
        $logo = $this->uploadAsset($this->brand, status: AssetStatus::Approved);

        $pairing = fn (string $assetId) => [[
            'type' => 'type_pairing',
            'faces' => [
                ['script' => 'ethiopic', 'family' => 'Noto Serif Ethiopic', 'assetId' => $assetId, 'weight' => 500, 'sample' => 'ቡና'],
                ['script' => 'latin', 'family' => 'Inter', 'assetId' => null, 'weight' => 400, 'sample' => 'Coffee'],
                ['script' => 'arabic', 'family' => '', 'assetId' => null, 'weight' => 400, 'sample' => 'قهوة'],
            ],
            'scale' => [['label' => ['en' => 'Body'], 'sizes' => ['ethiopic' => 17, 'latin' => 16, 'arabic' => 18]]],
            'note' => [],
        ]];

        $this->actingAs($this->editor->user)
            ->put(route('workspaces.guidelines.update', [$this->workspace, $page]), ['title' => ['en' => 'Type'], 'blocks' => $pairing($logo->id)])
            ->assertSessionHasErrors('blocks');

        $this->actingAs($this->editor->user)
            ->put(route('workspaces.guidelines.update', [$this->workspace, $page]), ['title' => ['en' => 'Type'], 'blocks' => $pairing($font->id)])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.assets.font', [$this->workspace, $font]))
            ->assertOk()
            ->assertHeader('Content-Type', 'font/woff2');

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.assets.font', [$this->workspace, $logo]))
            ->assertNotFound();
    }

    public function test_viewers_download_the_brand_typeface_from_a_published_page_and_other_companies_cannot()
    {
        $font = $this->uploadAsset($this->brand, file: UploadedFile::fake()->create('YirgaSans.woff2', 40, 'font/woff2'), status: AssetStatus::Approved);
        $page = $this->createGuidelinePage($this->brand, author: $this->editor->user, publisher: $this->manager->user, blocks: [[
            'id' => 'type',
            'type' => 'type_pairing',
            'faces' => [['script' => 'ethiopic', 'family' => 'Yirga Sans', 'assetId' => $font->id, 'weight' => 500, 'sample' => 'ቡና']],
            'scale' => [],
            'note' => [],
        ]]);
        $download = route('workspaces.assets.download', [$this->workspace, $font]);

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $page]))
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia->where("assets.{$font->id}.downloadUrl", $download));

        $this->actingAs($this->viewer->user)
            ->get($download)
            ->assertOk()
            ->assertDownload('YirgaSans.woff2');

        $other = $this->createWorkspace('Another Company');
        $this->createBrand($other, 'Another Brand');

        foreach ([$other, $this->workspace] as $workspace) {
            $this->actingAs($this->ownerOf($other))
                ->get(route('workspaces.assets.download', [$workspace, $font]))
                ->assertNotFound();
        }
    }

    public function test_a_brand_manager_publishes_someone_elses_page_and_viewers_see_that_version()
    {
        $page = $this->createGuidelinePage($this->brand, author: $this->editor->user, title: 'Logo');

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $page]))
            ->assertNotFound();

        $this->actingAs($this->manager->user)
            ->post(route('workspaces.guidelines.publish', [$this->workspace, $page]))
            ->assertRedirect(route('workspaces.guidelines.show', [$this->workspace, $page]));

        // The editor keeps working; Viewers still see what was published.
        $this->actingAs($this->editor->user)
            ->put(route('workspaces.guidelines.update', [$this->workspace, $page]), [
                'title' => ['en' => 'Logo v2'],
                'blocks' => [['type' => 'text', 'text' => ['en' => 'Unreleased rule', 'am' => '']]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $page]))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('guidelines/show')
                ->where('page.title.en', 'Logo')
                ->where('page.title.am', 'አርማ')
                ->where('languages.0.code', 'am')
                ->where('languages.1.code', 'en')
                ->where('page.blocks.0.text.am', 'አርማውን በነጭ ላይ ተጠቀሙ።')
                ->where('page.hasChanges', false)
                ->where('can.update', false)
                ->where('can.publish', false));

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.guidelines.edit', [$this->workspace, $page]))
            ->assertForbidden();

        $this->actingAs($this->manager->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $page]))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('page.hasChanges', true)
                ->where('can.publish', true));

        $this->inWorkspace($this->workspace, function () {
            $log = ActivityLog::where('action', 'guideline.published')->sole();
            $this->assertSame($this->manager->user_id, $log->actor_id);
            $this->assertFalse($log->details['self_approved']);
        });
    }

    public function test_nobody_publishes_their_own_edits()
    {
        $page = $this->createGuidelinePage($this->brand, author: $this->manager->user);

        $this->actingAs($this->manager->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $page]))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('can.publish', false)
                ->where('can.publishReason', 'Someone else must publish your changes.'));

        $this->actingAs($this->manager->user)
            ->post(route('workspaces.guidelines.publish', [$this->workspace, $page]))
            ->assertForbidden();

        // The database refuses it too, whatever the app does.
        $this->inWorkspace($this->workspace, function () use ($page) {
            $this->expectException(QueryException::class);

            DB::table('guideline_pages')->where('id', $page->id)->update([
                'published_title' => '{"en": "Logo"}',
                'published_blocks' => '[]',
                'published_by' => $this->manager->user_id,
                'published_at' => now(),
            ]);
        });
    }

    public function test_a_one_person_workspace_can_publish_its_own_pages_and_it_is_logged()
    {
        $owner = $this->ownerOf($this->workspace);
        $page = $this->createGuidelinePage($this->brand, author: $owner);

        $this->actingAs($owner)
            ->patch(route('workspaces.settings.approval', $this->workspace), ['allow_self_approval' => true]);

        $this->actingAs($owner)
            ->post(route('workspaces.guidelines.publish', [$this->workspace, $page]))
            ->assertRedirect(route('workspaces.guidelines.show', [$this->workspace, $page]));

        $this->inWorkspace($this->workspace, function () use ($page) {
            $this->assertTrue(GuidelinePage::findOrFail($page->id)->isPublished());
            $this->assertTrue(ActivityLog::where('action', 'guideline.published')->sole()->details['self_approved']);
        });
    }

    public function test_editors_cannot_publish_and_pages_with_unapproved_files_wait()
    {
        $draftLogo = $this->uploadAsset($this->brand, uploader: $this->editor->user, file: UploadedFile::fake()->image('new-lockup.png'));
        $page = $this->createGuidelinePage($this->brand, author: $this->editor->user, blocks: [[
            'id' => 'logos',
            'type' => 'logos',
            'items' => [['assetId' => $draftLogo->id, 'caption' => ['en' => 'Lockup', 'am' => ''], 'background' => 'light']],
        ]]);

        $this->actingAs($this->editor->user)
            ->post(route('workspaces.guidelines.publish', [$this->workspace, $page]))
            ->assertForbidden();

        $this->actingAs($this->manager->user)
            ->post(route('workspaces.guidelines.publish', [$this->workspace, $page]))
            ->assertSessionHasErrors(['publish' => 'Approve these files before publishing: new-lockup']);

        $this->actingAs($this->manager->user)->post(route('workspaces.assets.approve', [$this->workspace, $draftLogo]));

        $this->actingAs($this->manager->user)
            ->post(route('workspaces.guidelines.publish', [$this->workspace, $page]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $page]))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->has("assets.{$draftLogo->id}")
                ->where("assets.{$draftLogo->id}.name", 'new-lockup'));
    }

    public function test_viewers_never_get_files_they_cannot_see_on_a_page()
    {
        $draft = $this->uploadAsset($this->brand, uploader: $this->editor->user);
        $page = $this->createGuidelinePage($this->brand, author: $this->editor->user, publisher: $this->manager->user, blocks: [[
            'id' => 'dont',
            'type' => 'dos_donts',
            'items' => [['kind' => 'dont', 'assetId' => $draft->id, 'caption' => ['en' => 'Stretched', 'am' => '']]],
        ]]);

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $page]))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('assets', []));
    }

    public function test_partner_editors_only_work_on_pages_they_started()
    {
        $partner = $this->addMember($this->workspace, kind: MembershipKind::Partner);
        $this->giveBrandRole($this->brand, $partner, BrandRoleName::Editor);
        $staffDraft = $this->createGuidelinePage($this->brand, author: $this->editor->user, title: 'Internal draft');
        $published = $this->createGuidelinePage($this->brand, author: $this->editor->user, publisher: $this->manager->user, position: 1);

        $this->actingAs($partner->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $staffDraft]))
            ->assertNotFound();
        $this->actingAs($partner->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $published]))
            ->assertOk();
        $this->actingAs($partner->user)
            ->put(route('workspaces.guidelines.update', [$this->workspace, $published]), ['title' => ['en' => 'Taken'], 'blocks' => []])
            ->assertForbidden();
        $this->actingAs($partner->user)
            ->post(route('workspaces.guidelines.move', [$this->workspace, $published]), ['direction' => 'up'])
            ->assertForbidden();

        $this->actingAs($partner->user)
            ->post(route('workspaces.brands.guidelines.store', [$this->workspace, $this->brand]), ['template' => 'voice'])
            ->assertRedirect();

        $own = $this->inWorkspace($this->workspace, fn () => GuidelinePage::where('created_by', $partner->user_id)->sole());

        $this->actingAs($partner->user)
            ->put(route('workspaces.guidelines.update', [$this->workspace, $own]), ['title' => ['en' => 'Voice', 'ti' => 'ድምፂ'], 'blocks' => []])
            ->assertSessionHasNoErrors();
        $this->actingAs($partner->user)
            ->post(route('workspaces.guidelines.publish', [$this->workspace, $own]))
            ->assertForbidden();
    }

    public function test_pages_can_be_put_in_order()
    {
        $first = $this->createGuidelinePage($this->brand, title: 'Logo', position: 0);
        $second = $this->createGuidelinePage($this->brand, title: 'Colour', position: 1);
        $third = $this->createGuidelinePage($this->brand, title: 'Type', position: 2);

        $this->actingAs($this->editor->user)
            ->post(route('workspaces.guidelines.move', [$this->workspace, $third]), ['direction' => 'up'])
            ->assertRedirect();
        $this->actingAs($this->editor->user)
            ->post(route('workspaces.guidelines.move', [$this->workspace, $first]), ['direction' => 'up'])
            ->assertRedirect();

        $this->inWorkspace($this->workspace, fn () => $this->assertSame(
            ['Logo', 'Type', 'Colour'],
            GuidelinePage::orderBy('position')->get()->map(fn (GuidelinePage $page) => $page->title['en'])->all(),
        ));

        $this->actingAs($this->viewer->user)
            ->post(route('workspaces.guidelines.move', [$this->workspace, $second]), ['direction' => 'up'])
            ->assertNotFound();
    }

    public function test_who_can_delete_a_page()
    {
        $draft = $this->createGuidelinePage($this->brand, author: $this->editor->user);
        $published = $this->createGuidelinePage($this->brand, author: $this->editor->user, publisher: $this->manager->user, position: 1);

        $this->actingAs($this->viewer->user)
            ->delete(route('workspaces.guidelines.destroy', [$this->workspace, $published]))
            ->assertForbidden();
        $this->actingAs($this->editor->user)
            ->delete(route('workspaces.guidelines.destroy', [$this->workspace, $published]))
            ->assertForbidden();
        $this->actingAs($this->editor->user)
            ->delete(route('workspaces.guidelines.destroy', [$this->workspace, $draft]))
            ->assertRedirect(route('workspaces.brands.guidelines.index', [$this->workspace, $this->brand]));
        $this->actingAs($this->manager->user)
            ->delete(route('workspaces.guidelines.destroy', [$this->workspace, $published]))
            ->assertRedirect();

        $this->inWorkspace($this->workspace, function () {
            $this->assertSame(0, GuidelinePage::count());
            $this->assertSame(2, ActivityLog::where('action', 'guideline.deleted')->count());
        });
    }

    public function test_viewers_cannot_start_pages_and_people_off_the_brand_see_nothing()
    {
        $published = $this->createGuidelinePage($this->brand, publisher: User::factory()->create());
        $outsider = $this->addMember($this->workspace);

        $this->actingAs($this->viewer->user)
            ->post(route('workspaces.brands.guidelines.store', [$this->workspace, $this->brand]), ['template' => 'logo'])
            ->assertForbidden();

        $this->actingAs($outsider->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $published]))
            ->assertNotFound();

        $this->actingAs($outsider->user)
            ->get(route('workspaces.guidelines.index', $this->workspace))
            ->assertInertia(fn (Assert $page) => $page->has('brands', 0));
    }

    public function test_brand_managers_choose_the_brands_languages_and_their_order()
    {
        $page = $this->createGuidelinePage($this->brand, publisher: User::factory()->create());

        $this->actingAs($this->manager->user)
            ->put(route('workspaces.brands.guidelines.languages', [$this->workspace, $this->brand]), ['languages' => ['om', 'am', 'en', 'ar']])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.guidelines.show', [$this->workspace, $page]))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('languages', fn ($languages) => collect($languages)->pluck('code')->all() === ['om', 'am', 'en', 'ar'])
                ->where('languages.3.dir', 'rtl')
                ->where('languages.1.script', 'ethiopic')
                ->where('can.setLanguages', false));

        foreach ([[], ['am', 'am'], ['xx'], array_fill(0, 9, 'en')] as $invalid) {
            $this->actingAs($this->manager->user)
                ->put(route('workspaces.brands.guidelines.languages', [$this->workspace, $this->brand]), ['languages' => $invalid])
                ->assertSessionHasErrors();
        }

        foreach ([$this->editor, $this->viewer] as $member) {
            $this->actingAs($member->user)
                ->put(route('workspaces.brands.guidelines.languages', [$this->workspace, $this->brand]), ['languages' => ['en']])
                ->assertForbidden();
        }

        $this->inWorkspace($this->workspace, function () {
            $this->assertSame(['om', 'am', 'en', 'ar'], Brand::findOrFail($this->brand->id)->guideline_languages);
            $this->assertSame(1, ActivityLog::where('action', 'brand.guideline_languages')->count());
        });
    }

    public function test_new_and_existing_brands_start_with_amharic_and_english()
    {
        $this->inWorkspace($this->workspace, fn () => $this->assertSame(['am', 'en'], Brand::findOrFail($this->brand->id)->guideline_languages));
    }
}
