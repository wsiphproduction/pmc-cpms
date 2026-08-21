<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retention withheld from a billing until the project completes.
 *
 * The rate is stored per billing rather than read back from settings, so that
 * changing the setting later cannot silently restate what was already billed.
 * A null rate means the billing carries no retention at all, which is distinct
 * from a rate of zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_billings', function (Blueprint $table) {
            $table->decimal('retention_pct', 5, 2)->nullable()->after('amount');
            $table->decimal('retention_amount', 15, 2)->default(0)->after('retention_pct');
        });
    }

    public function down(): void
    {
        Schema::table('project_billings', function (Blueprint $table) {
            $table->dropColumn(['retention_pct', 'retention_amount']);
        });
    }
};
