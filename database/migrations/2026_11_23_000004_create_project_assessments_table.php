<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Operations Hub documents with an electronic approver process — Safety
 * Risk Assessment, Geological Assessment, Billing Validation Report, and the
 * sign-off of the completion certificates. One table, keyed by `type`; each
 * type's form and signing chain live in config/project_assessments.php, and
 * the chain itself in approval_steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('control_no', 30)->unique();
            $table->string('title')->nullable();
            $table->json('data')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('return_remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'type']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_assessments');
    }
};
