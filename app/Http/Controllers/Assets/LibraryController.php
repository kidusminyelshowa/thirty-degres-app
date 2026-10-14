<?php

namespace App\Http\Controllers\Assets;

use App\Assets\AssetPresenter;
use App\Assets\BrandAccess;
use App\Assets\VisibleAssets;
use App\Enums\AssetKind;
use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The asset library: every file the person can see, across their brands,
 * searchable by name and tag. A brand's page is the same library, filtered
 * to that brand, with an upload button for its Editors.
 */
class LibraryController extends Controller
{
    public const PER_PAGE = 48;

    public function __construct(
        private BrandAccess $access,
        private VisibleAssets $visible,
        private AssetPresenter $presenter,
    ) {}

    public function index(Request $request, Workspace $workspace): Response
    {
        return $this->render($request, $workspace, null);
    }

    public function brand(Request $request, Workspace $workspace, Brand $brand): Response
    {
        abort_unless(Gate::allows('view', $brand), 404);

        return $this->render($request, $workspace, $brand);
    }

    private function render(Request $request, Workspace $workspace, ?Brand $brand): Response
    {
        $filters = [
            'q' => trim($request->string('q')->limit(100, '')->toString()),
            'tag' => trim($request->string('tag')->limit(40, '')->toString()),
            'kind' => AssetKind::tryFrom($request->string('kind')->toString())?->value,
            'status' => AssetStatus::tryFrom($request->string('status')->toString())?->value,
            'brand' => $brand?->id,
        ];

        $roles = $this->access->roles();
        $canSeeArchived = in_array(BrandRoleName::Manager, $roles, true);

        $query = $this->visible->apply(Asset::query(), includeArchived: $filters['status'] === AssetStatus::Archived->value)
            ->with(['brand', 'tags'])
            ->when($brand !== null, fn (Builder $query) => $query->where('brand_id', $brand?->id))
            ->when($filters['kind'] !== null, fn (Builder $query) => $query->where('kind', $filters['kind']))
            ->when($filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['tag'] !== '', fn (Builder $query) => $query->whereHas(
                'tags',
                fn (Builder $query) => $query->whereRaw('lower(tags.name) = lower(?)', [$filters['tag']]),
            ))
            ->when($filters['q'] !== '', function (Builder $query) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '\\%_').'%';

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'ilike', $like)
                    ->orWhere('original_name', 'ilike', $like)
                    ->orWhereHas('tags', fn (Builder $query) => $query->where('tags.name', 'ilike', $like)));
            })
            // Ids are time-ordered, so this also sorts uploads from the same second.
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $assets = $query->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('assets/index', [
            'filters' => $filters,
            'assets' => [
                'data' => $assets->getCollection()->map(fn (Asset $asset) => $this->presenter->summary($workspace, $asset))->values(),
                'currentPage' => $assets->currentPage(),
                'lastPage' => $assets->lastPage(),
                'total' => $assets->total(),
                'nextPageUrl' => $assets->nextPageUrl(),
                'previousPageUrl' => $assets->previousPageUrl(),
            ],
            'brands' => $this->brandList($roles),
            'tags' => $this->popularTags($brand),
            'brand' => $brand !== null ? $this->brandDetails($brand) : null,
            'canSeeArchived' => $canSeeArchived,
            'canCreateBrand' => Gate::allows('create', Brand::class),
            'upload' => [
                'maxMegabytes' => config('assets.max_upload_megabytes'),
                'extensions' => config('assets.extensions'),
            ],
        ]);
    }

    /**
     * @param  array<string, BrandRoleName>  $roles
     * @return list<array{id: string, name: string, parentId: string|null, role: string}>
     */
    private function brandList(array $roles): array
    {
        return array_values(Brand::query()
            ->whereIn('id', array_keys($roles))
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'parentId' => $brand->parent_id,
                'role' => $roles[$brand->id]->value,
            ])
            ->all());
    }

    /**
     * The most used tags on files the person can see, to filter by.
     *
     * @return list<string>
     */
    private function popularTags(?Brand $brand): array
    {
        $visible = $this->visible->apply(Asset::query())
            ->when($brand !== null, fn (Builder $query) => $query->where('brand_id', $brand?->id))
            ->select('assets.id');

        return array_values(DB::table('tags')
            ->join('asset_tag', 'asset_tag.tag_id', '=', 'tags.id')
            ->whereIn('asset_tag.asset_id', $visible)
            ->groupBy('tags.id', 'tags.name')
            ->orderByRaw('count(*) desc')
            ->orderBy('tags.name')
            ->limit(30)
            ->pluck('tags.name')
            ->map(fn (mixed $name) => (string) $name)
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function brandDetails(Brand $brand): array
    {
        $brand->loadMissing('parent');

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'parent' => $brand->parent !== null && $this->access->roleOn($brand->parent) !== null
                ? ['id' => $brand->parent->id, 'name' => $brand->parent->name]
                : null,
            'children' => $brand->children()
                ->orderBy('name')
                ->get()
                ->filter(fn (Brand $child) => $this->access->roleOn($child) !== null)
                ->map(fn (Brand $child) => ['id' => $child->id, 'name' => $child->name])
                ->values(),
            'role' => $this->access->roleOn($brand)?->value,
            'canUpload' => Gate::allows('upload', $brand),
            'canRename' => Gate::allows('update', $brand),
            'canManageAccess' => Gate::allows('manageAccess', $brand),
            'canAddSubBrand' => $brand->parent_id === null && Gate::allows('create', Brand::class),
        ];
    }
}
