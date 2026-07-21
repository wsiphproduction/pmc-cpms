<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Notification;
use App\Models\ProjectNtp;
use App\Models\ProjectRfq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class NtpReviewController extends Controller
{
    /**
     * List NTPs awaiting the department user's review.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Show the full history — records for review, issued, and rejected — not
        // just those pending review. Ordered so pending-review surfaces first.
        $query = ProjectNtp::with(['project.projectRequest', 'creator', 'reviewer', 'rfq.items'])
            ->orderByRaw("CASE WHEN status = 'pending_review' THEN 0 ELSE 1 END")
            ->latest();

        // A department user only reviews NTPs on the projects they requested.
        // Admins can see every NTP.
        if (!$user->hasRole('admin')) {
            $query->whereHas('project.projectRequest', fn ($q) => $q->where('requester_id', $user->id));
        }

        return Inertia::render('ntp-reviews/index', [
            'ntps' => $query->get()->map(fn (ProjectNtp $ntp) => $this->rowData($ntp))->values(),
        ]);
    }

    public function approve(Request $request, ProjectNtp $ntp): RedirectResponse
    {
        $this->authorizeReviewer($request, $ntp);
        abort_unless($ntp->status === 'pending_review', 422, 'This NTP is no longer pending review.');

        $ntp->update([
            'status'      => 'issued',
            'issued_date' => now()->toDateString(),
            'issued_by'   => $ntp->created_by, // the engineer who prepared it
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Award the linked RFQ. The project budget is set manually via the
        // Project Cost field, so issuing an NTP no longer changes it.
        if ($ntp->project_rfq_id) {
            ProjectRfq::where('id', $ntp->project_rfq_id)->update(['status' => 'awarded']);
        }

        $project = $ntp->project;

        AuditTrail::log(
            "NTP {$ntp->ntp_no} approved and issued after department review",
            $project,
            array_filter(['module' => 'NTP', 'type' => 'update', 'rfq_id' => $ntp->project_rfq_id])
        );

        if ($ntp->created_by) {
            Notification::notify(
                $ntp->created_by,
                "NTP {$ntp->ntp_no} was approved and issued by the department user.",
                route('projects.hub.ntp', $project->id, absolute: false)
            );
        }

        return back()->with('success', "NTP {$ntp->ntp_no} approved and issued.");
    }

    public function reject(Request $request, ProjectNtp $ntp): RedirectResponse
    {
        $this->authorizeReviewer($request, $ntp);
        abort_unless($ntp->status === 'pending_review', 422, 'This NTP is no longer pending review.');

        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $ntp->update([
            'status'         => 'rejected',
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
            'review_remarks' => $data['remarks'] ?? null,
        ]);

        AuditTrail::log(
            "NTP {$ntp->ntp_no} rejected during department review",
            $ntp->project,
            array_filter(['module' => 'NTP', 'type' => 'update', 'rfq_id' => $ntp->project_rfq_id])
        );

        if ($ntp->created_by) {
            $reason = !empty($data['remarks']) ? " Reason: {$data['remarks']}" : '';
            Notification::notify(
                $ntp->created_by,
                "NTP {$ntp->ntp_no} was rejected by the department user.{$reason}",
                route('projects.hub.ntp', $ntp->project->id, absolute: false)
            );
        }

        return back()->with('success', "NTP {$ntp->ntp_no} rejected.");
    }

    /**
     * Only the project's department user (requester) — or an admin — may review.
     */
    private function authorizeReviewer(Request $request, ProjectNtp $ntp): void
    {
        $user = $request->user();
        if ($user->hasRole('admin')) {
            return;
        }
        abort_unless($ntp->project->projectRequest?->requester_id === $user->id, 403);
    }

    private function rowData(ProjectNtp $ntp): array
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
