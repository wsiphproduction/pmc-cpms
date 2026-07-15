<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_requests', function (Blueprint $table) {
            // Remembers the status a request had right before a Project Engineer's
            // comment put it ON HOLD, so it can be restored when the hold is lifted.
            $table->string('status_before_hold')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('project_requests', function (Blueprint $table) {
            $table->dropColumn('status_before_hold');
        });
    }
};
