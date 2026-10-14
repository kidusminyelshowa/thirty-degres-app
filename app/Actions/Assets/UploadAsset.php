<?php

namespace App\Actions\Assets;

use App\Assets\PreviewGenerator;
use App\Enums\AssetKind;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Adds one file to a brand's library as a draft, and makes its preview.
 */
class UploadAsset
{
    public function __construct(private PreviewGenerator $previews) {}

    /**
     * @param  list<string>  $tags
     */
    public function handle(Brand $brand, UploadedFile $file, User $uploader, array $tags = [], ?AssetKind $kind = null): Asset
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = Str::limit($file->getClientOriginalName(), 250, '');
        $disk = (string) config('filesystems.default');

        $asset = new Asset([
            'brand_id' => $brand->id,
            'name' => Str::limit(pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName, 200, ''),
            'kind' => $kind ?? AssetKind::guess($extension),
            'original_name' => $originalName,
            'extension' => $extension,
            'mime_type' => Str::limit($file->getMimeType() ?? 'application/octet-stream', 250, ''),
            'size' => $file->getSize() ?: 0,
            'disk' => $disk,
            'uploaded_by' => $uploader->id,
        ]);
        $asset->id = $asset->newUniqueId();
        $asset->workspace_id = $brand->workspace_id;
        $asset->path = Asset::storagePathFor($brand->workspace_id, $asset->id, $extension);

        $stream = fopen($file->getRealPath(), 'r');

        if ($stream === false || ! Storage::disk($disk)->writeStream($asset->path, $stream)) {
            throw new RuntimeException('The file could not be stored.');
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        try {
            DB::transaction(function () use ($asset, $tags, $uploader) {
                $asset->save();
                $asset->syncTagNames($tags);

                ActivityLog::record('asset.uploaded', $asset, [
                    'name' => $asset->name,
                    'brand_id' => $asset->brand_id,
                    'size' => $asset->size,
                ], $uploader);
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($asset->path);

            throw $e;
        }

        $this->previews->generate($asset, $file->getRealPath());

        return $asset;
    }
}
