<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master-data tables that gain an is_active flag. cost_codes already has one
     * (added by 2026_07_14_000001), so it's intentionally excluded here.
     */
    private array $tables = [
        'job_types',
        'job_locations',
        'sites',
        'classes',
        'priorities',
        'statuses',
        'departments',
        'categories',
        'service_types',
        'work_forces',
        'structures',
        'suppliers',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'is_active')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('is_active')->default(true);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_active')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('is_active');
                });
            }
        }
    }
};
