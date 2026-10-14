<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The third lock from docs/roles-and-permissions.md: Postgres itself refuses
 * to return or change rows from any workspace except the current one.
 *
 * The app connects as the migration (owner) user and then switches to the
 * restricted role below for every query (see App\Tenancy\DatabaseRole), so the
 * policies apply even if that user is a superuser or has BYPASSRLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Passed as a setting and quoted by Postgres (format %I), so the role
        // name never becomes part of the SQL text.
        DB::select("select set_config('app.migration_role', ?, false)", [$this->appRole()]);

        DB::unprepared(<<<'SQL'
            do $$
            declare
                app_role text := current_setting('app.migration_role');
            begin
                if not exists (select from pg_roles where rolname = app_role) then
                    execute format('create role %I nologin', app_role);
                end if;

                execute format('grant %I to current_user', app_role);
                execute format('grant usage on schema public to %I', app_role);
                execute format('grant select, insert, update, delete on all tables in schema public to %I', app_role);
                execute format('grant usage, select on all sequences in schema public to %I', app_role);
                execute format('alter default privileges in schema public grant select, insert, update, delete on tables to %I', app_role);
                execute format('alter default privileges in schema public grant usage, select on sequences to %I', app_role);
                execute format('revoke update, delete on activity_log from %I', app_role);
            end
            $$;

            select set_config('app.migration_role', '', false);

            -- Which workspace and person this request is for. Unset means null,
            -- and null matches no rows, so a forgotten setting fails closed.
            create or replace function current_workspace_id() returns uuid
                language sql stable
                as $$ select nullif(current_setting('app.workspace_id', true), '')::uuid $$;

            create or replace function current_app_user_id() returns bigint
                language sql stable
                as $$ select nullif(current_setting('app.user_id', true), '')::bigint $$;

            create or replace function current_invitation_token_hash() returns text
                language sql stable
                as $$ select nullif(current_setting('app.invitation_token_hash', true), '') $$;

            -- Every table holding company data: one rule, for reading and writing.
            alter table memberships enable row level security;
            alter table memberships force row level security;
            create policy workspace_isolation on memberships
                using (workspace_id = current_workspace_id())
                with check (workspace_id = current_workspace_id());

            alter table invitations enable row level security;
            alter table invitations force row level security;
            create policy workspace_isolation on invitations
                using (workspace_id = current_workspace_id())
                with check (workspace_id = current_workspace_id());

            alter table activity_log enable row level security;
            alter table activity_log force row level security;
            create policy workspace_isolation on activity_log
                using (workspace_id = current_workspace_id())
                with check (workspace_id = current_workspace_id());

            alter table workspaces enable row level security;
            alter table workspaces force row level security;
            create policy workspace_isolation on workspaces
                using (id = current_workspace_id())
                with check (id = current_workspace_id());

            -- Narrow doorways that legitimately cross workspaces, read-only:
            -- a person can list the workspaces they belong to ...
            create policy own_memberships on memberships for select
                using (user_id = current_app_user_id());

            create policy member_workspaces on workspaces for select
                using (exists (
                    select 1 from memberships m
                    where m.workspace_id = workspaces.id
                      and m.user_id = current_app_user_id()
                ));

            -- ... and whoever holds an invitation's secret link can read that one
            -- invitation and the name of its workspace.
            create policy invitation_by_token on invitations for select
                using (token_hash = current_invitation_token_hash());

            create policy invited_workspace on workspaces for select
                using (exists (
                    select 1 from invitations i
                    where i.workspace_id = workspaces.id
                      and i.token_hash = current_invitation_token_hash()
                ));
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            drop policy if exists invited_workspace on workspaces;
            drop policy if exists invitation_by_token on invitations;
            drop policy if exists member_workspaces on workspaces;
            drop policy if exists own_memberships on memberships;
            drop policy if exists workspace_isolation on workspaces;
            alter table workspaces no force row level security;
            alter table workspaces disable row level security;
            SQL);

        DB::unprepared(<<<'SQL'
            drop policy if exists workspace_isolation on activity_log;
            alter table activity_log no force row level security;
            alter table activity_log disable row level security;
            drop policy if exists workspace_isolation on invitations;
            alter table invitations no force row level security;
            alter table invitations disable row level security;
            drop policy if exists workspace_isolation on memberships;
            alter table memberships no force row level security;
            alter table memberships disable row level security;
            drop function if exists current_invitation_token_hash();
            drop function if exists current_app_user_id();
            drop function if exists current_workspace_id();
            SQL);
    }

    private function appRole(): string
    {
        $role = config('database.connections.pgsql.app_role') ?: 'thirty_app';

        if (! is_string($role) || ! preg_match('/^[a-z_][a-z0-9_]*$/', $role)) {
            throw new RuntimeException('DB_APP_ROLE must be a plain lowercase Postgres role name.');
        }

        return $role;
    }
};
