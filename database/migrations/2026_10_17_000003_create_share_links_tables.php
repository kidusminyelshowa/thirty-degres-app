<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only share links (THI-38), designed in docs/roles-and-permissions.md:
 * a secret link to a brand's published guidelines or to a kit of its
 * approved files, for people without an account. Every link expires, can be
 * revoked, can have a password, and serves previews only unless originals
 * are allowed. Only a hash of the link's secret is stored.
 *
 * Only adds tables, so it is safe on a database that already holds files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained();
            $table->uuid('brand_id');
            $table->string('name', 120);
            $table->string('kind');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('password_hash')->nullable();
            $table->boolean('allow_originals')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'id']);
            $table->foreign(['workspace_id', 'brand_id'])->references(['workspace_id', 'id'])->on('brands');
            $table->index(['workspace_id', 'brand_id']);
        });

        Schema::create('share_link_assets', function (Blueprint $table) {
            $table->foreignUuid('workspace_id')->constrained();
            $table->uuid('share_link_id');
            $table->uuid('asset_id');

            $table->primary(['share_link_id', 'asset_id']);
            $table->foreign(['workspace_id', 'share_link_id'])->references(['workspace_id', 'id'])->on('share_links')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'asset_id'])->references(['workspace_id', 'id'])->on('assets')->cascadeOnDelete();
            $table->index(['workspace_id', 'asset_id']);
        });

        DB::statement("alter table share_links add constraint share_links_kind_check check (kind in ('guidelines', 'files'))");
        // There is no "never expires": at most 90 days from when it was made.
        DB::statement("alter table share_links add constraint share_links_expiry_check check (created_at is null or expires_at <= created_at + interval '90 days 1 minute')");

        DB::unprepared(<<<'SQL'
            create or replace function current_share_token_hash() returns text
                language sql stable
                as $$ select nullif(current_setting('app.share_token_hash', true), '') $$;

            alter table share_links enable row level security;
            alter table share_links force row level security;
            create policy workspace_isolation on share_links
                using (workspace_id = current_workspace_id())
                with check (workspace_id = current_workspace_id());

            -- Opening a link: no one is signed in and the workspace is not yet
            -- known. Whoever holds the link's secret can read that one row,
            -- which says which workspace to continue in, under the normal lock.
            create policy share_link_by_token on share_links for select
                using (token_hash = current_share_token_hash());

            alter table share_link_assets enable row level security;
            alter table share_link_assets force row level security;
            create policy workspace_isolation on share_link_assets
                using (workspace_id = current_workspace_id())
                with check (workspace_id = current_workspace_id());
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('share_link_assets');
        Schema::dropIfExists('share_links');

        DB::statement('drop function if exists current_share_token_hash()');
    }
};
