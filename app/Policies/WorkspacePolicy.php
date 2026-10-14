<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\Concerns\ActsInCurrentWorkspace;

class WorkspacePolicy
{
    use ActsInCurrentWorkspace;

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->actor($user, $workspace->id) !== null;
    }

    /**
     * See the member list and invite people: staff Owners and Admins.
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->actor($user, $workspace->id)?->managesWorkspace() ?? false;
    }

    /**
     * Rename the workspace, billing, deleting it: Owners only.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        $actor = $this->actor($user, $workspace->id);

        return $actor !== null && $actor->managesWorkspace() && $actor->role === WorkspaceRole::Owner;
    }
}
