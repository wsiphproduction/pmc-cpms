<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Production reached a state where `add_supplier_quotation_portal` counted as
 * run while its columns were absent, so every quotation insert died on an
 * unknown `origin` column. This puts the schema back without caring how that
 * happened: each piece is added only if it is genuinely missing, so the
 * migration is a no-op on any database that is already correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('project_rfqs', 'portal_token')) {
            Schema::table('project_rfqs', function (Blueprint $table) {
                $table->string('portal_token', 64)->nullable()->after('recipient_email');
            });

            foreach (DB::table('project_rfqs')->whereNull('portal_token')->pluck('id') as $id) {
                DB::table('project_rfqs')->where('id', $id)->update(['portal_token' => Str::random(48)]);
            }

            Schema::table('project_rfqs', function (Blueprint $table) {
                $table->unique('portal_token');
            });
        }

        // Remembered because the backfill below must only touch rows that
        // predate the column — never quotations already carrying a real status.
        $addedStatus = ! Schema::hasColumn('project_rfq_quotations', 'status');

        Schema::table('project_rfq_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('project_rfq_quotations', 'status')) {
                $table->string('status', 20)->default('draft')->after('label');
            }
            if (! Schema::hasColumn('project_rfq_quotations', 'origin')) {
                $table->string('origin', 20)->default('staff')->after('status');
            }
            if (! Schema::hasColumn('project_rfq_quotations', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('is_final');
            }
            if (! Schema::hasColumn('project_rfq_quotations', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('project_rfq_quotations', 'received_by')) {
                $table->unsignedBigInteger('received_by')->nullable()->after('received_at');
            }
        });

        if (! Schema::hasIndex('project_rfq_quotations', 'project_rfq_quotations_project_rfq_id_status_index')) {
            Schema::table('project_rfq_quotations', function (Blueprint $table) {
                $table->index(['project_rfq_id', 'status']);
            });
        }

        // Same reasoning as the original migration: anything on file before the
        // portal existed was typed in by the project team, so it is in hand.
        if ($addedStatus) {
            DB::table('project_rfq_quotations')->update([
                'status'      => 'received',
                'origin'      => 'staff',
                'received_at' => DB::raw('created_at'),
            ]);
        }
    }

    /**
     * Intentionally empty: every column this touches belongs to
     * `add_supplier_quotation_portal`, and that migration's own `down()` drops
     * them. Dropping them here too would make a rollback fail on the second
     * attempt.
     */
    public function down(): void
    {
    }
};
