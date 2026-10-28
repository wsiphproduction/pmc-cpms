<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Titles are now stored in capitals on save (see the `title` mutators on
 * Project and ProjectRequest). This brings the rows already encoded into line.
 * Not reversible: the original casing is not kept anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['projects', 'project_requests'] as $table) {
            DB::table($table)->update(['title' => DB::raw('UPPER(TRIM(title))')]);
        }
    }

    public function down(): void
    {
        //
    }
};
