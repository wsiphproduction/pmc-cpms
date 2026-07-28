<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The weekly report form captures a site checklist and up to three
     * issue/corrective-action rows that previously had nowhere to live. Both are
     * small, read-mostly, and always fetched with the report, so they are stored
     * as JSON on the report itself rather than in child tables.
     */
    public function up(): void
    {
        Schema::table('project_weekly_reports', function (Blueprint $table) {
            $table->json('checklist')->nullable()->after('progress_updates');
            $table->json('issues')->nullable()->after('checklist');
        });
    }

    public function down(): void
    {
        Schema::table('project_weekly_reports', function (Blueprint $table) {
            $table->dropColumn(['checklist', 'issues']);
        });
    }
};
