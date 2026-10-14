<?php

namespace Tests\Feature\Assets;

use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Membership;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class AssetDownloadTest extends TestCase
{
    use CreatesAssets, CreatesWorkspaces, RefreshDatabase;

    private Workspace $workspace;

    private Brand $brand;

    private Membership $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $this->workspace = $this->createWorkspace('Ethio Coffee');
        $this->brand = $this->createBrand($this->workspace);
        $this->viewer = $this->addMember($this->workspace);
        $this->giveBrandRole($this->brand, $this->viewer, BrandRoleName::Viewer);
    }

    public function test_a_viewer_downloads_the_original_file_and_it_is_logged()
    {
        $content = '%PDF-1.4 guideline';
        $asset = $this->uploadAsset(
            $this->brand,
            file: UploadedFile::fake()->createWithContent('Brand guidelines ብራንድ.pdf', $content),
            status: AssetStatus::Approved,
        );

        $response = $this->actingAs($this->viewer->user)
            ->get(route('workspaces.assets.download', [$this->workspace, $asset]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // Saved under its original name, Ge'ez included.
        $this->assertStringContainsString(
            "filename*=utf-8''".rawurlencode('Brand guidelines ብራንድ.pdf'),
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));

        $this->assertSame($content, $response->streamedContent());

        $this->inWorkspace($this->workspace, function () {
            $log = ActivityLog::where('action', 'asset.downloaded')->sole();
            $this->assertSame($this->viewer->user_id, $log->actor_id);
        });
    }

    public function test_a_viewer_sees_the_light_preview_of_an_approved_image()
    {
        $asset = $this->uploadAsset($this->brand, status: AssetStatus::Approved);

        $this->actingAs($this->viewer->user)
            ->get(route('workspaces.assets.preview', [$this->workspace, $asset]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
    }

    public function test_drafts_cannot_be_downloaded_or_previewed_by_viewers()
    {
        $draft = $this->uploadAsset($this->brand);

        $this->actingAs($this->viewer->user)->get(route('workspaces.assets.download', [$this->workspace, $draft]))->assertNotFound();
        $this->actingAs($this->viewer->user)->get(route('workspaces.assets.preview', [$this->workspace, $draft]))->assertNotFound();

        $this->inWorkspace($this->workspace, fn () => $this->assertFalse(ActivityLog::where('action', 'asset.downloaded')->exists()));
    }
}
