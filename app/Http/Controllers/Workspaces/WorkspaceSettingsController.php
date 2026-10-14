<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WorkspaceSettingsController extends Controller
{
    /**
     * A workspace with one person in it can let people approve their own
     * uploads. Only an Owner can turn this on, and every such approval is
     * marked in the activity log.
     */
    public function updateApproval(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('update', $workspace);

        $validated = $request->validate([
            'allow_self_approval' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($workspace, $validated) {
            $workspace->allow_self_approval = (bool) $validated['allow_self_approval'];
            $workspace->save();

            ActivityLog::record('workspace.self_approval_changed', $workspace, [
                'allow_self_approval' => $workspace->allow_self_approval,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval setting saved.')]);

        return to_route('workspaces.show', $workspace);
    }
}
