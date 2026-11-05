<?php

namespace App\Http\Controllers\Sharing;

use App\Assets\AssetPresenter;
use App\Enums\AssetStatus;
use App\Enums\ShareLinkKind;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\GuidelinePage;
use App\Models\ShareLink;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A brand's share links: making them, seeing who opened them, revoking them.
 * Editors and above who are staff; partners never share (THI-38).
 */
class ShareLinkController extends Controller
{
    public function __construct(private AssetPresenter $presenter) {}

    public function index(Request $request, Workspace $workspace, Brand $brand): Response
    {
        abort_unless(Gate::allows('view', $brand), 404);
        Gate::authorize('viewAny', [ShareLink::class, $brand]);

        $links = ShareLink::query()
            ->where('brand_id', $brand->id)
            ->with('creator')
            ->withCount('assets')
            ->latest()
            ->get();

        $counts = ActivityLog::query()
            ->toBase()
            ->where('subject_type', (new ShareLink)->getMorphClass())
            ->whereIn('subject_id', $links->pluck('id'))
            ->whereIn('action', ['share_link.opened', 'share_link.downloaded'])
            ->selectRaw('subject_id, action, count(*) as total')
            ->groupBy('subject_id', 'action')
            ->get()
            ->mapWithKeys(fn (object $row) => ["{$row->subject_id}.{$row->action}" => (int) $row->total]);

        $files = Asset::query()
            ->where('brand_id', $brand->id)
            ->where('status', AssetStatus::Approved)
            ->with(['brand', 'tags'])
            ->orderBy('name')
            ->limit(ShareLink::MAX_FILES)
            ->get();

        return Inertia::render('brands/share-links', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'links' => $links->map(fn (ShareLink $link) => [
                'id' => $link->id,
                'name' => $link->name,
                'kind' => $link->kind->value,
                'files' => $link->assets_count,
                'expiresAt' => $link->expires_at->toIso8601String(),
                'revokedAt' => $link->revoked_at?->toIso8601String(),
                'isLive' => $link->isLive(),
                'hasPassword' => $link->hasPassword(),
                'allowOriginals' => $link->allow_originals,
                'createdBy' => $link->creator?->name,
                'createdAt' => $link->created_at?->toIso8601String(),
                'lastOpenedAt' => $link->last_opened_at?->toIso8601String(),
                'opens' => $counts["{$link->id}.share_link.opened"] ?? 0,
                'downloads' => $counts["{$link->id}.share_link.downloaded"] ?? 0,
                'canRevoke' => Gate::allows('revoke', $link),
            ])->values(),
            'files' => $files->map(fn (Asset $asset) => $this->presenter->summary($workspace, $asset))->values(),
            'publishedPages' => GuidelinePage::query()->where('brand_id', $brand->id)->whereNotNull('published_at')->count(),
            'defaults' => [
                'kind' => (ShareLinkKind::tryFrom($request->string('kind')->toString()) ?? ShareLinkKind::Guidelines)->value,
                'days' => ShareLink::DEFAULT_DAYS,
                'maxDays' => ShareLink::MAX_DAYS,
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace, Brand $brand): RedirectResponse
    {
        abort_unless(Gate::allows('view', $brand), 404);
        Gate::authorize('create', [ShareLink::class, $brand]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(ShareLinkKind::class)],
            'asset_ids' => ['exclude_unless:kind,files', 'required', 'array', 'min:1', 'max:'.ShareLink::MAX_FILES],
            'asset_ids.*' => ['exclude_unless:kind,files', 'uuid', 'distinct'],
            'days' => ['required', 'integer', 'min:1', 'max:'.ShareLink::MAX_DAYS],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'allow_originals' => ['boolean'],
        ], [
            'asset_ids.required' => __('Choose at least one file.'),
        ]);

        $kind = ShareLinkKind::from($validated['kind']);
        /** @var list<string> $assetIds */
        $assetIds = array_values($validated['asset_ids'] ?? []);

        if ($kind === ShareLinkKind::Files) {
            $usable = Asset::query()
                ->where('brand_id', $brand->id)
                ->where('status', AssetStatus::Approved)
                ->whereIn('id', $assetIds)
                ->count();

            if ($usable !== count($assetIds)) {
                throw ValidationException::withMessages(['asset_ids' => __('Only approved files from this brand can be shared.')]);
            }
        }

        if ($kind === ShareLinkKind::Guidelines
            && ! GuidelinePage::query()->where('brand_id', $brand->id)->whereNotNull('published_at')->exists()) {
            throw ValidationException::withMessages(['kind' => __('Publish at least one guideline page first. Links only show published pages.')]);
        }

        /** @var User $user */
        $user = $request->user();
        $token = Str::random(40);

        DB::transaction(function () use ($brand, $validated, $kind, $assetIds, $user, $token) {
            $link = ShareLink::create([
                'brand_id' => $brand->id,
                'name' => $validated['name'],
                'kind' => $kind,
                'token_hash' => ShareLink::hashToken($token),
                'expires_at' => now()->addDays((int) $validated['days']),
                'password_hash' => filled($validated['password'] ?? null) ? Hash::make($validated['password']) : null,
                'allow_originals' => (bool) ($validated['allow_originals'] ?? false),
                'created_by' => $user->id,
            ]);

            if ($assetIds !== []) {
                $link->assets()->attach($assetIds, ['workspace_id' => $link->workspace_id]);
            }

            ActivityLog::record('share_link.created', $link, [
                'brand_id' => $brand->id,
                'kind' => $kind->value,
                'files' => count($assetIds),
                'expires_at' => $link->expires_at->toIso8601String(),
                'password' => $link->hasPassword(),
                'allow_originals' => $link->allow_originals,
            ]);
        });

        // The secret is only known now; the page shows it once.
        Inertia::flash('shareLink', ['name' => $validated['name'], 'url' => route('share.show', $token)]);

        return to_route('workspaces.brands.share-links.index', [$workspace, $brand]);
    }

    public function destroy(Request $request, Workspace $workspace, Brand $brand, ShareLink $shareLink): RedirectResponse
    {
        abort_unless(Gate::allows('view', $brand) && $shareLink->brand_id === $brand->id, 404);
        Gate::authorize('revoke', $shareLink);

        DB::transaction(function () use ($request, $shareLink) {
            $shareLink->forceFill(['revoked_at' => now(), 'revoked_by' => $request->user()?->getAuthIdentifier()])->save();

            ActivityLog::record('share_link.revoked', $shareLink, ['name' => $shareLink->name]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link turned off. It stops working straight away.')]);

        return to_route('workspaces.brands.share-links.index', [$workspace, $brand]);
    }
}
