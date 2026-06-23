<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_requests', function (Blueprint $table) {
            $table->string('costcode', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE project_requests SET costcode = '' WHERE costcode IS NULL");

        Schema::table('project_requests', function (Blueprint $table) {
            $table->string('costcode', 255)->nullable(false)->change();
        });
    }
};
