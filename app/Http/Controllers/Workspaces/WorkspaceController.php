<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Http\Controllers\Controller;
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

    public function show(Workspace $workspace, Tenancy $tenancy): Response
    {
        Gate::authorize('view', $workspace);

        return Inertia::render('workspaces/show', [
            'memberCount' => Gate::allows('manageMembers', $workspace)
                ? $workspace->memberships()->count()
                : null,
            'partnerAccessEndsAt' => $tenancy->membership()?->access_ends_at?->toDateString(),
        ]);
    }
}
