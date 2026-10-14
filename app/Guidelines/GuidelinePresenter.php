<?php

namespace App\Guidelines;

use App\Assets\AssetPresenter;
use App\Assets\VisibleAssets;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\GuidelinePage;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;

/**
 * The shape of guideline pages sent to the screens.
 */
class GuidelinePresenter
{
    public const FONT_EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf'];

    public function __construct(private VisibleAssets $visible, private AssetPresenter $assets) {}

    /**
     * The brand's pages the person can open, in order, for the page list.
     *
     * @return list<array{id: string, title: array{en: string, am: string}, published: bool, hasChanges: bool}>
     */
    public function pageList(Brand $brand): array
    {
        return array_values(GuidelinePage::query()
            ->where('brand_id', $brand->id)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->filter(fn (GuidelinePage $page) => Gate::allows('view', $page))
            ->map(function (GuidelinePage $page) {
                $working = Gate::allows('update', $page);

                return [
                    'id' => $page->id,
                    'title' => $this->title($page, $working || ! $page->isPublished()),
                    'published' => $page->isPublished(),
                    'hasChanges' => $working && $page->hasUnpublishedChanges(),
                ];
            })
            ->all());
    }

    /**
     * @return array{en: string, am: string}
     */
    public function title(GuidelinePage $page, bool $working): array
    {
        return $working
            ? ['en' => $page->title_en, 'am' => $page->title_am]
            : ['en' => (string) $page->published_title_en, 'am' => (string) $page->published_title_am];
    }

    /**
     * The library files these blocks point at, that this person may see.
     * Anything else is left out, and the page shows an empty frame instead.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, array<string, mixed>>
     */
    public function assetsFor(Workspace $workspace, Brand $brand, array $blocks): array
    {
        $ids = GuidelineBlocks::assetIds($blocks);

        if ($ids === []) {
            return [];
        }

        return $this->visible->apply(Asset::query())
            ->whereIn('id', $ids)
            ->whereIn('brand_id', self::brandIdsFor($brand))
            ->get()
            ->mapWithKeys(fn (Asset $asset) => [$asset->id => $this->asset($workspace, $asset)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function asset(Workspace $workspace, Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'kind' => $asset->kind->value,
            'status' => $asset->status->value,
            'extension' => $asset->extension,
            'previewUrl' => $this->assets->previewUrl($workspace, $asset),
            'fontUrl' => in_array($asset->extension, self::FONT_EXTENSIONS, true)
                ? route('workspaces.assets.font', [$workspace, $asset, 'v' => $asset->updated_at?->getTimestamp()])
                : null,
        ];
    }

    /**
     * The brand and its parent, whose files a page may use.
     *
     * @return list<string>
     */
    public static function brandIdsFor(Brand $brand): array
    {
        return array_values(array_filter([$brand->id, $brand->parent_id]));
    }
}
