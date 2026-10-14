<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guideline pages (THI-37): a brand's logo, colour, type and voice rules, in
 * English and Amharic. Each page keeps the working copy Editors change and
 * the copy a Brand manager last published, which is all Viewers ever see.
 *
 * Only adds a table, so it is safe on a database that already holds files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guideline_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained();
            $table->uuid('brand_id');
            $table->unsignedInteger('position')->default(0);
            $table->string('title_en')->default('');
            $table->string('title_am')->default('');
            $table->jsonb('blocks')->default('[]');
            $table->string('published_title_en')->nullable();
            $table->string('published_title_am')->nullable();
            $table->jsonb('published_blocks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'id']);
            $table->foreign(['workspace_id', 'brand_id'])->references(['workspace_id', 'id'])->on('brands');
            $table->index(['workspace_id', 'brand_id', 'position']);
        });

        DB::statement("alter table guideline_pages add constraint guideline_pages_blocks_is_list check (jsonb_typeof(blocks) = 'array')");
        DB::statement('alter table guideline_pages add constraint guideline_pages_publish_check check ((published_at is null) = (published_blocks is null))');

        DB::unprepared(<<<'SQL'
            -- Nobody publishes their own edits, unless the workspace allows
            -- self-approval (a one-person company). Same rule as for files.
            create or replace function guideline_pages_refuse_self_publish() returns trigger language plpgsql as $$
            begin
                if new.published_at is not null
                   and new.published_by is not distinct from new.edited_by
                   and (tg_op = 'INSERT' or old.published_at is distinct from new.published_at)
                   and not coalesce((select w.allow_self_approval from workspaces w where w.id = new.workspace_id), false)
                then
                    raise exception 'self-approval is not allowed in this workspace';
                end if;

                return new;
            end;
            $$;

            create trigger guideline_pages_no_self_publish
                before insert or update on guideline_pages
                for each row execute function guideline_pages_refuse_self_publish();

            alter table guideline_pages enable row level security;
            alter table guideline_pages force row level security;
            create policy workspace_isolation on guideline_pages
                using (workspace_id = current_workspace_id())
                with check (workspace_id = current_workspace_id());
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('guideline_pages');

        DB::statement('drop function if exists guideline_pages_refuse_self_publish()');
    }
};
