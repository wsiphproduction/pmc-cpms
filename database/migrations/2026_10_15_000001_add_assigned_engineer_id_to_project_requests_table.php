<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The PMD Department Manager, giving a request its final signature, may name
 * the project engineer who should run it. The project form then opens with
 * that engineer as Project Manager instead of whoever happens to register it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_requests', function (Blueprint $table) {
            $table->foreignId('assigned_engineer_id')->nullable()->after('requester_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_engineer_id');
        });
    }
};
