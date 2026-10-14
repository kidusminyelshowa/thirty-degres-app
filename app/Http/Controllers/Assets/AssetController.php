<?php

namespace App\Http\Controllers\Assets;

use App\Actions\Assets\UploadAsset;
use App\Assets\AssetPresenter;
use App\Enums\AssetKind;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function __construct(private AssetPresenter $presenter) {}

    /**
     * Upload one file. The screen sends several files one after another, so
     * a slow connection only ever retries the file that failed.
     */
    public function store(Request $request, Workspace $workspace, Brand $brand, UploadAsset $upload): RedirectResponse
    {
        abort_unless(Gate::allows('view', $brand), 404);
        Gate::authorize('upload', $brand);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.(config('assets.max_upload_megabytes') * 1024),
                'extensions:'.implode(',', config('assets.extensions')),
            ],
            'kind' => ['nullable', Rule::enum(AssetKind::class)],
            ...$this->tagRules(),
        ], [
            'file.extensions' => __('This type of file cannot be added to the library.'),
        ]);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $file */
        $file = $validated['file'];

        $upload->handle(
            $brand,
            $file,
            $user,
            AssetPresenter::cleanTags($validated['tags'] ?? []),
            isset($validated['kind']) ? AssetKind::from($validated['kind']) : null,
        );

        return back();
    }

    public function show(Workspace $workspace, Asset $asset): Response
    {
        abort_unless(Gate::allows('view', $asset), 404);

        $asset->load(['brand', 'tags', 'uploader', 'approver']);
        $approval = Gate::inspect('approve', $asset);

        return Inertia::render('assets/show', [
            'asset' => [
                ...$this->presenter->summary($workspace, $asset),
                'originalName' => $asset->original_name,
                'width' => $asset->width,
                'height' => $asset->height,
                'previewStatus' => $asset->preview_status->value,
                'uploadedBy' => $asset->uploader?->name,
                'approvedBy' => $asset->approver?->name,
                'approvedAt' => $asset->approved_at?->toIso8601String(),
                'downloadUrl' => route('workspaces.assets.download', [$workspace, $asset]),
            ],
            'can' => [
                'update' => Gate::allows('update', $asset),
                'approve' => $approval->allowed(),
                'approveReason' => $asset->isDraft() && $approval->denied() ? $approval->message() : null,
                'archive' => Gate::allows('archive', $asset),
                'restore' => Gate::allows('restore', $asset),
                'delete' => Gate::allows('delete', $asset),
            ],
            'tagSuggestions' => Tag::query()->orderBy('name')->limit(200)->pluck('name'),
        ]);
    }

    public function update(Request $request, Workspace $workspace, Asset $asset): RedirectResponse
    {
        abort_unless(Gate::allows('view', $asset), 404);
        Gate::authorize('update', $asset);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::enum(AssetKind::class)],
            ...$this->tagRules(),
        ]);

        DB::transaction(function () use ($asset, $validated) {
            $asset->update([
                'name' => $validated['name'],
                'kind' => AssetKind::from($validated['kind']),
            ]);
            $asset->syncTagNames(AssetPresenter::cleanTags($validated['tags'] ?? []));
            $asset->touch();

            ActivityLog::record('asset.updated', $asset, ['name' => $asset->name]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return to_route('workspaces.assets.show', [$workspace, $asset]);
    }

    public function destroy(Workspace $workspace, Asset $asset): RedirectResponse
    {
        abort_unless(Gate::allows('view', $asset), 404);
        Gate::authorize('delete', $asset);

        $brand = $asset->brand;
        $paths = array_values(array_unique(array_filter([$asset->path, $asset->preview_path])));

        DB::transaction(function () use ($asset) {
            $asset->delete();
            ActivityLog::record('asset.deleted', $asset, ['name' => $asset->name, 'brand_id' => $asset->brand_id]);
        });

        Storage::disk($asset->disk)->delete($paths);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft deleted.')]);

        return to_route('workspaces.brands.show', [$workspace, $brand]);
    }

    /**
     * The original file, exactly as uploaded. Every download is logged.
     */
    public function download(Workspace $workspace, Asset $asset): StreamedResponse
    {
        abort_unless(Gate::allows('view', $asset), 404);

        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->path), 404);

        ActivityLog::record('asset.downloaded', $asset, ['name' => $asset->name]);

        return $disk->download($asset->path, $asset->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The light preview pages show in place of the original.
     */
    public function preview(Workspace $workspace, Asset $asset): StreamedResponse
    {
        abort_unless(Gate::allows('view', $asset) && $asset->preview_path !== null, 404);

        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->preview_path), 404);

        $isSvg = $asset->preview_path === $asset->path && $asset->extension === 'svg';

        return $disk->response($asset->preview_path, null, [
            'Content-Type' => $isSvg ? 'image/svg+xml' : 'image/webp',
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
            // An SVG can carry scripts. Opened on its own, it must not run them.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox",
        ]);
    }

    /**
     * A font file, so guideline pages can show type in the brand's own
     * typeface. Partners' use is logged like a download.
     */
    public function font(Workspace $workspace, Asset $asset): StreamedResponse
    {
        abort_unless(Gate::allows('view', $asset), 404);

        $type = match ($asset->extension) {
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            default => null,
        };
        abort_if($type === null, 404);

        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->path), 404);

        if (app(Tenancy::class)->membership()?->isPartner() ?? false) {
            ActivityLog::record('asset.font_loaded', $asset, ['name' => $asset->name]);
        }

        return $disk->response($asset->path, null, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function tagRules(): array
    {
        return [
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:'.Tag::MAX_LENGTH],
        ];
    }
}
