<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The languages a brand writes its guidelines in, in order (THI-37). Only
 * adds a column with a default, so existing brands keep working and start
 * with Amharic and English.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            alter table brands
                add column guideline_languages jsonb not null default '["am", "en"]'
                constraint brands_guideline_languages_is_list check (jsonb_typeof(guideline_languages) = 'array' and jsonb_array_length(guideline_languages) between 1 and 8)
            SQL);
    }

    public function down(): void
    {
        DB::statement('alter table brands drop column if exists guideline_languages');
    }
};
