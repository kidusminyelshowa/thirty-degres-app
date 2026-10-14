<?php

namespace App\Actions\Workspaces;

use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a pending invitation into a membership for the person it was sent to.
 */
class AcceptInvitation
{
    public function __construct(private Tenancy $tenancy) {}

    /**
     * Find the pending invitation behind a secret link, or null.
     */
    public function find(string $token): ?Invitation
    {
        $this->tenancy->setInvitationTokenHash(Invitation::hashToken($token));

        $invitation = Invitation::query()
            ->withoutGlobalScopes()
            ->with('workspace')
            ->where('token_hash', Invitation::hashToken($token))
            ->first();

        return $invitation?->isPending() ? $invitation : null;
    }

    public function emailMatches(Invitation $invitation, User $user): bool
    {
        return Str::lower($user->email) === Str::lower($invitation->email);
    }

    public function handle(Invitation $invitation, User $user): Workspace
    {
        if (! $this->emailMatches($invitation, $user)) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation was sent to :email.', ['email' => $invitation->email]),
            ]);
        }

        $workspace = $invitation->workspace;

        $this->tenancy->run($workspace, fn () => DB::transaction(function () use ($invitation, $user) {
            $membership = Membership::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'role' => $invitation->role,
                    'kind' => $invitation->kind,
                    'partner_organisation' => $invitation->partner_organisation,
                    'access_ends_at' => $invitation->access_ends_at,
                ],
            );

            $invitation->forceFill(['accepted_at' => now()])->save();

            ActivityLog::record('invitation.accepted', $membership, ['email' => $invitation->email], $user);
        }));

        // The link was emailed to this address, so opening it proves the
        // person controls the inbox.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $workspace;
    }
}
