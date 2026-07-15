<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_weekly_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('project_ntp_id')->nullable()->after('project_id');
            $table->foreign('project_ntp_id')->references('id')->on('project_ntps')->noActionOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_weekly_reports', function (Blueprint $table) {
            $table->dropForeign(['project_ntp_id']);
            $table->dropColumn('project_ntp_id');
        });
    }
};
