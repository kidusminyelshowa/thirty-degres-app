<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\InviteMember;
use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function store(Request $request, Workspace $workspace, InviteMember $inviteMember): RedirectResponse
    {
        Gate::authorize('manageMembers', $workspace);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
            'kind' => ['required', Rule::enum(MembershipKind::class)],
            'partner_organisation' => ['nullable', 'required_if:kind,partner', 'string', 'max:120'],
            'access_ends_at' => ['nullable', 'required_if:kind,partner', 'date', 'after:today', 'before_or_equal:'.now()->addYear()->toDateString()],
        ]);

        $role = WorkspaceRole::from($validated['role']);
        $kind = MembershipKind::from($validated['kind']);

        Gate::authorize('create', [Invitation::class, $role, $kind]);

        $alreadyMember = Membership::query()
            ->whereHas('user', fn ($query) => $query->whereRaw('lower(email) = ?', [Str::lower($validated['email'])]))
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => __('This person is already in the workspace.'),
            ]);
        }

        /** @var User $user */
        $user = $request->user();

        $inviteMember->handle(
            $workspace,
            $user,
            $validated['email'],
            $role,
            $kind,
            $validated['partner_organisation'] ?? null,
            isset($validated['access_ends_at']) ? Date::parse($validated['access_ends_at'])->endOfDay() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $validated['email']])]);

        return to_route('workspaces.members.index', $workspace);
    }

    public function destroy(Workspace $workspace, Invitation $invitation): RedirectResponse
    {
        Gate::authorize('delete', $invitation);

        $invitation->delete();

        ActivityLog::record('invitation.revoked', $invitation, ['email' => $invitation->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('workspaces.members.index', $workspace);
    }
}
