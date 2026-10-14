<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brands, brand roles and the asset library (THI-36), designed in
 * docs/roles-and-permissions.md. Every table carries workspace_id, and every
 * link between tables includes it, so the database refuses a row in one
 * company pointing at another company's brand, person or tag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();

            $table->unique(['workspace_id', 'id']);
            $table->foreign(['workspace_id', 'parent_id'])->references(['workspace_id', 'id'])->on('brands');
            $table->index(['workspace_id', 'parent_id']);
        });

        Schema::create('brand_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained();
            $table->uuid('brand_id');
            $table->uuid('membership_id');
            $table->string('role');
            $table->timestamps();

            $table->unique(['brand_id', 'membership_id']);
            $table->foreign(['workspace_id', 'brand_id'])->references(['workspace_id', 'id'])->on('brands')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'membership_id'])->references(['workspace_id', 'id'])->on('memberships')->cascadeOnDelete();
            $table->index(['workspace_id', 'membership_id']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained();
            $table->uuid('brand_id');
            $table->string('name');
            $table->string('kind');
            $table->string('status')->default('draft');
            $table->string('original_name');
            $table->string('extension', 16);
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('disk');
            $table->string('path');
            $table->string('preview_status')->default('none');
            $table->string('preview_path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'id']);
            $table->foreign(['workspace_id', 'brand_id'])->references(['workspace_id', 'id'])->on('brands');
            $table->index(['workspace_id', 'brand_id', 'status']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained();
            $table->string('name');
            $table->timestamps();

            $table->unique(['workspace_id', 'id']);
        });

        Schema::create('asset_tag', function (Blueprint $table) {
            $table->foreignUuid('workspace_id')->constrained();
            $table->uuid('asset_id');
            $table->uuid('tag_id');

            $table->primary(['asset_id', 'tag_id']);
            $table->foreign(['workspace_id', 'asset_id'])->references(['workspace_id', 'id'])->on('assets')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'tag_id'])->references(['workspace_id', 'id'])->on('tags')->cascadeOnDelete();
            $table->index(['workspace_id', 'tag_id']);
        });

        // "Logo" and "logo" are the same tag within a company.
        DB::statement('create unique index tags_workspace_name_unique on tags (workspace_id, lower(name))');

        DB::statement('alter table brands add constraint brands_not_own_parent check (parent_id is null or parent_id <> id)');
        DB::statement("alter table brand_roles add constraint brand_roles_role_check check (role in ('manager', 'editor', 'viewer'))");
        DB::statement("alter table assets add constraint assets_kind_check check (kind in ('logo', 'font', 'photo', 'document', 'other'))");
        DB::statement("alter table assets add constraint assets_status_check check (status in ('draft', 'approved', 'archived'))");
        DB::statement("alter table assets add constraint assets_preview_status_check check (preview_status in ('none', 'ready', 'failed'))");
        DB::statement("alter table assets add constraint assets_approval_check check (status = 'draft' or (approved_by is not null and approved_at is not null))");

        DB::unprepared(<<<'SQL'
            -- Nobody approves their own upload, unless the workspace allows it
            -- (a one-person company). Reads the workspace under the same lock as
            -- the app, so a workspace it cannot see counts as "not allowed".
            create or replace function assets_refuse_self_approval() returns trigger language plpgsql as $$
            begin
                if new.approved_by is not null
                   and new.approved_by is not distinct from new.uploaded_by
                   and (tg_op = 'INSERT' or old.approved_by is distinct from new.approved_by)
                   and not coalesce((select w.allow_self_approval from workspaces w where w.id = new.workspace_id), false)
                then
                    raise exception 'self-approval is not allowed in this workspace';
                end if;

                return new;
            end;
            $$;

            create trigger assets_no_self_approval
                before insert or update on assets
                for each row execute function assets_refuse_self_approval();

            -- Partners can be Editor or Viewer on a brand, never Brand manager.
            create or replace function brand_roles_refuse_partner_managers() returns trigger language plpgsql as $$
            begin
                if new.role = 'manager' and exists (
                    select 1 from memberships m
                    where m.id = new.membership_id and m.kind = 'partner'
                ) then
                    raise exception 'partners cannot be brand managers';
                end if;

                return new;
            end;
            $$;

            create trigger brand_roles_no_partner_managers
                before insert or update on brand_roles
                for each row execute function brand_roles_refuse_partner_managers();
            SQL);

        foreach (['brands', 'brand_roles', 'assets', 'tags', 'asset_tag'] as $table) {
            DB::unprepared(<<<SQL
                alter table {$table} enable row level security;
                alter table {$table} force row level security;
                create policy workspace_isolation on {$table}
                    using (workspace_id = current_workspace_id())
                    with check (workspace_id = current_workspace_id());
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('brand_roles');
        Schema::dropIfExists('brands');

        DB::statement('drop function if exists assets_refuse_self_approval()');
        DB::statement('drop function if exists brand_roles_refuse_partner_managers()');
    }
};
