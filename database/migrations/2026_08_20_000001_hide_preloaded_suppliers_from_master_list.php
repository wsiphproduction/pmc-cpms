<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Start PMD's supplier list empty.
     *
     * The `source` column was added with a 'pmd' default, which grandfathered
     * every pre-existing row into the list PMD curates by hand. Those rows were
     * loaded wholesale rather than added one-by-one, and they show it: none of
     * them carry a `created_by`. Re-stamp them as imports so they drop out of
     * both the Master Data table and the RFQ supplier dropdown — only the
     * suppliers PMD actually adds are in play. The rows themselves are kept, so
     * adding one back by name promotes the existing record.
     */
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || ! Schema::hasColumn('suppliers', 'source')) {
            return;
        }

        DB::table('suppliers')
            ->where('source', 'pmd')
            ->whereNull('created_by')
            ->update(['source' => 'import']);
    }

    public function down(): void
    {
        // Not reversible: once re-stamped, a pre-loaded row is indistinguishable
        // from a genuine CSV import, so there is nothing safe to promote back.
    }
};
