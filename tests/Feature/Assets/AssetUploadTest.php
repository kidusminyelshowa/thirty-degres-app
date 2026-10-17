<?php

namespace Tests\Feature\Assets;

use App\Enums\AssetKind;
use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Enums\PreviewStatus;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\Membership;
use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class AssetUploadTest extends TestCase
{
    use CreatesAssets, CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    private Brand $brand;

    private Membership $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $this->workspace = $this->createWorkspace('Ethio Coffee');
        $this->brand = $this->createBrand($this->workspace);
        $this->editor = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $this->editor, BrandRoleName::Editor);
    }

    public function test_an_editor_uploads_a_logo_as_a_draft_with_a_light_preview()
    {
        $this->actingAs($this->editor->user)
            ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                'file' => UploadedFile::fake()->image('primary-logo.png', 2400, 1200)->size(900),
                'tags' => ['Primary logo', 'ዋና ሎጎ'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $asset = $this->inWorkspace($this->workspace, fn () => Asset::with('tags')->sole());

        $this->assertSame('primary-logo', $asset->name);
        $this->assertSame(AssetKind::Logo, $asset->kind);
        $this->assertSame(AssetStatus::Draft, $asset->status);
        $this->assertSame($this->editor->user_id, $asset->uploaded_by);
        $this->assertSame(['Primary logo', 'ዋና ሎጎ'], $asset->tags->pluck('name')->sort()->values()->all());
        $this->assertSame([2400, 1200], [$asset->width, $asset->height]);

        // Stored under the workspace, never under the uploader's file name.
        $this->assertSame("workspaces/{$this->workspace->id}/assets/{$asset->id}/original.png", $asset->path);
        Storage::disk($asset->disk)->assertExists($asset->path);

        $this->assertSame(PreviewStatus::Ready, $asset->preview_status);
        $this->assertNotNull($asset->preview_path);
        $preview = Storage::disk($asset->disk)->get($asset->preview_path);
        $this->assertNotNull($preview);
        $size = getimagesizefromstring($preview);
        $this->assertNotFalse($size);
        $this->assertSame([800, 400, 'image/webp'], [$size[0], $size[1], $size['mime']]);

        $this->inWorkspace($this->workspace, fn () => $this->assertTrue(ActivityLog::where('action', 'asset.uploaded')->exists()));
    }

    public function test_an_svg_logo_is_its_own_preview_and_cannot_run_scripts()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><script>alert(1)</script><rect width="10" height="10"/></svg>';

        $this->actingAs($this->editor->user)
            ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                'file' => UploadedFile::fake()->createWithContent('mark.svg', $svg),
            ])
            ->assertSessionHasNoErrors();

        $asset = $this->inWorkspace($this->workspace, fn () => Asset::sole());
        $this->assertSame(PreviewStatus::Ready, $asset->preview_status);
        $this->assertSame($asset->path, $asset->preview_path);

        $response = $this->actingAs($this->editor->user)
            ->get(route('workspaces.assets.preview', [$this->workspace, $asset]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_fonts_and_documents_are_kept_without_a_preview()
    {
        $this->actingAs($this->editor->user)
            ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                'file' => UploadedFile::fake()->create('NotoSansEthiopic-Bold.ttf', 120, 'font/ttf'),
            ])
            ->assertSessionHasNoErrors();

        $asset = $this->inWorkspace($this->workspace, fn () => Asset::sole());

        $this->assertSame(AssetKind::Font, $asset->kind);
        $this->assertSame(PreviewStatus::None, $asset->preview_status);
        $this->assertNull($asset->preview_path);

        $this->actingAs($this->editor->user)
            ->get(route('workspaces.assets.preview', [$this->workspace, $asset]))
            ->assertNotFound();
    }

    public function test_the_uploader_can_choose_the_type()
    {
        $this->actingAs($this->editor->user)
            ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                'file' => UploadedFile::fake()->image('team.png'),
                'kind' => 'photo',
            ]);

        $this->inWorkspace($this->workspace, fn () => $this->assertSame(AssetKind::Photo, Asset::sole()->kind));
    }

    public function test_programs_and_web_pages_are_refused()
    {
        foreach (['setup.exe', 'page.html', 'script.js', 'run.php'] as $name) {
            $this->actingAs($this->editor->user)
                ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                    'file' => UploadedFile::fake()->create($name, 10),
                ])
                ->assertSessionHasErrors('file');
        }

        $this->inWorkspace($this->workspace, fn () => $this->assertSame(0, Asset::count()));
    }

    public function test_a_refused_file_says_which_types_are_accepted()
    {
        $this->actingAs($this->editor->user)
            ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                'file' => UploadedFile::fake()->create('setup.exe', 10),
            ])
            ->assertSessionHasErrors([
                'file' => 'This type of file can’t go in the library. It accepts '.strtoupper(implode(', ', config('assets.extensions'))).'.',
            ]);
    }

    public function test_files_over_the_limit_are_refused()
    {
        config(['assets.max_upload_megabytes' => 1]);

        $this->actingAs($this->editor->user)
            ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                'file' => UploadedFile::fake()->create('campaign.pdf', 2048, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_tags_are_tidied_and_shared_across_files()
    {
        foreach (['one.png', 'two.png'] as $name) {
            $this->actingAs($this->editor->user)
                ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                    'file' => UploadedFile::fake()->image($name),
                    'tags' => ['  Ramadan   2026 ', 'ramadan 2026', 'Logo'],
                ]);
        }

        $this->inWorkspace($this->workspace, function () {
            $this->assertSame(['Logo', 'Ramadan 2026'], Tag::orderBy('name')->pluck('name')->all());
            $this->assertSame(2, Tag::where('name', 'Logo')->sole()->assets()->count());
        });
    }

    public function test_viewers_cannot_upload_and_people_without_a_role_do_not_find_the_brand()
    {
        $viewer = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $viewer, BrandRoleName::Viewer);
        $stranger = $this->addMember($this->workspace);

        $this->actingAs($viewer->user)
            ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                'file' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertForbidden();

        $this->actingAs($stranger->user)
            ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                'file' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertNotFound();

        $this->inWorkspace($this->workspace, fn () => $this->assertSame(0, Asset::count()));
        $this->assertSame([], Storage::disk(config('filesystems.default'))->allFiles());
    }

    public function test_a_broken_image_is_still_kept_without_a_preview()
    {
        $this->actingAs($this->editor->user)
            ->post(route('workspaces.brands.assets.store', [$this->workspace, $this->brand]), [
                'file' => UploadedFile::fake()->createWithContent('broken.jpg', 'not really a jpeg'),
            ])
            ->assertSessionHasNoErrors();

        $asset = $this->inWorkspace($this->workspace, fn () => Asset::sole());
        $this->assertSame(PreviewStatus::Failed, $asset->preview_status);
        Storage::disk($asset->disk)->assertExists($asset->path);
    }
}
