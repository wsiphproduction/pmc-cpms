<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * progress_pct was a tinyint while everything around it treats the percentage
 * as fractional: the form input steps in hundredths, the request validates it
 * as numeric, and the model casts it decimal:2. On SQL Server that mismatch is
 * a hard failure rather than a rounding — billing 5,000 against a large
 * contract works out to 0.05% and the insert dies converting '0.05' to tinyint.
 *
 * completion_pct and completion_percent stay tinyint: those are validated as
 * integers, so the column matches the intent there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_billings', function (Blueprint $table) {
            $table->decimal('progress_pct', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_billings', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_pct')->nullable()->change();
        });
    }
};
