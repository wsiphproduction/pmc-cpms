<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Once the Division Manager signs, the NTP is issued and the contractor has to
 * be told. Recording when that mail went out keeps the hub honest about whether
 * the vendor has actually been informed, and lets it be re-sent knowingly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_ntps', function (Blueprint $table) {
            $table->timestamp('vendor_notified_at')->nullable()->after('issued_by');
        });
    }

    public function down(): void
    {
        Schema::table('project_ntps', function (Blueprint $table) {
            $table->dropColumn('vendor_notified_at');
        });
    }
};
