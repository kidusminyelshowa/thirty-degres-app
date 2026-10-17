<?php

namespace App\Policies;

use App\Assets\BrandAccess;
use App\Enums\BrandRoleName;
use App\Models\Brand;
use App\Models\GuidelinePage;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\Concerns\ActsInCurrentWorkspace;
use App\Tenancy\Tenancy;
use Illuminate\Auth\Access\Response;

/**
 * Guideline pages follow the role table in docs/roles-and-permissions.md:
 * Viewers see published pages; Editors write drafts; Brand managers publish
 * (never their own edits, unless the workspace allows it) and delete.
 * Partner Editors only work on pages they started.
 */
class GuidelinePagePolicy
{
    use ActsInCurrentWorkspace;

    public function __construct(private BrandAccess $access, private Tenancy $tenancy) {}

    /**
     * See the published page. Pages never published are only for the
     * people who can edit them.
     */
    public function view(User $user, GuidelinePage $page): bool
    {
        [$actor, $role] = $this->actorAndRole($user, $page);

        if ($actor === null || $role === null) {
            return false;
        }

        return $page->isPublished() || $this->update($user, $page);
    }

    /**
     * Start a page in this brand's guidelines.
     */
    public function create(User $user, Brand $brand): bool
    {
        return $this->actor($user, $brand->workspace_id) !== null
            && ($this->access->roleOn($brand)?->atLeast(BrandRoleName::Editor) ?? false);
    }

    /**
     * See and change the working copy.
     */
    public function update(User $user, GuidelinePage $page): bool
    {
        [$actor, $role] = $this->actorAndRole($user, $page);

        if ($actor === null || $role === null || ! $role->atLeast(BrandRoleName::Editor)) {
            return false;
        }

        return ! $actor->isPartner() || $this->startedBy($page, $actor);
    }

    /**
     * Put the working copy live. Nobody publishes their own edits, unless the
     * workspace allows self-approval.
     */
    public function publish(User $user, GuidelinePage $page): Response
    {
        [$actor, $role] = $this->actorAndRole($user, $page);

        if ($actor === null || $role !== BrandRoleName::Manager || $actor->isPartner()) {
            return Response::deny(__('Only a Brand manager can publish guidelines.'));
        }

        if (! $page->hasUnpublishedChanges()) {
            return Response::deny(__('There are no changes to publish.'));
        }

        if ($page->edited_by === $actor->user_id && ! $this->allowsSelfApproval()) {
            return Response::deny(__('Someone else must publish your changes.'));
        }

        return Response::allow();
    }

    /**
     * Change the order of the brand's pages.
     */
    public function reorder(User $user, Brand $brand): bool
    {
        $actor = $this->actor($user, $brand->workspace_id);

        return $actor !== null
            && ! $actor->isPartner()
            && ($this->access->roleOn($brand)?->atLeast(BrandRoleName::Editor) ?? false);
    }

    /**
     * Brand managers delete pages. Whoever started a page can delete it while
     * it has never been published.
     */
    public function delete(User $user, GuidelinePage $page): bool
    {
        [$actor, $role] = $this->actorAndRole($user, $page);

        if ($actor === null || $role === null) {
            return false;
        }

        if ($role === BrandRoleName::Manager && ! $actor->isPartner()) {
            return true;
        }

        return $role === BrandRoleName::Editor && ! $page->isPublished() && $this->startedBy($page, $actor);
    }

    /**
     * @return array{0: Membership|null, 1: BrandRoleName|null}
     */
    private function actorAndRole(User $user, GuidelinePage $page): array
    {
        $actor = $this->actor($user, $page->workspace_id);

        return [$actor, $actor !== null ? $this->access->roleOn($page->brand_id) : null];
    }

    private function startedBy(GuidelinePage $page, Membership $actor): bool
    {
        return $page->created_by !== null && $page->created_by === $actor->user_id;
    }

    private function allowsSelfApproval(): bool
    {
        $workspace = $this->tenancy->workspace();

        return $workspace instanceof Workspace && $workspace->allow_self_approval;
    }
}
