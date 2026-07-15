<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NTPs pending review have no issued date yet — it is set on approval.
        Schema::table('project_ntps', function (Blueprint $table) {
            $table->date('issued_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_ntps', function (Blueprint $table) {
            $table->date('issued_date')->nullable(false)->change();
        });
    }
};
