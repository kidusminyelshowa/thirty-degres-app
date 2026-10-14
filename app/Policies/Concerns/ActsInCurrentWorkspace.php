<?php

namespace App\Policies\Concerns;

use App\Models\Membership;
use App\Models\User;
use App\Tenancy\Tenancy;

trait ActsInCurrentWorkspace
{
    /**
     * The person's own active membership of the workspace this request is for.
     */
    protected function actor(User $user, ?string $workspaceId = null): ?Membership
    {
        $tenancy = app(Tenancy::class);
        $membership = $tenancy->membership();

        if ($membership === null || $membership->user_id !== $user->id || ! $membership->isActive()) {
            return null;
        }

        if ($workspaceId !== null && $tenancy->workspaceId() !== $workspaceId) {
            return null;
        }

        return $membership;
    }
}
