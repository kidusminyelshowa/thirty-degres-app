<?php

namespace App\Policies;

use App\Assets\BrandAccess;
use App\Enums\BrandRoleName;
use App\Models\Brand;
use App\Models\User;
use App\Policies\Concerns\ActsInCurrentWorkspace;

/**
 * Brands: Owners and Admins create and rename them; Brand managers decide
 * who works on theirs (docs/roles-and-permissions.md).
 */
class BrandPolicy
{
    use ActsInCurrentWorkspace;

    public function __construct(private BrandAccess $access) {}

    public function view(User $user, Brand $brand): bool
    {
        return $this->actor($user, $brand->workspace_id) !== null
            && $this->access->roleOn($brand) !== null;
    }

    public function create(User $user): bool
    {
        return $this->actor($user)?->managesWorkspace() ?? false;
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->actor($user, $brand->workspace_id)?->managesWorkspace() ?? false;
    }

    /**
     * Upload files to this brand.
     */
    public function upload(User $user, Brand $brand): bool
    {
        return $this->actor($user, $brand->workspace_id) !== null
            && ($this->access->roleOn($brand)?->atLeast(BrandRoleName::Editor) ?? false);
    }

    /**
     * See who works on this brand and give them roles on it.
     */
    public function manageAccess(User $user, Brand $brand): bool
    {
        $actor = $this->actor($user, $brand->workspace_id);

        return $actor !== null
            && ! $actor->isPartner()
            && $this->access->roleOn($brand) === BrandRoleName::Manager;
    }
}
