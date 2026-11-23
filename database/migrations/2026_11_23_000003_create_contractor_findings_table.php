<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-compliance records (NCR) — framework §9: the deficiency, the date it
 * was identified, the responsible contractor, the corrective action and its
 * closure. A record may come from an approved evaluation's "Required
 * Corrective Actions" or be logged on its own. The contractor answers through
 * a tokened link (they have no account), the same way they quote on RFQs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('control_no', 30)->unique();
            $table->foreignId('contractor_evaluation_id')->nullable()->constrained()->nullOnDelete();
            // The evaluation category it falls under (schedule, quality…), if any.
            $table->string('category', 30)->nullable();
            $table->string('contractor_name');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            $table->text('deficiency');
            $table->date('date_identified');
            $table->text('corrective_action');
            $table->date('target_date')->nullable();
            $table->string('status', 20)->default('open');

            // The contractor's explanation, from the portal or recorded by PMD.
            $table->text('contractor_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('response_recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->date('closure_date')->nullable();
            $table->text('closure_remarks')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('portal_token', 64)->unique();
            $table->string('notified_email')->nullable();
            $table->timestamp('notified_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_findings');
    }
};
