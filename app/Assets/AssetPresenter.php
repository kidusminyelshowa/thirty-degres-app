<?php

namespace App\Assets;

use App\Models\Asset;
use App\Models\Tag;
use App\Models\Workspace;

/**
 * The shape of a file sent to the screens.
 */
class AssetPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Workspace $workspace, Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'kind' => $asset->kind->value,
            'status' => $asset->status->value,
            'extension' => $asset->extension,
            'size' => $asset->size,
            'previewUrl' => $this->previewUrl($workspace, $asset),
            'brand' => ['id' => $asset->brand->id, 'name' => $asset->brand->name],
            'tags' => $asset->tags->map(fn (Tag $tag) => $tag->name)->values()->all(),
            'createdAt' => $asset->created_at?->toIso8601String(),
        ];
    }

    public function previewUrl(Workspace $workspace, Asset $asset): ?string
    {
        if ($asset->preview_path === null) {
            return null;
        }

        // The version in the address lets browsers keep the preview cached
        // until the file changes, which saves data on slow connections.
        return route('workspaces.assets.preview', [
            'workspace' => $workspace,
            'asset' => $asset,
            'v' => $asset->updated_at?->getTimestamp(),
        ]);
    }

    /**
     * Turns what people type into a tidy list: trimmed, single spaces, no
     * repeats whatever the capitals.
     *
     * @param  array<mixed>  $input
     * @return list<string>
     */
    public static function cleanTags(array $input): array
    {
        $tags = [];

        foreach ($input as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $tag = trim((string) preg_replace('/\s+/u', ' ', $tag));

            if ($tag !== '') {
                $tags[mb_strtolower($tag)] ??= $tag;
            }
        }

        return array_values($tags);
    }
}
