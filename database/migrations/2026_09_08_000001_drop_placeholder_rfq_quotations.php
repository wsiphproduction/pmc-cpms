<?php

use App\Models\ProjectRfqQuotation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every RFQ used to be dispatched with an empty "Original quotation" attached,
 * so the vendor's first offer would have somewhere to land. The portal made it
 * redundant — the supplier's quotation arrives as a row of its own — leaving
 * every RFQ showing a blank final offer beside the real one.
 *
 * Dispatch no longer creates it. This clears out the ones already sitting in
 * the data, taking only rows that are demonstrably untouched: the label the
 * seed gave them, no line items, and not one field of the form filled in. A
 * placeholder the team typed into is a real quotation and is left alone.
 */
return new class extends Migration
{
    /** The form fields; all null means nobody ever opened this quotation. */
    private const BODY = [
        'scope_of_work', 'terms_conditions', 'inclusions', 'exclusions',
        'duration_days', 'quotation_file',
    ];

    public function up(): void
    {
        $ids = DB::table('project_rfq_quotations')
            ->where('origin', ProjectRfqQuotation::ORIGIN_STAFF)
            ->where('label', 'Original quotation')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('project_rfq_items')
                ->whereColumn('project_rfq_items.project_rfq_quotation_id', 'project_rfq_quotations.id'))
            ->where(function ($q) {
                foreach (self::BODY as $field) {
                    $q->whereNull($field);
                }
            })
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // Which RFQs lose their final offer, so a real one can take its place.
        $orphaned = DB::table('project_rfq_quotations')
            ->whereIn('id', $ids)->where('is_final', true)
            ->pluck('project_rfq_id');

        DB::table('project_rfq_quotations')->whereIn('id', $ids)->delete();

        // Hand the final flag to the latest real offer, sent or acknowledged.
        // Wider than the bar the hub now holds new awards to (received only),
        // deliberately: an RFQ whose only offer is still merely submitted is
        // better pinned to it than left mirroring the placeholder's blanks.
        foreach ($orphaned as $rfqId) {
            $replacement = DB::table('project_rfq_quotations')
                ->where('project_rfq_id', $rfqId)
                ->whereIn('status', [ProjectRfqQuotation::STATUS_SUBMITTED, ProjectRfqQuotation::STATUS_RECEIVED])
                ->orderByDesc('seq')
                ->value('id');

            if ($replacement === null) {
                continue;
            }

            DB::table('project_rfq_quotations')->where('id', $replacement)->update(['is_final' => true]);

            ProjectRfqQuotation::find($replacement)?->rfq?->syncFromFinalQuotation();
        }
    }

    public function down(): void
    {
        // The placeholders held nothing worth restoring.
    }
};
