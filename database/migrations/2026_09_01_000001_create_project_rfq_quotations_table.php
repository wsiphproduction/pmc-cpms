<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An RFQ sent to a vendor can collect several quotations (an original offer, a
 * revision, a best-and-final). Each one lives here with its own line items; the
 * one flagged `is_final` is the offer the project runs with.
 *
 * The matching columns on `project_rfqs` are kept as a mirror of whichever
 * quotation is final, so every existing reader (NTP issuance, the printed
 * PMD-PRJ-FRM-03, the hub table, reports) keeps working unchanged.
 */
return new class extends Migration
{
    /** Quotation fields mirrored onto the parent `project_rfqs` row. */
    private const MIRRORED = [
        'due_date', 'scope_of_work', 'terms_conditions',
        'inclusions', 'exclusions', 'duration_days', 'quotation_file',
    ];

    public function up(): void
    {
        Schema::create('project_rfq_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_rfq_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('seq')->default(1);
            $table->string('label')->nullable();
            $table->date('due_date')->nullable();
            $table->text('scope_of_work')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->text('inclusions')->nullable();
            $table->text('exclusions')->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->string('quotation_file')->nullable();
            $table->boolean('is_final')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_rfq_id', 'is_final']);
        });

        // Deliberately not a foreign key: SQL Server rejects the second cascade
        // path into project_rfq_items (it already cascades from project_rfqs).
        // Items are cleaned up with their quotation in ProjectHubController.
        Schema::table('project_rfq_items', function (Blueprint $table) {
            $table->unsignedBigInteger('project_rfq_quotation_id')
                ->nullable()
                ->after('project_rfq_id')
                ->index();
        });

        // Every existing RFQ becomes quotation #1, already final, so nothing in
        // the hub loses its quotation when this ships.
        $now = now();
        foreach (DB::table('project_rfqs')->get() as $rfq) {
            $id = DB::table('project_rfq_quotations')->insertGetId([
                'project_rfq_id' => $rfq->id,
                'seq'            => 1,
                'label'          => 'Original quotation',
                'is_final'       => true,
                'created_by'     => $rfq->created_by,
                'created_at'     => $now,
                'updated_at'     => $now,
                ...collect(self::MIRRORED)->mapWithKeys(fn ($f) => [$f => $rfq->{$f} ?? null])->all(),
            ]);

            DB::table('project_rfq_items')
                ->where('project_rfq_id', $rfq->id)
                ->update(['project_rfq_quotation_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('project_rfq_items', function (Blueprint $table) {
            $table->dropIndex(['project_rfq_quotation_id']);
            $table->dropColumn('project_rfq_quotation_id');
        });

        Schema::dropIfExists('project_rfq_quotations');
    }
};
