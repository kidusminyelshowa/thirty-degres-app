<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, Workspace $workspace, Tenancy $tenancy): Response
    {
        Gate::authorize('manageMembers', $workspace);

        /** @var User $user */
        $user = $request->user();
        $actor = $tenancy->membership();

        $members = $workspace->memberships()
            ->with('user')
            ->get()
            ->sortBy(fn (Membership $membership) => [
                array_search($membership->role, WorkspaceRole::cases(), true),
                $membership->user->name,
            ])
            ->values()
            ->map(fn (Membership $membership) => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'kind' => $membership->kind->value,
                'partnerOrganisation' => $membership->partner_organisation,
                'accessEndsAt' => $membership->access_ends_at?->toDateString(),
                'isActive' => $membership->isActive(),
                'isYou' => $membership->user_id === $user->id,
                'canRemove' => Gate::inspect('delete', $membership)->allowed(),
            ]);

        $invitations = $workspace->invitations()
            ->with('inviter')
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get()
            ->map(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'kind' => $invitation->kind->value,
                'partnerOrganisation' => $invitation->partner_organisation,
                'expiresAt' => $invitation->expires_at->toDateString(),
                'invitedBy' => $invitation->inviter?->name,
            ]);

        return Inertia::render('workspaces/members', [
            'members' => $members,
            'invitations' => $invitations,
            'assignableRoles' => $actor?->role === WorkspaceRole::Owner
                ? array_map(fn (WorkspaceRole $role) => $role->value, WorkspaceRole::cases())
                : [WorkspaceRole::Admin->value, WorkspaceRole::Member->value],
            'defaultPartnerAccessEndsAt' => now()->addDays(90)->toDateString(),
        ]);
    }

    public function update(Request $request, Workspace $workspace, Membership $membership): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
        ]);

        $newRole = WorkspaceRole::from($validated['role']);

        Gate::authorize('update', [$membership, $newRole]);

        $previous = $membership->role;
        $membership->update(['role' => $newRole]);

        ActivityLog::record('member.role_changed', $membership, [
            'from' => $previous->value,
            'to' => $newRole->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('workspaces.members.index', $workspace);
    }

    public function destroy(Workspace $workspace, Membership $membership): RedirectResponse
    {
        Gate::authorize('delete', $membership);

        $membership->delete();

        ActivityLog::record('member.removed', $membership, [
            'user_id' => $membership->user_id,
            'role' => $membership->role->value,
            'kind' => $membership->kind->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Removed from the workspace.')]);

        return to_route('workspaces.members.index', $workspace);
    }
}
