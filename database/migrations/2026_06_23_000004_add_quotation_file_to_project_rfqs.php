<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_rfqs', function (Blueprint $table) {
            $table->string('quotation_file', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_rfqs', function (Blueprint $table) {
            $table->dropColumn('quotation_file');
        });
    }
};
