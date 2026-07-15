<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_variation_orders', function (Blueprint $table) {
            // Additional project duration (in calendar days) introduced by the variation.
            $table->integer('duration_days')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('project_variation_orders', function (Blueprint $table) {
            $table->dropColumn('duration_days');
        });
    }
};
