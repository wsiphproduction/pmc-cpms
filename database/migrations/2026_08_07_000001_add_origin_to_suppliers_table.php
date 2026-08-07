<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master Data lists only the suppliers PMD curates itself, so a supplier has
     * to say where it came from. `source` defaults to 'pmd', which also
     * grandfathers every existing row into PMD's list; the CSV importer stamps
     * new rows as 'import' instead. `created_by` records who added it.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'source')) {
                $table->string('source', 20)->default('pmd')->after('company');
            }
            if (! Schema::hasColumn('suppliers', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('is_active')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('suppliers', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
