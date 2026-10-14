<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Assets\BrandAccess;
use App\Assets\VisibleAssets;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Brand;
use App\Models\User;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('workspaces/create');
    }

    public function store(Request $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $workspace = $createWorkspace->handle($user, $validated['name']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace created.')]);

        return to_route('workspaces.show', $workspace);
    }

    public function show(Workspace $workspace, Tenancy $tenancy, BrandAccess $access, VisibleAssets $visible): Response
    {
        Gate::authorize('view', $workspace);

        $roles = $access->roles();

        $counts = $visible->apply(Asset::query())
            ->toBase()
            ->selectRaw('brand_id, count(*) as files')
            ->groupBy('brand_id')
            ->pluck('files', 'brand_id');

        $brands = Brand::query()
            ->whereIn('id', array_keys($roles))
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'parentId' => $brand->parent_id,
                'role' => $roles[$brand->id]->value,
                'files' => (int) ($counts[$brand->id] ?? 0),
            ]);

        return Inertia::render('workspaces/show', [
            'memberCount' => Gate::allows('manageMembers', $workspace)
                ? $workspace->memberships()->count()
                : null,
            'partnerAccessEndsAt' => $tenancy->membership()?->access_ends_at?->toDateString(),
            'brands' => $brands,
            'canCreateBrand' => Gate::allows('create', Brand::class),
            'selfApproval' => Gate::allows('update', $workspace)
                ? ['enabled' => $workspace->allow_self_approval]
                : null,
        ]);
    }
}
