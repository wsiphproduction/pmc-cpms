<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RFQ dispatch records
        Schema::create('project_rfqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('contractor_name');
            $table->date('sent_date');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('scope_of_work')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->text('inclusions')->nullable();
            $table->text('exclusions')->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        // Quotation line items per RFQ
        Schema::create('project_rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_rfq_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('seq');
            $table->string('description')->nullable();
            $table->decimal('qty', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->timestamps();
        });

        // Notice to Proceed records
        Schema::create('project_ntps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('ntp_no', 40)->unique();
            $table->string('contractor_name');
            $table->foreignId('project_rfq_id')->nullable()->constrained('project_rfqs')->nullOnDelete();
            $table->date('baseline_start');
            $table->date('baseline_end');
            $table->decimal('approved_cost', 15, 2)->default(0);
            $table->date('issued_date');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('project_id');
        });

        // Permit / compliance documents
        Schema::create('project_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('doc_type', 100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('project_id');
        });

        // Files attached to each permit
        Schema::create('project_permit_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_permit_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('path', 500);
            $table->string('mime_type', 80)->nullable();
            $table->timestamps();
        });

        // Variation Order Forms
        Schema::create('project_variation_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('vo_no', 40)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->date('submitted_date');
            $table->date('approved_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        // Quality Plan & Procedures documents
        Schema::create('project_quality_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('doc_type', 100);
            $table->string('file_path', 500);
            $table->string('filename');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('project_id');
        });

        // Material Test Reports
        Schema::create('project_mtr_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('material_type', 100);
            $table->date('test_date');
            $table->string('file_path', 500);
            $table->string('filename');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('project_id');
        });

        // Request for Payment / Billing records
        Schema::create('project_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('stmt_no', 40)->unique();
            $table->string('billing_type', 50);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedTinyInteger('progress_pct')->default(0);
            $table->text('summary')->nullable();
            $table->text('remarks')->nullable();
            $table->string('recommendation', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('file_path', 500)->nullable();
            $table->string('filename', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        // Input Other Cost / Actual Cost entries (IOC + ACR)
        Schema::create('project_ioc_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('file_path', 500)->nullable();
            $table->string('filename', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('project_id');
        });

        // Weekly Progress Reports (PSR)
        Schema::create('project_weekly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('week_code', 20);
            $table->unsignedTinyInteger('completion_pct')->default(0);
            $table->text('identified_issues')->nullable();
            $table->text('progress_updates')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('filename', 255)->nullable();
            $table->date('submitted_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'submitted_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_weekly_reports');
        Schema::dropIfExists('project_ioc_items');
        Schema::dropIfExists('project_billings');
        Schema::dropIfExists('project_mtr_docs');
        Schema::dropIfExists('project_quality_docs');
        Schema::dropIfExists('project_variation_orders');
        Schema::dropIfExists('project_permit_files');
        Schema::dropIfExists('project_permits');
        Schema::dropIfExists('project_ntps');
        Schema::dropIfExists('project_rfq_items');
        Schema::dropIfExists('project_rfqs');
    }
};
