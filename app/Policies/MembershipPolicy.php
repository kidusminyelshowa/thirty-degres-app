<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Membership;
use App\Models\User;
use App\Policies\Concerns\ActsInCurrentWorkspace;
use Illuminate\Auth\Access\Response;

/**
 * Who can change whose role, following the segregation-of-duties rules in
 * docs/roles-and-permissions.md.
 */
class MembershipPolicy
{
    use ActsInCurrentWorkspace;

    public function update(User $user, Membership $target, WorkspaceRole $newRole): Response
    {
        $actor = $this->actor($user, $target->workspace_id);

        if ($actor === null || ! $actor->managesWorkspace()) {
            return Response::deny(__('Only Owners and Admins can change roles.'));
        }

        if ($actor->is($target)) {
            return Response::deny(__('Nobody can change their own role.'));
        }

        if ($actor->role !== WorkspaceRole::Owner && ($target->role === WorkspaceRole::Owner || $newRole === WorkspaceRole::Owner)) {
            return Response::deny(__('Only an Owner can make or change an Owner.'));
        }

        if ($target->isPartner() && $newRole !== WorkspaceRole::Member) {
            return Response::deny(__('Partners are always Members.'));
        }

        if ($target->role === WorkspaceRole::Owner && $newRole !== WorkspaceRole::Owner && $this->isLastOwner($target)) {
            return Response::deny(__('A workspace always needs an Owner. Make someone else Owner first.'));
        }

        return Response::allow();
    }

    public function delete(User $user, Membership $target): Response
    {
        $actor = $this->actor($user, $target->workspace_id);

        if ($actor === null || ! $actor->managesWorkspace()) {
            return Response::deny(__('Only Owners and Admins can remove people.'));
        }

        if ($actor->is($target)) {
            return Response::deny(__('You cannot remove yourself.'));
        }

        if ($target->role === WorkspaceRole::Owner && $actor->role !== WorkspaceRole::Owner) {
            return Response::deny(__('Only an Owner can remove an Owner.'));
        }

        if ($target->role === WorkspaceRole::Owner && $this->isLastOwner($target)) {
            return Response::deny(__('A workspace always needs an Owner. Make someone else Owner first.'));
        }

        return Response::allow();
    }

    private function isLastOwner(Membership $target): bool
    {
        return ! Membership::query()
            ->where('role', WorkspaceRole::Owner)
            ->whereKeyNot($target->getKey())
            ->exists();
    }
}
