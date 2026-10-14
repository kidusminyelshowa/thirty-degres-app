<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Proves the database lock is really on in this environment: the app's
 * queries run as a role that cannot skip row-level security, and every
 * table holding company data is locked. Run it once on each new host.
 */
class CheckDatabaseLock extends Command
{
    protected $signature = 'tenancy:check';

    protected $description = 'Check that Postgres row-level security applies to the app';

    public function handle(): int
    {
        $problems = [];

        /** @var object{current_user: string, session_user: string, rolsuper: bool, rolbypassrls: bool} $role */
        $role = DB::selectOne(
            'select current_user, session_user, r.rolsuper, r.rolbypassrls from pg_roles r where r.rolname = current_user'
        );

        $this->line("Login user: {$role->session_user}");
        $this->line("Queries run as: {$role->current_user}");

        if ($role->rolsuper || $role->rolbypassrls) {
            $problems[] = "Queries run as {$role->current_user}, which can skip row-level security. Set DB_APP_ROLE and run the migrations.";
        }

        // Each statement below is its own transaction. Behind a transaction
        // pooler the role switch would not survive to the second one.
        $again = DB::selectOne('select current_user');

        if (! is_object($again) || ($again->current_user ?? null) !== $role->current_user) {
            $problems[] = 'The role switch did not survive between queries. Connect directly, not through a transaction pooler.';
        }

        $unlocked = DB::select(<<<'SQL'
            select c.relname as name
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace
            where n.nspname = 'public' and c.relkind = 'r'
              and (c.relname = 'workspaces' or exists (
                  select 1 from information_schema.columns col
                  where col.table_schema = 'public' and col.table_name = c.relname and col.column_name = 'workspace_id'
              ))
              and not (c.relrowsecurity and c.relforcerowsecurity)
            SQL);

        foreach ($unlocked as $table) {
            $problems[] = "Table {$table->name} holds company data but is not locked.";
        }

        $visible = DB::table('memberships')->count();

        if ($visible > 0) {
            $problems[] = "With no workspace chosen, {$visible} memberships are visible. The lock should show none.";
        }

        if ($problems === []) {
            $this->info('The database lock is on.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        return self::FAILURE;
    }
}
