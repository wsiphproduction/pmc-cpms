<?php

namespace App\Support;

use App\Models\ProjectNtp;
use Illuminate\Support\Facades\Storage;

/**
 * The NTP payload shown on a review screen — the department user's NTP Reviews
 * page and the PMD approvals portal render the same card, so they share one
 * shape. Carries the full quotation detail so a reviewer sees exactly what they
 * are signing.
 */
class NtpPresenter
{
    public static function row(ProjectNtp $ntp): array
    {
        $rfq = $ntp->rfq;

        return [
            'id'             => $ntp->id,
            'ntp_no'         => $ntp->ntp_no,
            'contractor'     => $ntp->contractor_name,
            'status'         => $ntp->status,
            'baseline_start' => optional($ntp->baseline_start)->format('M d, Y'),
            'baseline_end'   => optional($ntp->baseline_end)->format('M d, Y'),
            'approved_cost'  => (float) $ntp->approved_cost,
            'submitted_at'   => optional($ntp->created_at)->format('M d, Y h:i A'),
            'prepared_by'    => $ntp->creator->name ?? '—',
            'issued_date'    => optional($ntp->issued_date)->format('M d, Y'),
            'reviewed_by'    => $ntp->reviewer->name ?? null,
            'reviewed_at'    => optional($ntp->reviewed_at)->format('M d, Y h:i A'),
            'review_remarks' => $ntp->review_remarks,
            'scope_of_work'  => $rfq->scope_of_work ?? null,
            'approvals'      => $ntp->approvalTimeline(),
            'awaiting_role'  => $ntp->currentApprovalRole(),
            'project'        => [
                'id'         => $ntp->project->id,
                'project_no' => $ntp->project->project_no,
                'title'      => $ntp->project->title,
            ],
            // Full quotation detail from the linked RFQ so the reviewer sees
            // exactly what they are approving.
            'rfq'            => $rfq ? [
                'due_date'       => optional($rfq->due_date)->format('M d, Y'),
                'sent_date'      => optional($rfq->sent_date)->format('M d, Y'),
                'duration_days'  => $rfq->duration_days,
                'terms'          => $rfq->terms_conditions,
                'inclusions'     => $rfq->inclusions,
                'exclusions'     => $rfq->exclusions,
                'quotation_file' => $rfq->quotation_file ? Storage::disk('public')->url($rfq->quotation_file) : null,
                'grand_total'    => (float) $rfq->items->sum('total_cost'),
                'items'          => $rfq->items->map(fn ($item) => [
                    'seq'        => $item->seq,
                    'description'=> $item->description,
                    'qty'        => $item->qty !== null ? (float) $item->qty : null,
                    'unit'       => $item->unit,
                    'unit_cost'  => $item->unit_cost !== null ? (float) $item->unit_cost : null,
                    'total_cost' => $item->total_cost !== null ? (float) $item->total_cost : null,
                ])->values(),
            ] : null,
        ];
    }
}
