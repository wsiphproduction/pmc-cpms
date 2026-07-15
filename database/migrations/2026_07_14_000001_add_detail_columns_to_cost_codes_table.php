<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Widen the cost_codes table so a full GL-code CSV (Full_GL_Codes, Division,
     * Cost_Center, Activity, Expense_Description, AGU_PER_CLASS, AGU_PER_STAT,
     * isActive) can be stored in its entirety rather than just code + description.
     */
    public function up(): void
    {
        Schema::table('cost_codes', function (Blueprint $table) {
            $table->string('division', 191)->nullable()->after('description');
            $table->string('cost_center', 191)->nullable()->after('division');
            $table->string('activity', 191)->nullable()->after('cost_center');
            $table->string('expense_description', 500)->nullable()->after('activity');
            $table->string('agu_per_class', 191)->nullable()->after('expense_description');
            $table->string('agu_per_stat', 100)->nullable()->after('agu_per_class');
            $table->boolean('is_active')->default(true)->after('agu_per_stat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cost_codes', function (Blueprint $table) {
            $table->dropColumn([
                'division',
                'cost_center',
                'activity',
                'expense_description',
                'agu_per_class',
                'agu_per_stat',
                'is_active',
            ]);
        });
    }
};
