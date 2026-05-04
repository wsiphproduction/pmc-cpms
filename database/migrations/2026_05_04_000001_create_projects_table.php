<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_no', 40)->unique();
            $table->string('title');
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('project_manager_name')->nullable();
            $table->string('site');
            $table->string('asset_id');
            $table->string('class_name');
            $table->string('priority');
            $table->string('status_key')->default('PLANNING');
            $table->string('work_force');
            $table->string('wr_no');
            $table->date('wr_date');
            $table->string('dept_owner');
            $table->string('cost_code');
            $table->string('category');
            $table->string('service_type');
            $table->date('deadline');
            $table->string('owner_email')->nullable();
            $table->string('structure_type')->nullable();
            $table->boolean('jip')->default(false);
            $table->boolean('need_civil')->default(false);
            $table->boolean('need_electrical')->default(false);
            $table->boolean('need_mechanical')->default(false);
            $table->text('notes')->nullable();
            $table->decimal('budget_total', 15, 2)->default(0);
            $table->decimal('budget_paid', 15, 2)->default(0);
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status_key', 'site']);
            $table->index(['project_manager_id', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
