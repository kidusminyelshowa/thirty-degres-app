<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workspaces, memberships, invitations and the activity log
 * (docs/roles-and-permissions.md, THI-11 / THI-35).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // When the person agreed to their data being stored outside Ethiopia
            // (docs/data-residency.md). Null for accounts made before the question existed.
            $table->timestamp('data_transfer_consent_at')->nullable();
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('allow_self_approval')->default(false);
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('kind')->default('staff');
            $table->string('partner_organisation')->nullable();
            $table->timestamp('access_ends_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            // Lets other company tables point at (workspace_id, membership_id).
            $table->unique(['workspace_id', 'id']);
            $table->index('user_id');
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained();
            $table->string('email');
            $table->string('role');
            $table->string('kind')->default('staff');
            $table->string('partner_organisation')->nullable();
            $table->timestamp('access_ends_at')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained();
            // No foreign key: the log keeps who did what even after an account is deleted.
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('details')->default('{}');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'created_at']);
        });

        // Values the app must never get wrong are also rules in the database.
        DB::statement("alter table memberships add constraint memberships_role_check check (role in ('owner', 'admin', 'member'))");
        DB::statement("alter table memberships add constraint memberships_kind_check check (kind in ('staff', 'partner'))");
        DB::statement("alter table memberships add constraint memberships_partner_check check (kind = 'staff' or (role = 'member' and access_ends_at is not null))");
        DB::statement("alter table invitations add constraint invitations_role_check check (role in ('owner', 'admin', 'member'))");
        DB::statement("alter table invitations add constraint invitations_kind_check check (kind in ('staff', 'partner'))");
        DB::statement("alter table invitations add constraint invitations_partner_check check (kind = 'staff' or (role = 'member' and access_ends_at is not null))");

        // The activity log can be added to, never edited or deleted, by anyone.
        DB::unprepared(<<<'SQL'
            create or replace function activity_log_is_append_only() returns trigger language plpgsql as $$
            begin
                raise exception 'activity_log is append-only';
            end;
            $$;

            create trigger activity_log_append_only
                before update or delete on activity_log
                for each row execute function activity_log_is_append_only();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
        DB::statement('drop function if exists activity_log_is_append_only()');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('workspaces');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('data_transfer_consent_at');
        });
    }
};
