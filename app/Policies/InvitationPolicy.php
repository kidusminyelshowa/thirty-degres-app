<?php

namespace App\Policies;

use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\User;
use App\Policies\Concerns\ActsInCurrentWorkspace;
use Illuminate\Auth\Access\Response;

class InvitationPolicy
{
    use ActsInCurrentWorkspace;

    public function create(User $user, WorkspaceRole $role, MembershipKind $kind): Response
    {
        $actor = $this->actor($user);

        if ($actor === null || ! $actor->managesWorkspace()) {
            return Response::deny(__('Only Owners and Admins can invite people. Ask one of them to send the invitation.'));
        }

        if ($role === WorkspaceRole::Owner && $actor->role !== WorkspaceRole::Owner) {
            return Response::deny(__('Only an Owner can make or change an Owner. Ask an Owner to do it.'));
        }

        if ($kind === MembershipKind::Partner && $role !== WorkspaceRole::Member) {
            return Response::deny(__('Partners are always Members.'));
        }

        return Response::allow();
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->actor($user, $invitation->workspace_id)?->managesWorkspace() ?? false;
    }
}
