<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suppliers') && ! Schema::hasColumn('suppliers', 'accredited')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->boolean('accredited')->default(true)->after('company');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('suppliers') && Schema::hasColumn('suppliers', 'accredited')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('accredited');
            });
        }
    }
};
