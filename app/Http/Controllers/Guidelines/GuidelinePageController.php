<?php

namespace App\Http\Controllers\Guidelines;

use App\Assets\BrandAccess;
use App\Assets\VisibleAssets;
use App\Enums\AssetStatus;
use App\Guidelines\GuidelineBlocks;
use App\Guidelines\GuidelinePresenter;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\GuidelinePage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A brand's guideline pages: reading them, writing them and publishing them.
 */
class GuidelinePageController extends Controller
{
    public function __construct(
        private BrandAccess $access,
        private VisibleAssets $visible,
        private GuidelinePresenter $presenter,
    ) {}

    /**
     * Every brand the person can see, with its guideline pages.
     */
    public function index(Workspace $workspace): Response
    {
        $brands = Brand::query()
            ->whereIn('id', array_keys($this->access->roles()))
            ->orderBy('name')
            ->get();

        return Inertia::render('guidelines/index', [
            'brands' => $brands->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'parentId' => $brand->parent_id,
                'pages' => $this->presenter->pageList($brand),
                'canCreate' => Gate::allows('create', [GuidelinePage::class, $brand]),
            ])->values(),
            'templates' => GuidelineBlocks::TEMPLATES,
        ]);
    }

    /**
     * A brand's guidelines open on their first page.
     */
    public function brand(Workspace $workspace, Brand $brand): RedirectResponse|Response
    {
        abort_unless(Gate::allows('view', $brand), 404);

        $pages = $this->presenter->pageList($brand);

        if ($pages !== []) {
            return to_route('workspaces.guidelines.show', [$workspace, $pages[0]['id']]);
        }

        return Inertia::render('guidelines/empty', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'canCreate' => Gate::allows('create', [GuidelinePage::class, $brand]),
            'templates' => GuidelineBlocks::TEMPLATES,
        ]);
    }

    public function store(Request $request, Workspace $workspace, Brand $brand): RedirectResponse
    {
        abort_unless(Gate::allows('view', $brand), 404);
        Gate::authorize('create', [GuidelinePage::class, $brand]);

        $validated = $request->validate([
            'template' => ['required', Rule::in(GuidelineBlocks::TEMPLATES)],
        ]);

        /** @var User $user */
        $user = $request->user();
        $template = GuidelineBlocks::template($validated['template']);

        $page = DB::transaction(function () use ($brand, $user, $template) {
            $position = GuidelinePage::query()->where('brand_id', $brand->id)->max('position');

            $page = GuidelinePage::create([
                'brand_id' => $brand->id,
                'position' => is_numeric($position) ? (int) $position + 1 : 0,
                'title_en' => $template['title_en'],
                'title_am' => $template['title_am'],
                'blocks' => $template['blocks'],
                'created_by' => $user->id,
                'edited_by' => $user->id,
                'edited_at' => now(),
            ]);

            ActivityLog::record('guideline.created', $page, ['title' => $page->title_en, 'brand_id' => $brand->id]);

            return $page;
        });

        return to_route('workspaces.guidelines.edit', [$workspace, $page]);
    }

    /**
     * The page as Viewers see it: the published copy, or the working copy
     * marked as a draft if it has never been published.
     */
    public function show(Workspace $workspace, GuidelinePage $page): Response
    {
        abort_unless(Gate::allows('view', $page), 404);

        $page->load(['brand', 'publisher']);
        $working = ! $page->isPublished();
        $blocks = $working ? $page->blocks : ($page->published_blocks ?? []);

        return Inertia::render('guidelines/show', [
            ...$this->pageProps($workspace, $page, $working, $blocks),
            'publishedBy' => $page->publisher?->name,
            'publishedAt' => $page->published_at?->toIso8601String(),
            'can' => $this->abilities($page),
        ]);
    }

    public function edit(Workspace $workspace, GuidelinePage $page): Response
    {
        abort_unless(Gate::allows('view', $page), 404);
        Gate::authorize('update', $page);

        $page->load(['brand', 'editor']);
        $brandIds = GuidelinePresenter::brandIdsFor($page->brand);

        // Files from this brand (and its parent) the editor can put on the page.
        $library = $this->visible->apply(Asset::query())
            ->whereIn('brand_id', $brandIds)
            ->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->map(fn (Asset $asset) => $this->presenter->asset($workspace, $asset))
            ->values();

        return Inertia::render('guidelines/edit', [
            ...$this->pageProps($workspace, $page, true, $page->blocks),
            'library' => $library,
            'editedBy' => $page->editor?->name,
            'editedAt' => $page->edited_at?->toIso8601String(),
            'can' => $this->abilities($page),
        ]);
    }

    public function update(Request $request, Workspace $workspace, GuidelinePage $page): RedirectResponse
    {
        abort_unless(Gate::allows('view', $page), 404);
        Gate::authorize('update', $page);

        $validated = $request->validate([
            'title_en' => ['present', 'nullable', 'string', 'max:120'],
            'title_am' => ['present', 'nullable', 'string', 'max:120'],
        ]);

        $titleEn = trim((string) ($validated['title_en'] ?? ''));
        $titleAm = trim((string) ($validated['title_am'] ?? ''));

        if ($titleEn === '' && $titleAm === '') {
            throw ValidationException::withMessages(['title_en' => __('Give the page a title in English or Amharic.')]);
        }

        $blocks = GuidelineBlocks::validate($request->all());
        $page->loadMissing('brand');
        $this->ensureFilesCanBeUsed($page->brand, $blocks);

        /** @var User $user */
        $user = $request->user();

        $page->update([
            'title_en' => $titleEn,
            'title_am' => $titleAm,
            'blocks' => $blocks,
            'edited_by' => $user->id,
            'edited_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return to_route('workspaces.guidelines.edit', [$workspace, $page]);
    }

    public function publish(Request $request, Workspace $workspace, GuidelinePage $page): RedirectResponse
    {
        abort_unless(Gate::allows('view', $page), 404);
        Gate::authorize('publish', $page);

        $this->ensureFilesAreApproved($page);

        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($page, $user) {
            $page->published_title_en = $page->title_en;
            $page->published_title_am = $page->title_am;
            $page->published_blocks = $page->blocks;
            $page->published_by = $user->id;
            $page->published_at = now();
            $page->save();

            ActivityLog::record('guideline.published', $page, [
                'title' => $page->title_en,
                'self_approved' => $page->edited_by === $user->id,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Published. Everyone on this brand now sees this version.')]);

        return to_route('workspaces.guidelines.show', [$workspace, $page]);
    }

    /**
     * Move a page one place up or down in the brand's list.
     */
    public function move(Request $request, Workspace $workspace, GuidelinePage $page): RedirectResponse
    {
        abort_unless(Gate::allows('view', $page), 404);
        $page->loadMissing('brand');
        Gate::authorize('reorder', [GuidelinePage::class, $page->brand]);

        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        DB::transaction(function () use ($page, $validated) {
            $pages = GuidelinePage::query()
                ->where('brand_id', $page->brand_id)
                ->orderBy('position')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get()
                ->values();

            $from = $pages->search(fn (GuidelinePage $candidate) => $candidate->id === $page->id);
            $to = is_int($from) ? $from + ($validated['direction'] === 'up' ? -1 : 1) : -1;

            if (! is_int($from) || $to < 0 || $to >= $pages->count()) {
                return;
            }

            $ordered = $pages->all();
            [$ordered[$from], $ordered[$to]] = [$ordered[$to], $ordered[$from]];

            foreach (array_values($ordered) as $position => $candidate) {
                if ($candidate->position !== $position) {
                    // Order is not content: it leaves "last edited by" alone.
                    GuidelinePage::query()->whereKey($candidate->id)->update(['position' => $position]);
                }
            }
        });

        return back();
    }

    public function destroy(Workspace $workspace, GuidelinePage $page): RedirectResponse
    {
        abort_unless(Gate::allows('view', $page), 404);
        Gate::authorize('delete', $page);

        $brand = $page->brand;

        DB::transaction(function () use ($page) {
            $page->delete();
            ActivityLog::record('guideline.deleted', $page, ['title' => $page->title_en, 'brand_id' => $page->brand_id]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page deleted.')]);

        return to_route('workspaces.brands.guidelines.index', [$workspace, $brand]);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function pageProps(Workspace $workspace, GuidelinePage $page, bool $working, array $blocks): array
    {
        $brand = $page->brand;

        return [
            'page' => [
                'id' => $page->id,
                'title' => $this->presenter->title($page, $working),
                'blocks' => $blocks,
                'published' => $page->isPublished(),
                'hasChanges' => Gate::allows('update', $page) && $page->hasUnpublishedChanges(),
            ],
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'pages' => $this->presenter->pageList($brand),
            'assets' => $this->presenter->assetsFor($workspace, $brand, $blocks),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function abilities(GuidelinePage $page): array
    {
        $publish = Gate::inspect('publish', $page);

        return [
            'update' => Gate::allows('update', $page),
            'publish' => $publish->allowed(),
            'publishReason' => $page->hasUnpublishedChanges() && $publish->denied() ? $publish->message() : null,
            'reorder' => Gate::allows('reorder', [GuidelinePage::class, $page->brand]),
            'delete' => Gate::allows('delete', $page),
            'create' => Gate::allows('create', [GuidelinePage::class, $page->brand]),
        ];
    }

    /**
     * A page may only show files from its brand (or the parent brand) that
     * the editor can see, and typefaces must be font files.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function ensureFilesCanBeUsed(Brand $brand, array $blocks): void
    {
        $ids = GuidelineBlocks::assetIds($blocks);

        if ($ids === []) {
            return;
        }

        $usable = $this->visible->apply(Asset::query())
            ->whereIn('id', $ids)
            ->whereIn('brand_id', GuidelinePresenter::brandIdsFor($brand))
            ->get()
            ->keyBy('id');

        if ($usable->count() !== count($ids)) {
            throw ValidationException::withMessages(['blocks' => __('Some files on this page are not in this brand’s library.')]);
        }

        foreach (GuidelineBlocks::fontAssetIds($blocks) as $id) {
            $asset = $usable->get($id);

            if (! $asset instanceof Asset || ! in_array($asset->extension, GuidelinePresenter::FONT_EXTENSIONS, true)) {
                throw ValidationException::withMessages(['blocks' => __('Typefaces must be font files (WOFF2, WOFF, TTF or OTF).')]);
            }
        }
    }

    /**
     * Viewers only see approved files, so a page can only go live once every
     * file on it is approved.
     */
    private function ensureFilesAreApproved(GuidelinePage $page): void
    {
        $ids = GuidelineBlocks::assetIds($page->blocks);

        if ($ids === []) {
            return;
        }

        $pending = Asset::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', AssetStatus::Approved)
            ->orderBy('name')
            ->pluck('name');

        $missing = count($ids) - Asset::query()->whereIn('id', $ids)->count();

        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages(['publish' => __('Approve these files before publishing: :files', ['files' => $pending->implode(', ')])]);
        }

        if ($missing > 0) {
            throw ValidationException::withMessages(['publish' => __('Some files on this page were deleted. Remove them before publishing.')]);
        }
    }
}
