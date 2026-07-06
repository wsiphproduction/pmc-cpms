<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('reference_no', 100)->nullable();
            $table->string('sub_project_title', 191)->nullable();
            $table->string('classification', 100)->nullable();

            // Planning schedule
            $table->date('plan_baseline_start')->nullable();
            $table->date('plan_baseline_end')->nullable();
            $table->date('plan_actual_start')->nullable();
            $table->date('plan_actual_end')->nullable();

            // Construction schedule
            $table->date('con_baseline_start')->nullable();
            $table->date('con_baseline_end')->nullable();
            $table->date('con_actual_start')->nullable();
            $table->date('con_actual_end')->nullable();

            $table->string('contractor', 191)->nullable();

            // Cost
            $table->decimal('baseline_amount', 15, 2)->nullable();
            $table->decimal('actual_amount', 15, 2)->nullable();
            $table->string('payment_status', 100)->nullable();
            $table->string('completion_status', 100)->nullable()->default('Finished');

            // Certificate dates
            $table->date('request_date')->nullable();
            $table->date('date_prepared')->nullable();
            $table->date('issued_on')->nullable();

            // Owner-side signatories (PMD-side comes from Settings)
            $table->string('received_by', 191)->nullable();       // Project Owner Representative
            $table->string('accepted_by', 191)->nullable();       // Project Owner Division Manager
            $table->string('acknowledged_by', 191)->nullable();   // Project Owner – Department Manager

            $table->json('photos')->nullable();

            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_completions');
    }
};
