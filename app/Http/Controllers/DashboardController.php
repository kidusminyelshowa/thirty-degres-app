<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\AcceptInvitation;
use App\Http\Controllers\Workspaces\AcceptInvitationController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where people land after signing in: a pending invitation first, then
 * their workspace, or the page to create one.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $request->session()->pull(AcceptInvitationController::SESSION_KEY);
        $invitation = is_string($token) ? $acceptInvitation->find($token) : null;

        // Only when it is theirs to accept; otherwise drop it, so a stale
        // link never stands between someone and their own workspace.
        if (is_string($token) && $invitation !== null && $acceptInvitation->emailMatches($invitation, $user)) {
            return to_route('invitations.show', $token);
        }

        $membership = $user->memberships()->active()->with('workspace')->oldest()->first();

        return $membership === null
            ? to_route('workspaces.create')
            : to_route('workspaces.show', $membership->workspace);
    }
}
