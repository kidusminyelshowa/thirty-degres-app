<?php

namespace App\Policies;

use App\Assets\BrandAccess;
use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Models\Asset;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\Concerns\ActsInCurrentWorkspace;
use App\Tenancy\Tenancy;
use Illuminate\Auth\Access\Response;

/**
 * What each brand role can do with a file (docs/roles-and-permissions.md,
 * "What each role can do"). Keep view() in step with App\Assets\VisibleAssets.
 */
class AssetPolicy
{
    use ActsInCurrentWorkspace;

    public function __construct(private BrandAccess $access, private Tenancy $tenancy) {}

    /**
     * See the file, its preview, and download it.
     */
    public function view(User $user, Asset $asset): bool
    {
        [$actor, $role] = $this->actorAndRole($user, $asset);

        if ($actor === null || $role === null) {
            return false;
        }

        return match ($asset->status) {
            AssetStatus::Approved => true,
            AssetStatus::Draft => $role === BrandRoleName::Manager
                || ($role === BrandRoleName::Editor && (! $actor->isPartner() || $this->uploadedBy($asset, $actor))),
            AssetStatus::Archived => $role === BrandRoleName::Manager,
        };
    }

    /**
     * Rename the file, change its type and tags. Editors can change drafts;
     * once a file is approved only a Brand manager can.
     */
    public function update(User $user, Asset $asset): bool
    {
        [$actor, $role] = $this->actorAndRole($user, $asset);

        if ($actor === null || $role === null || $asset->isArchived()) {
            return false;
        }

        if ($role === BrandRoleName::Manager) {
            return true;
        }

        return $role === BrandRoleName::Editor
            && $asset->isDraft()
            && (! $actor->isPartner() || $this->uploadedBy($asset, $actor));
    }

    /**
     * Nobody approves their own upload, unless the workspace allows it.
     */
    public function approve(User $user, Asset $asset): Response
    {
        [$actor, $role] = $this->actorAndRole($user, $asset);

        if ($actor === null || $role !== BrandRoleName::Manager || $actor->isPartner()) {
            return Response::deny(__('Only a Brand manager can approve files. Ask a Brand manager on this brand.'));
        }

        if (! $asset->isDraft()) {
            return Response::deny(__('Only drafts can be approved.'));
        }

        if ($this->uploadedBy($asset, $actor) && ! $this->allowsSelfApproval()) {
            return Response::deny(__('Someone else needs to approve your upload. Ask a Brand manager on this brand.'));
        }

        return Response::allow();
    }

    public function archive(User $user, Asset $asset): bool
    {
        return $this->isManager($user, $asset) && $asset->isApproved();
    }

    public function restore(User $user, Asset $asset): bool
    {
        return $this->isManager($user, $asset) && $asset->isArchived();
    }

    /**
     * Drafts can be deleted by whoever uploaded them, or a Brand manager.
     * Approved files are archived instead, so nothing approved disappears.
     */
    public function delete(User $user, Asset $asset): bool
    {
        [$actor, $role] = $this->actorAndRole($user, $asset);

        if ($actor === null || $role === null || ! $asset->isDraft()) {
            return false;
        }

        return $role === BrandRoleName::Manager
            || ($role === BrandRoleName::Editor && $this->uploadedBy($asset, $actor));
    }

    private function isManager(User $user, Asset $asset): bool
    {
        [$actor, $role] = $this->actorAndRole($user, $asset);

        return $actor !== null && ! $actor->isPartner() && $role === BrandRoleName::Manager;
    }

    /**
     * @return array{0: Membership|null, 1: BrandRoleName|null}
     */
    private function actorAndRole(User $user, Asset $asset): array
    {
        $actor = $this->actor($user, $asset->workspace_id);

        return [$actor, $actor !== null ? $this->access->roleOn($asset->brand_id) : null];
    }

    private function uploadedBy(Asset $asset, Membership $actor): bool
    {
        return $asset->uploaded_by !== null && $asset->uploaded_by === $actor->user_id;
    }

    private function allowsSelfApproval(): bool
    {
        $workspace = $this->tenancy->workspace();

        return $workspace instanceof Workspace && $workspace->allow_self_approval;
    }
}
