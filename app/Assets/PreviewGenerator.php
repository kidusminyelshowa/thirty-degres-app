<?php

namespace App\Assets;

use App\Enums\PreviewStatus;
use App\Models\Asset;
use GdImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Makes the light preview pages show instead of the original (CLAUDE.md
 * rule 6): a WebP at most 800 pixels on its longest side, usually tens of
 * kilobytes where the original may be many megabytes.
 *
 * SVG logos are already light, so they are their own preview. Fonts,
 * documents and design files get an icon on screen instead.
 */
class PreviewGenerator
{
    public const MAX_SIDE = 800;

    /** Larger images are skipped rather than risk running out of memory. */
    public const MAX_PIXELS = 50_000_000;

    private const RASTER = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @param  string  $localPath  The uploaded file on this server, before or after it was stored.
     */
    public function generate(Asset $asset, string $localPath): void
    {
        $extension = strtolower($asset->extension);

        if ($extension === 'svg') {
            $asset->preview_status = PreviewStatus::Ready;
            $asset->preview_path = $asset->path;
            $asset->save();

            return;
        }

        if (! in_array($extension, self::RASTER, true)) {
            return;
        }

        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $this->generateRaster($asset, $localPath);
        } catch (Throwable $e) {
            Log::warning('Could not make a preview', ['asset' => $asset->id, 'error' => $e->getMessage()]);

            $asset->preview_status = PreviewStatus::Failed;
            $asset->save();
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    private function generateRaster(Asset $asset, string $localPath): void
    {
        $size = @getimagesize($localPath);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            $asset->preview_status = PreviewStatus::Failed;
            $asset->save();

            return;
        }

        [$width, $height] = $size;
        $asset->width = $width;
        $asset->height = $height;

        if ($width * $height > self::MAX_PIXELS) {
            $asset->preview_status = PreviewStatus::None;
            $asset->save();

            return;
        }

        $image = $this->open($localPath, $size[2]);

        if ($image === null) {
            $asset->preview_status = PreviewStatus::Failed;
            $asset->save();

            return;
        }

        $image = $this->applyOrientation($image, $localPath, $size[2]);
        $preview = $this->shrink($image);

        $temporary = tempnam(sys_get_temp_dir(), 'preview');

        if ($temporary === false || ! imagewebp($preview, $temporary, 80)) {
            throw new \RuntimeException('Could not encode the preview.');
        }

        try {
            $path = Asset::previewPathFor($asset->workspace_id, $asset->id);
            $stream = fopen($temporary, 'r');

            if ($stream === false || ! Storage::disk($asset->disk)->writeStream($path, $stream)) {
                throw new \RuntimeException('Could not store the preview.');
            }

            if (is_resource($stream)) {
                fclose($stream);
            }

            $asset->preview_path = $path;
            $asset->preview_status = PreviewStatus::Ready;
            $asset->save();
        } finally {
            @unlink($temporary);
        }
    }

    private function open(string $path, int $type): ?GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };

        return $image instanceof GdImage ? $image : null;
    }

    /**
     * Phone photos are often stored sideways with a note saying which way is up.
     */
    private function applyOrientation(GdImage $image, string $path, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * Fit within MAX_SIDE, keeping transparency (logos on clear backgrounds).
     */
    private function shrink(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $preview = imagecreatetruecolor($newWidth, $newHeight);

        if ($preview === false) {
            throw new \RuntimeException('Could not create the preview.');
        }

        imagealphablending($preview, false);
        imagesavealpha($preview, true);
        $transparent = imagecolorallocatealpha($preview, 0, 0, 0, 127);

        if ($transparent !== false) {
            imagefill($preview, 0, 0, $transparent);
        }

        imagecopyresampled($preview, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $preview;
    }
}
