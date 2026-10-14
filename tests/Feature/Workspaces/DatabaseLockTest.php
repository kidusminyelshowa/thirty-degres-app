<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Tenancy\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

/**
 * Lock 3: Postgres row-level security keeps companies apart even when app
 * code skips every filter (docs/roles-and-permissions.md, tests 2 and 3).
 */
class DatabaseLockTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    private Workspace $companyA;

    private Workspace $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = $this->createWorkspace('Company A');
        $this->companyB = $this->createWorkspace('Company B');
        $this->addMember($this->companyA);
        $this->addMember($this->companyB);
        $this->addMember($this->companyB);
    }

    public function test_the_app_runs_as_a_role_that_cannot_skip_row_level_security()
    {
        $role = DB::selectOne('select current_user as name, r.rolsuper, r.rolbypassrls from pg_roles r where r.rolname = current_user');

        $this->assertSame('thirty_app', $role->name);
        $this->assertFalse($role->rolsuper);
        $this->assertFalse($role->rolbypassrls);
    }

    public function test_with_no_workspace_set_raw_queries_return_nothing()
    {
        $this->assertSame(0, DB::table('memberships')->count());
        $this->assertSame(0, DB::table('invitations')->count());
        $this->assertSame(0, DB::table('activity_log')->count());
        $this->assertSame(0, DB::table('workspaces')->count());
        $this->assertSame(0, Membership::withoutGlobalScopes()->count());
    }

    public function test_raw_queries_only_see_the_current_workspace()
    {
        $this->inWorkspace($this->companyA, function () {
            $this->assertSame(2, DB::table('memberships')->count());
            $this->assertSame(2, Membership::withoutGlobalScopes()->count());
            $this->assertSame([$this->companyA->id], DB::table('memberships')->distinct()->pluck('workspace_id')->all());
            $this->assertSame([$this->companyA->id], DB::table('workspaces')->pluck('id')->all());
            $this->assertSame(0, DB::table('memberships')->where('workspace_id', $this->companyB->id)->count());
        });

        $this->inWorkspace($this->companyB, function () {
            $this->assertSame(3, DB::table('memberships')->count());
        });
    }

    public function test_rows_cannot_be_written_into_another_workspace()
    {
        $this->inWorkspace($this->companyA, function () {
            $this->assertRefused(fn () => DB::table('memberships')->insert([
                'id' => (string) Str::uuid7(),
                'workspace_id' => $this->companyB->id,
                'user_id' => User::factory()->create()->id,
                'role' => 'owner',
                'kind' => 'staff',
            ]));

            $this->assertRefused(fn () => DB::table('activity_log')->insert([
                'id' => (string) Str::uuid7(),
                'workspace_id' => $this->companyB->id,
                'action' => 'member.invited',
            ]));

            // Moving one of A's rows over to B is refused too.
            $this->assertRefused(fn () => DB::table('memberships')->limit(1)->update(['workspace_id' => $this->companyB->id]));
        });
    }

    public function test_another_workspaces_rows_cannot_be_changed_or_deleted()
    {
        $this->inWorkspace($this->companyA, function () {
            $this->assertSame(0, DB::table('memberships')->where('workspace_id', $this->companyB->id)->update(['role' => 'owner']));
            $this->assertSame(0, DB::table('memberships')->where('workspace_id', $this->companyB->id)->delete());
            $this->assertSame(0, DB::table('workspaces')->where('id', $this->companyB->id)->update(['name' => 'Taken over']));
        });

        $this->inWorkspace($this->companyB, function () {
            $this->assertSame(3, DB::table('memberships')->count());
            $this->assertSame('Company B', DB::table('workspaces')->value('name'));
        });
    }

    public function test_a_person_sees_their_own_memberships_across_workspaces_and_no_one_elses()
    {
        $person = User::factory()->create();
        $this->addMember($this->companyA, WorkspaceRole::Member, user: $person);
        $this->addMember($this->companyB, WorkspaceRole::Admin, user: $person);

        app(Tenancy::class)->setUser($person->id);

        $this->assertSame(2, DB::table('memberships')->count());
        $this->assertSame([$person->id], DB::table('memberships')->distinct()->pluck('user_id')->all());
        $this->assertSame(2, DB::table('workspaces')->count());

        // Reading is all this doorway allows.
        $this->assertSame(0, DB::table('memberships')->update(['role' => 'owner']));
    }

    public function test_the_activity_log_cannot_be_edited_or_deleted()
    {
        $this->inWorkspace($this->companyA, function () {
            $this->assertRefused(fn () => DB::table('activity_log')->update(['action' => 'nothing.happened']));
            $this->assertRefused(fn () => DB::table('activity_log')->delete());
            $this->assertSame(['workspace.created'], ActivityLog::pluck('action')->all());
        });
    }

    public function test_every_table_with_company_data_is_locked()
    {
        $tables = collect(DB::select(<<<'SQL'
            select c.relname as name, c.relrowsecurity as enabled, c.relforcerowsecurity as forced,
                   exists (
                       select 1 from pg_policies p
                       where p.schemaname = 'public' and p.tablename = c.relname
                         and p.policyname = 'workspace_isolation'
                   ) as has_policy
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public' and c.relkind = 'r'
              and (c.relname = 'workspaces' or exists (
                  select 1 from information_schema.columns col
                  where col.table_schema = 'public' and col.table_name = c.relname
                    and col.column_name = 'workspace_id'
              ))
            SQL));

        $this->assertContains('memberships', $tables->pluck('name'));

        foreach ($tables as $table) {
            $this->assertTrue($table->enabled && $table->forced && $table->has_policy, "Table {$table->name} holds company data but is not locked with row-level security.");
        }
    }

    public function test_tenancy_settings_do_not_outlive_the_request()
    {
        $owner = $this->ownerOf($this->companyA);

        $this->actingAs($owner)->get(route('workspaces.show', $this->companyA))->assertOk();

        $this->assertSame(0, DB::table('memberships')->count());
        $this->assertSame('', DB::selectOne("select current_setting('app.workspace_id', true) as value")->value);
        $this->assertSame('', DB::selectOne("select current_setting('app.user_id', true) as value")->value);
    }

    public function test_the_lock_check_command_passes_here()
    {
        $this->artisan('tenancy:check')
            ->expectsOutput('Queries run as: thirty_app')
            ->expectsOutput('The database lock is on.')
            ->assertSuccessful();
    }

    private function assertRefused(callable $query): void
    {
        try {
            DB::transaction(fn () => $query());
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/row-level security|append-only|permission denied/', $e->getMessage());

            return;
        }

        $this->fail('The database allowed a write it should have refused.');
    }
}
