<?php

namespace App\Policies;

use App\Assets\BrandAccess;
use App\Enums\BrandRoleName;
use App\Models\Brand;
use App\Models\ShareLink;
use App\Models\User;
use App\Policies\Concerns\ActsInCurrentWorkspace;

/**
 * Share links (docs/roles-and-permissions.md): Editors and above who are
 * staff make them and see the brand's links; partners never do. Whoever made
 * a link, or a Brand manager, can revoke it.
 */
class ShareLinkPolicy
{
    use ActsInCurrentWorkspace;

    public function __construct(private BrandAccess $access) {}

    public function viewAny(User $user, Brand $brand): bool
    {
        return $this->create($user, $brand);
    }

    public function create(User $user, Brand $brand): bool
    {
        $actor = $this->actor($user, $brand->workspace_id);

        return $actor !== null
            && ! $actor->isPartner()
            && ($this->access->roleOn($brand)?->atLeast(BrandRoleName::Editor) ?? false);
    }

    public function revoke(User $user, ShareLink $link): bool
    {
        $actor = $this->actor($user, $link->workspace_id);
        $role = $this->access->roleOn($link->brand_id);

        if ($actor === null || $actor->isPartner() || $role === null || $link->revoked_at !== null) {
            return false;
        }

        return $role === BrandRoleName::Manager
            || ($role === BrandRoleName::Editor && $link->created_by === $user->id);
    }
}
