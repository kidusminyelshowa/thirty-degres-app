<?php

namespace App\Http\Controllers\Assets;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Approving, archiving and restoring files. Approval is also checked by the
 * database, which refuses self-approval whatever the app does.
 */
class AssetStatusController extends Controller
{
    public function approve(Request $request, Workspace $workspace, Asset $asset): RedirectResponse
    {
        abort_unless(Gate::allows('view', $asset), 404);
        Gate::authorize('approve', $asset);

        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($asset, $user) {
            $asset->status = AssetStatus::Approved;
            $asset->approved_by = $user->id;
            $asset->approved_at = now();
            $asset->save();

            ActivityLog::record('asset.approved', $asset, [
                'name' => $asset->name,
                'self_approved' => $asset->uploaded_by === $user->id,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approved. Everyone on this brand can now use it.')]);

        return to_route('workspaces.assets.show', [$workspace, $asset]);
    }

    public function archive(Workspace $workspace, Asset $asset): RedirectResponse
    {
        abort_unless(Gate::allows('view', $asset), 404);
        Gate::authorize('archive', $asset);

        DB::transaction(function () use ($asset) {
            $asset->status = AssetStatus::Archived;
            $asset->archived_at = now();
            $asset->save();

            ActivityLog::record('asset.archived', $asset, ['name' => $asset->name]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Archived. Only Brand managers can see it now.')]);

        return to_route('workspaces.assets.show', [$workspace, $asset]);
    }

    public function restore(Workspace $workspace, Asset $asset): RedirectResponse
    {
        abort_unless(Gate::allows('view', $asset), 404);
        Gate::authorize('restore', $asset);

        DB::transaction(function () use ($asset) {
            $asset->status = AssetStatus::Approved;
            $asset->archived_at = null;
            $asset->save();

            ActivityLog::record('asset.restored', $asset, ['name' => $asset->name]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Restored.')]);

        return to_route('workspaces.assets.show', [$workspace, $asset]);
    }
}
