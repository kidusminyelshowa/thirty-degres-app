<?php

namespace App\Http\Controllers\Workspaces;

use App\Actions\Workspaces\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page an emailed invitation link opens. Works signed out, so a new
 * teammate can create their account from it.
 */
class AcceptInvitationController extends Controller
{
    public const SESSION_KEY = 'pending_invitation_token';

    public function show(Request $request, string $token, AcceptInvitation $acceptInvitation): Response
    {
        $invitation = $acceptInvitation->find($token);

        if ($invitation === null) {
            $request->session()->forget(self::SESSION_KEY);

            return Inertia::render('invitations/show', ['invitation' => null]);
        }

        // Remember the link through sign-up or sign-in.
        $request->session()->put(self::SESSION_KEY, $token);

        $user = $request->user();

        return Inertia::render('invitations/show', [
            'invitation' => [
                'token' => $token,
                'workspaceName' => $invitation->workspace->name,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'kind' => $invitation->kind->value,
                'brandName' => $acceptInvitation->brandName($invitation),
                'brandRole' => $invitation->brand_role?->value,
                'invitedBy' => $invitation->inviter?->name,
                'emailMatches' => $user instanceof User && $acceptInvitation->emailMatches($invitation, $user),
            ],
        ]);
    }

    public function store(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        $invitation = $acceptInvitation->find($token);

        if ($invitation === null) {
            return to_route('invitations.show', $token);
        }

        /** @var User $user */
        $user = $request->user();

        $workspace = $acceptInvitation->handle($invitation, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :workspace.', ['workspace' => $workspace->name])]);

        return to_route('workspaces.show', $workspace);
    }
}
