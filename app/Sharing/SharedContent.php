<?php

namespace App\Sharing;

use App\Enums\AssetStatus;
use App\Enums\ShareLinkKind;
use App\Guidelines\GuidelineBlocks;
use App\Guidelines\GuidelinePresenter;
use App\Models\Asset;
use App\Models\GuidelinePage;
use App\Models\ShareLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What a share link shows, and nothing more: only approved files and only
 * published guideline pages, as they are now. Archiving a file or taking a
 * page down stops the link serving it.
 *
 * Nobody is signed in, so the role-based helpers (VisibleAssets, policies)
 * would show nothing; these queries are the link's own rules instead, and
 * still run under the workspace lock.
 */
class SharedContent
{
    /**
     * Published pages of the link's brand, in order. Files links have none.
     *
     * @return Collection<int, GuidelinePage>
     */
    public function pages(ShareLink $link): Collection
    {
        if ($link->kind !== ShareLinkKind::Guidelines) {
            return new Collection;
        }

        return GuidelinePage::query()
            ->where('brand_id', $link->brand_id)
            ->whereNotNull('published_at')
            ->orderBy('position')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * The approved files the link may serve: the kit's files, or the ones
     * the published pages show.
     *
     * @return Builder<Asset>
     */
    public function assets(ShareLink $link): Builder
    {
        $query = Asset::query()->where('status', AssetStatus::Approved);

        if ($link->kind === ShareLinkKind::Files) {
            return $query
                ->where('brand_id', $link->brand_id)
                ->whereIn('id', $link->assets()->select('assets.id'));
        }

        $link->loadMissing('brand');
        $ids = $this->pages($link)
            ->flatMap(fn (GuidelinePage $page) => GuidelineBlocks::assetIds($page->published_blocks ?? []))
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return $query->whereRaw('false');
        }

        return $query
            ->whereIn('brand_id', GuidelinePresenter::brandIdsFor($link->brand))
            ->whereIn('id', $ids);
    }

    /**
     * The shape of a file on a share page. Downloads and typefaces are the
     * original files, so they are only offered when the link allows them.
     *
     * @return array<string, mixed>
     */
    public function asset(string $token, ShareLink $link, Asset $asset): array
    {
        $version = $asset->updated_at?->getTimestamp();

        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'kind' => $asset->kind->value,
            'status' => $asset->status->value,
            'extension' => $asset->extension,
            'size' => $asset->size,
            'previewUrl' => $asset->preview_path !== null
                ? route('share.preview', ['token' => $token, 'asset' => $asset->id, 'v' => $version])
                : null,
            'fontUrl' => $link->allow_originals && in_array($asset->extension, GuidelinePresenter::FONT_EXTENSIONS, true)
                ? route('share.font', ['token' => $token, 'asset' => $asset->id, 'v' => $version])
                : null,
            'downloadUrl' => $link->allow_originals
                ? route('share.download', ['token' => $token, 'asset' => $asset->id])
                : null,
        ];
    }
}
