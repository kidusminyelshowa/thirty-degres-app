<?php

namespace Tests\Concerns;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Test data goes through the same database lock as the app: it is written
 * from inside its workspace, never around it.
 */
trait CreatesWorkspaces
{
    protected function createWorkspace(string $name = 'Ethio Coffee', ?User $owner = null): Workspace
    {
        return app(CreateWorkspace::class)->handle($owner ?? User::factory()->create(), $name);
    }

    protected function ownerOf(Workspace $workspace): User
    {
        return $this->inWorkspace($workspace, fn () => Membership::query()
            ->where('role', WorkspaceRole::Owner)
            ->oldest()
            ->firstOrFail()
            ->user);
    }

    protected function addMember(
        Workspace $workspace,
        WorkspaceRole $role = WorkspaceRole::Member,
        MembershipKind $kind = MembershipKind::Staff,
        ?CarbonInterface $accessEndsAt = null,
        ?User $user = null,
    ): Membership {
        $user ??= User::factory()->create();

        if ($kind === MembershipKind::Partner) {
            $accessEndsAt ??= now()->addDays(90);
        }

        return $this->inWorkspace($workspace, fn () => Membership::create([
            'user_id' => $user->id,
            'role' => $role,
            'kind' => $kind,
            'partner_organisation' => $kind === MembershipKind::Partner ? 'Addis Print House' : null,
            'access_ends_at' => $accessEndsAt,
        ]));
    }

    /**
     * @return array{0: Invitation, 1: string}
     */
    protected function createInvitation(
        Workspace $workspace,
        string $email = 'teammate@example.com',
        WorkspaceRole $role = WorkspaceRole::Member,
        ?CarbonInterface $expiresAt = null,
    ): array {
        $token = Str::random(48);

        $invitation = $this->inWorkspace($workspace, fn () => Invitation::create([
            'email' => $email,
            'role' => $role,
            'kind' => MembershipKind::Staff,
            'token_hash' => Invitation::hashToken($token),
            'invited_by' => $this->ownerOf($workspace)->id,
            'expires_at' => $expiresAt ?? now()->addDays(Invitation::DAYS_VALID),
        ]));

        return [$invitation, $token];
    }

    /**
     * A person's memberships, read the way the app reads them: with Postgres
     * told who is asking.
     *
     * @return Collection<int, Membership>
     */
    protected function membershipsOf(User $user): Collection
    {
        app(Tenancy::class)->setUser($user->id);

        return $user->memberships()->with('workspace')->get();
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function inWorkspace(Workspace $workspace, Closure $callback): mixed
    {
        return app(Tenancy::class)->run($workspace, $callback);
    }
}
