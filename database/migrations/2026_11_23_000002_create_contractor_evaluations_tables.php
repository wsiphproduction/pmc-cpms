<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contractor Performance Evaluation, project level.
 *
 * A project is evaluated per period (monthly, quarterly, upon completion…).
 * Each evaluation rates all eight categories of the PMC framework — one row
 * per category, holding that form's criterion ratings — and is signed off
 * Engineer → PMD Assistant Manager → PMD Department Manager through the
 * shared approval_steps chain. `supplier_id` links it to the contractor's
 * Master Data record, so project results can be consolidated per contractor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('control_no', 30)->unique();
            $table->string('period_type', 20);
            $table->string('period_label', 100);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('contractor_name');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            // Out of 100; null until every category is fully rated.
            $table->decimal('total_score', 6, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('return_remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('contractor_evaluation_categories', function (Blueprint $table) {
            $table->id();
            // Named explicitly: the default name overruns MySQL's 64-character limit.
            $table->foreignId('contractor_evaluation_id')
                ->constrained('contractor_evaluations', 'id', 'contractor_eval_categories_eval_fk')
                ->cascadeOnDelete();
            $table->string('category', 30);
            // criterion key => rating (1–5)
            $table->json('ratings')->nullable();
            // Out of the category's weight; null until every criterion is rated.
            $table->decimal('score', 6, 2)->nullable();
            $table->text('comments')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->timestamps();

            $table->unique(['contractor_evaluation_id', 'category'], 'contractor_eval_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_evaluation_categories');
        Schema::dropIfExists('contractor_evaluations');
    }
};
