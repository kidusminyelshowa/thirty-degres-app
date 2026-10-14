<?php

namespace App\Tenancy;

use App\Models\Membership;
use App\Models\Workspace;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Knows which workspace the current request (or job) is for, and tells
 * Postgres the same thing so row-level security filters every query.
 *
 * Locks 2 and 3 of docs/roles-and-permissions.md read from here: the
 * BelongsToWorkspace scope in the app, and current_workspace_id() in the
 * database. Keeping both in one place means they cannot disagree.
 */
class Tenancy
{
    private ?Workspace $workspace = null;

    private ?Membership $membership = null;

    private bool $dirty = false;

    public function workspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function workspaceId(): ?string
    {
        return $this->workspace?->id;
    }

    /**
     * The signed-in person's membership of the current workspace, if any.
     */
    public function membership(): ?Membership
    {
        return $this->membership;
    }

    /**
     * Who is signed in. Lets Postgres show a person their own memberships,
     * and nothing else, outside of a workspace.
     */
    public function setUser(?int $userId): void
    {
        $this->setDatabaseSetting('app.user_id', $userId === null ? null : (string) $userId);
    }

    public function enter(Workspace $workspace, ?Membership $membership = null): void
    {
        $this->workspace = $workspace;
        $this->membership = $membership;
        $this->setDatabaseSetting('app.workspace_id', $workspace->id);
    }

    public function leave(): void
    {
        $this->workspace = null;
        $this->membership = null;
        $this->setDatabaseSetting('app.workspace_id', null);
    }

    /**
     * Run a callback inside a workspace, then put back whatever was current.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Workspace $workspace, Closure $callback, ?Membership $membership = null): mixed
    {
        $previous = [$this->workspace, $this->membership];

        $this->enter($workspace, $membership);

        try {
            return $callback();
        } finally {
            [$previousWorkspace, $previousMembership] = $previous;

            $previousWorkspace === null
                ? $this->leave()
                : $this->enter($previousWorkspace, $previousMembership);
        }
    }

    /**
     * Let Postgres show the one invitation (and its workspace's name) whose
     * secret link this request carries.
     */
    public function setInvitationTokenHash(?string $tokenHash): void
    {
        $this->setDatabaseSetting('app.invitation_token_hash', $tokenHash);
    }

    /**
     * Forget everything, so nothing leaks into the next request or job on a
     * reused database connection.
     */
    public function reset(): void
    {
        $this->workspace = null;
        $this->membership = null;

        if (! $this->dirty) {
            return;
        }

        DB::select(
            "select set_config('app.workspace_id', '', false), set_config('app.user_id', '', false), set_config('app.invitation_token_hash', '', false)"
        );

        $this->dirty = false;
    }

    private function setDatabaseSetting(string $name, ?string $value): void
    {
        DB::select('select set_config(?, ?, false)', [$name, $value ?? '']);

        $this->dirty = true;
    }
}
