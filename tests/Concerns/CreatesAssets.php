<?php

namespace Tests\Concerns;

use App\Actions\Assets\UploadAsset;
use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\BrandRole;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;

/**
 * Brands, brand roles and files for tests, written from inside their
 * workspace like the app does. Use with CreatesWorkspaces and Storage::fake().
 */
trait CreatesAssets
{
    protected function createBrand(Workspace $workspace, string $name = 'Yirgacheffe Gold', ?Brand $parent = null): Brand
    {
        return $this->inWorkspace($workspace, fn () => Brand::create([
            'name' => $name,
            'parent_id' => $parent?->id,
        ]));
    }

    protected function giveBrandRole(Brand $brand, Membership $membership, BrandRoleName $role): BrandRole
    {
        return $this->inWorkspace($this->workspaceOf($brand), fn () => BrandRole::create([
            'brand_id' => $brand->id,
            'membership_id' => $membership->id,
            'role' => $role,
        ]));
    }

    /**
     * @param  list<string>  $tags
     */
    protected function uploadAsset(
        Brand $brand,
        ?User $uploader = null,
        ?UploadedFile $file = null,
        array $tags = [],
        AssetStatus $status = AssetStatus::Draft,
        ?User $approver = null,
    ): Asset {
        $workspace = $this->workspaceOf($brand);
        $uploader ??= $this->ownerOf($workspace);
        $file ??= UploadedFile::fake()->image('primary-logo.png', 1200, 600);

        return $this->inWorkspace($workspace, function () use ($brand, $uploader, $file, $tags, $status, $approver) {
            $asset = app(UploadAsset::class)->handle($brand, $file, $uploader, $tags);

            if ($status !== AssetStatus::Draft) {
                $asset->status = $status;
                $asset->approved_by = ($approver ?? User::factory()->create())->id;
                $asset->approved_at = now();
                $asset->save();
            }

            return $asset;
        });
    }

    protected function workspaceOf(Brand $brand): Workspace
    {
        $workspace = new Workspace;
        $workspace->id = $brand->workspace_id;
        $workspace->exists = true;

        return $this->inWorkspace($workspace, fn () => Workspace::query()->findOrFail($brand->workspace_id));
    }
}
