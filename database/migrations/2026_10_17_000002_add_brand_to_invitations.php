<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inviting a partner (an agency, a printer) straight onto one brand
 * (THI-38): the invitation carries the brand and the role they get on it, so
 * they see that brand and nothing else from the moment they join.
 *
 * Only adds nullable columns, so the previous release still runs. The link
 * to the brand includes the workspace, like every other link between
 * company tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->uuid('brand_id')->nullable();
            $table->string('brand_role')->nullable();

            $table->foreign(['workspace_id', 'brand_id'])->references(['workspace_id', 'id'])->on('brands')->cascadeOnDelete();
        });

        // Partners are never Brand managers, so an invitation cannot make one.
        DB::statement("alter table invitations add constraint invitations_brand_role_check check (brand_role is null or brand_role in ('editor', 'viewer'))");
        DB::statement('alter table invitations add constraint invitations_brand_pair_check check ((brand_id is null) = (brand_role is null))');
    }

    public function down(): void
    {
        DB::statement('alter table invitations drop constraint if exists invitations_brand_pair_check');
        DB::statement('alter table invitations drop constraint if exists invitations_brand_role_check');

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign(['workspace_id', 'brand_id']);
            $table->dropColumn(['brand_id', 'brand_role']);
        });
    }
};
