<?php

namespace App\Http\Controllers\Sharing;

use App\Enums\ShareLinkKind;
use App\Guidelines\GuidelineBlocks;
use App\Guidelines\Languages;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\GuidelinePage;
use App\Models\ShareLink;
use App\Sharing\OpenShareLink;
use App\Sharing\SharedContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What someone without an account sees when they open a share link: a
 * brand's published guidelines or a kit of approved files, read-only. Every
 * open and download is logged in the workspace's activity log.
 */
class SharedLinkController extends Controller
{
    public function __construct(private OpenShareLink $opener, private SharedContent $content) {}

    public function show(Request $request, string $token): Response
    {
        return $this->render($request, $token, null);
    }

    public function page(Request $request, string $token, string $page): Response
    {
        return $this->render($request, $token, $page);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $link = $this->open($token);

        $validated = $request->validate(['password' => ['required', 'string', 'max:100']]);

        if ($link->password_hash === null || ! Hash::check($validated['password'], $link->password_hash)) {
            throw ValidationException::withMessages(['password' => __('That password is not right. Ask the person who sent the link.')]);
        }

        $request->session()->regenerate();
        $this->opener->unlock($request, $link);

        return to_route('share.show', $token);
    }

    public function preview(Request $request, string $token, string $asset): StreamedResponse
    {
        [, $file] = $this->file($request, $token, $asset);
        abort_if($file->preview_path === null, 404);

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->preview_path), 404);

        $isSvg = $file->preview_path === $file->path && $file->extension === 'svg';

        return $disk->response($file->preview_path, null, [
            'Content-Type' => $isSvg ? 'image/svg+xml' : 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox",
        ]);
    }

    public function download(Request $request, string $token, string $asset): StreamedResponse
    {
        [$link, $file] = $this->file($request, $token, $asset);
        abort_unless($link->allow_originals, 404);

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404);

        ActivityLog::record('share_link.downloaded', $link, ['asset_id' => $file->id, 'name' => $file->name]);

        return $disk->download($file->path, $file->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A typeface on a shared guideline page. It is the original font file,
     * so only links that allow originals serve it.
     */
    public function font(Request $request, string $token, string $asset): StreamedResponse
    {
        [$link, $file] = $this->file($request, $token, $asset);

        $type = match ($file->extension) {
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            default => null,
        };
        abort_unless($link->allow_originals && $type !== null, 404);

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404);

        return $disk->response($file->path, null, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function render(Request $request, string $token, ?string $pageId): Response
    {
        $link = $this->open($token);
        $link->load('brand');
        $brand = ['name' => $link->brand->name];

        if (! $this->opener->isUnlocked($request, $link)) {
            return Inertia::render('shared/locked', ['token' => $token, 'brand' => $brand, 'name' => $link->name]);
        }

        $this->recordOpen($request, $link);

        $shared = [
            'token' => $token,
            'name' => $link->name,
            'brand' => $brand,
            'expiresAt' => $link->expires_at->toIso8601String(),
            'allowOriginals' => $link->allow_originals,
        ];

        if ($link->kind === ShareLinkKind::Files) {
            abort_if($pageId !== null, 404);

            return Inertia::render('shared/files', [
                'link' => $shared,
                'files' => $this->content->assets($link)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Asset $asset) => $this->content->asset($token, $link, $asset))
                    ->values(),
            ]);
        }

        $pages = $this->content->pages($link);
        $page = $pageId === null ? $pages->first() : $pages->firstWhere('id', $pageId);
        abort_if($pageId !== null && $page === null, 404);

        $blocks = $page->published_blocks ?? [];
        $ids = array_flip(GuidelineBlocks::assetIds($blocks));

        return Inertia::render('shared/guidelines', [
            'link' => $shared,
            'languages' => Languages::forBrand($link->brand),
            'pages' => $pages->map(fn (GuidelinePage $candidate) => [
                'id' => $candidate->id,
                'title' => $candidate->published_title ?? [],
            ])->values(),
            'page' => $page === null ? null : [
                'id' => $page->id,
                'title' => $page->published_title ?? [],
                'blocks' => $blocks,
                'publishedAt' => $page->published_at?->toIso8601String(),
            ],
            'assets' => $ids === [] ? (object) [] : $this->content->assets($link)
                ->whereIn('id', array_keys($ids))
                ->get()
                ->mapWithKeys(fn (Asset $asset) => [$asset->id => $this->content->asset($token, $link, $asset)]),
        ]);
    }

    /**
     * The live link, inside its workspace, or "not found".
     */
    private function open(string $token): ShareLink
    {
        $link = $this->opener->find($token);
        abort_if($link === null, 404);

        return $link;
    }

    /**
     * @return array{0: ShareLink, 1: Asset}
     */
    private function file(Request $request, string $token, string $assetId): array
    {
        $link = $this->open($token);
        abort_unless($this->opener->isUnlocked($request, $link), 404);

        $asset = $this->content->assets($link)->whereKey($assetId)->first();
        abort_if($asset === null, 404);

        return [$link, $asset];
    }

    /**
     * Logged once per visit, not on every page turn.
     */
    private function recordOpen(Request $request, ShareLink $link): void
    {
        $key = "share_links.{$link->id}.opened";

        if ($request->session()->get($key) === true) {
            return;
        }

        $request->session()->put($key, true);

        ActivityLog::record('share_link.opened', $link, ['signed_in' => $request->user() !== null]);
        ShareLink::query()->whereKey($link->id)->update(['last_opened_at' => now()]);
    }
}
