<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lock 1 of docs/roles-and-permissions.md: every /w/{workspace}/… page checks
 * the signed-in person is a current member of that workspace before anything
 * else runs, then makes it the current workspace for the rest of the request.
 *
 * Anyone else gets "not found", so they cannot even learn the workspace exists.
 */
class ResolveWorkspace
{
    public function __construct(private Tenancy $tenancy) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $slug = $route?->parameter('workspace');
        $user = $request->user();

        abort_unless($route !== null && is_string($slug) && $user instanceof User, 404);

        $membership = $user->memberships()
            ->active()
            ->whereHas('workspace', fn ($query) => $query->where('slug', $slug))
            ->with('workspace')
            ->first();

        abort_if($membership === null, 404);

        /** @var Workspace $workspace */
        $workspace = $membership->workspace;

        $this->tenancy->enter($workspace, $membership);
        $route->setParameter('workspace', $workspace);

        return $next($request);
    }
}
