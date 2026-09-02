<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Suppliers fill their quotations in the system itself rather than mailing a
 * spreadsheet back, and they do it without an account: the RFQ email carries a
 * link holding an unguessable `portal_token`, and that token is what stands in
 * for a login.
 *
 * A quotation therefore gains a lifecycle. The supplier works on a `draft`,
 * `submitted` hands it to the project team, and `received` is the team
 * acknowledging it — the point at which the supplier can no longer change it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_rfqs', function (Blueprint $table) {
            $table->string('portal_token', 64)->nullable()->after('recipient_email');
        });

        // Existing RFQs need a token too, or their suppliers could never reach
        // the portal from a re-sent email.
        foreach (DB::table('project_rfqs')->pluck('id') as $id) {
            DB::table('project_rfqs')->where('id', $id)->update(['portal_token' => Str::random(48)]);
        }

        Schema::table('project_rfqs', function (Blueprint $table) {
            $table->unique('portal_token');
        });

        Schema::table('project_rfq_quotations', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('label');
            // 'staff' for one the project team typed in, 'supplier' for one
            // filled in through the portal.
            $table->string('origin', 20)->default('staff')->after('status');
            $table->timestamp('submitted_at')->nullable()->after('is_final');
            $table->timestamp('received_at')->nullable()->after('submitted_at');
            // Not a foreign key: `created_by` already points at users, and SQL
            // Server refuses a second nullable cascade path into this table.
            $table->unsignedBigInteger('received_by')->nullable()->after('received_at');

            $table->index(['project_rfq_id', 'status']);
        });

        // Everything already on file was entered by the project team, so it is
        // in hand — received, and never waiting on a supplier.
        DB::table('project_rfq_quotations')->update([
            'status'      => 'received',
            'origin'      => 'staff',
            'received_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('project_rfq_quotations', function (Blueprint $table) {
            $table->dropIndex(['project_rfq_id', 'status']);
            $table->dropColumn(['status', 'origin', 'submitted_at', 'received_at', 'received_by']);
        });

        Schema::table('project_rfqs', function (Blueprint $table) {
            $table->dropUnique(['portal_token']);
            $table->dropColumn('portal_token');
        });
    }
};
