<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The RFQ hub used to carry a row off Pending with an "Accept" button, which
 * said the same thing the quotation flow already says — an offer has been
 * marked received and set as final. The button is gone; settling on a final
 * quotation now moves the row along by itself.
 *
 * Rows that were settled under the old flow but never accepted would be left
 * with no way forward, since the button that moved them no longer exists.
 * This walks them on, matching what the code now does on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_rfqs')
            ->where('status', 'pending')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('project_rfq_quotations')
                ->whereColumn('project_rfq_quotations.project_rfq_id', 'project_rfqs.id')
                ->where('project_rfq_quotations.is_final', true))
            ->update(['status' => 'submitted']);
    }

    public function down(): void
    {
        // Which rows were pending beforehand is not recorded, and 'submitted' is
        // the state the hub would have put them in anyway.
    }
};
