<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a project counts its days: 'calendar' (every day) or 'working'
     * (Monday to Saturday). Existing projects keep calendar days.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('duration_basis', 20)->default('calendar')->after('deadline');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('duration_basis');
        });
    }
};
