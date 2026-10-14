<?php

namespace App\Actions\Workspaces;

use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a company workspace with the given person as its first Owner.
 */
class CreateWorkspace
{
    public function __construct(private Tenancy $tenancy) {}

    public function handle(User $owner, string $name): Workspace
    {
        $workspace = new Workspace(['name' => $name]);
        $workspace->id = $workspace->newUniqueId();

        // Postgres only accepts the new rows once it is told they belong to the
        // workspace being created, like any other write.
        return $this->tenancy->run($workspace, fn () => DB::transaction(function () use ($workspace, $owner) {
            $this->saveWithFreeSlug($workspace);

            $membership = Membership::create([
                'user_id' => $owner->id,
                'role' => WorkspaceRole::Owner,
                'kind' => MembershipKind::Staff,
            ]);

            ActivityLog::record('workspace.created', $membership, ['name' => $workspace->name], $owner);

            return $workspace;
        }));
    }

    /**
     * Other companies' workspaces are invisible here, so a taken address only
     * shows up when the database refuses it. Then add a short random ending.
     */
    private function saveWithFreeSlug(Workspace $workspace): void
    {
        // Names in Ge'ez script have no Latin spelling to borrow, so those
        // addresses start as "workspace-" plus a random ending.
        $base = rtrim(Str::limit(Str::slug($workspace->name), 48, ''), '-');
        $slug = $base !== '' ? $base : 'workspace-'.Str::lower(Str::random(5));
        $base = $base !== '' ? $base : 'workspace';

        for ($attempt = 0; ; $attempt++) {
            $workspace->slug = $slug;

            try {
                DB::transaction(fn () => $workspace->save());

                return;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }

                $slug = $base.'-'.Str::lower(Str::random(5));
            }
        }
    }
}
