<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_ntps', function (Blueprint $table) {
            // Review workflow: a new NTP starts as pending_review and only becomes
            // issued once the project's department user approves it.
            $table->string('status', 20)->default('pending_review')->after('approved_cost');
            // Plain nullable column (no FK) — SQL Server rejects extra cascade paths
            // on this table, and referential integrity here isn't critical.
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('issued_by');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_remarks')->nullable()->after('reviewed_at');
        });

        // Legacy NTPs were issued immediately under the old flow — keep them issued.
        DB::table('project_ntps')->whereNotNull('issued_date')->update(['status' => 'issued']);
    }

    public function down(): void
    {
        Schema::table('project_ntps', function (Blueprint $table) {
            $table->dropColumn(['status', 'reviewed_by', 'reviewed_at', 'review_remarks']);
        });
    }
};
