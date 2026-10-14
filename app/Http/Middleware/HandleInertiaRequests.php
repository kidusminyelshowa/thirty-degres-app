<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'workspaces' => fn () => $this->workspaces($request->user()),
            'currentWorkspace' => fn () => $this->currentWorkspace(),
            'locale' => app()->getLocale(),
            'translations' => fn () => $this->translations(),
        ];
    }

    /**
     * The workspaces the signed-in person belongs to, for the switcher.
     *
     * @return list<array{name: string, slug: string, role: string}>
     */
    private function workspaces(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_values($user->memberships()
            ->active()
            ->with('workspace')
            ->oldest()
            ->get()
            ->map(fn (Membership $membership) => [
                'name' => $membership->workspace->name,
                'slug' => $membership->workspace->slug,
                'role' => $membership->role->value,
            ])
            ->all());
    }

    /**
     * @return array{name: string, slug: string, role: string, kind: string, canManageMembers: bool}|null
     */
    private function currentWorkspace(): ?array
    {
        $tenancy = app(Tenancy::class);
        $workspace = $tenancy->workspace();
        $membership = $tenancy->membership();

        if ($workspace === null || $membership === null) {
            return null;
        }

        return [
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'role' => $membership->role->value,
            'kind' => $membership->kind->value,
            'canManageMembers' => $membership->managesWorkspace(),
        ];
    }

    /**
     * Strings for the current language, from lang/{locale}.json. English is
     * the source text, so it needs no file.
     *
     * @return array<string, string>
     */
    private function translations(): array
    {
        $path = lang_path(app()->getLocale().'.json');

        if (! File::exists($path)) {
            return [];
        }

        $translations = json_decode(File::get($path), true);

        return is_array($translations) ? array_filter($translations, 'is_string') : [];
    }
}
