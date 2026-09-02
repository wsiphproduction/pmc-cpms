<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRfq;
use App\Models\Setting;
use App\Support\PdfRenderer;
use Illuminate\Http\Response;

/**
 * Renders the controlled PMD forms as PDFs for preview in a new tab.
 *
 * The forms are built here from the record rather than from HTML the browser
 * sends up: a printed NTP carries approval stamps, and only the server may
 * decide what those say. Access is gated the same way the hub is.
 */
class PrintController extends Controller
{
    public function __construct(private readonly PdfRenderer $pdf) {}

    /** Request for Quotation — PMD-PRJ-FRM-03. */
    public function rfq(Project $project, ProjectRfq $rfq): Response
    {
        abort_unless((int) $rfq->project_id === (int) $project->id, 404);

        $rfq->load('items');

        return $this->pdf->stream('print.rfq', [
            'project'      => $project,
            'rfq'          => $rfq,
            'signatories'  => $this->signatories($project),
            'subProjectNo' => $project->parent_id ? $project->project_no : null,
        ], "RFQ {$project->project_no} {$rfq->contractor_name}");
    }

    /** Notice to Proceed — PMD-PRJ-FRM-04. */
    public function ntp(Project $project, ProjectNtp $ntp): Response
    {
        abort_unless((int) $ntp->project_id === (int) $project->id, 404);

        $ntp->load('rfq.items', 'creator', 'approvals.user');

        return $this->pdf->stream('print.ntp', [
            'project'      => $project,
            'ntp'          => $ntp,
            'signatories'  => $this->signatories($project),
            'subProjectNo' => $project->parent_id ? $project->project_no : null,
        ], "NTP {$ntp->ntp_no}");
    }

    /** Project Completion and Acceptance Certificate — PMD-PRJ-FRM-06. */
    public function acceptance(Project $project): Response
    {
        return $this->pdf->stream('print.acceptance', [
            'project'     => $project,
            'completion'  => $project->completion,
            'signatories' => $this->signatories($project),
        ], "Acceptance Certificate {$project->project_no}");
    }

    /** Project Completion Summary — PMD-PRJ-FRM-12. */
    public function completionSummary(Project $project): Response
    {
        return $this->pdf->stream('print.completion-summary', [
            'project'     => $project,
            'completion'  => $project->completion,
            'signatories' => $this->signatories($project),
        ], "Completion Summary {$project->project_no}");
    }

    /**
     * The names printed under each signature block. `prepared_by` is the
     * engineer who registered the project; the rest are configured offices.
     *
     * @return array<string, string>
     */
    private function signatories(Project $project): array
    {
        return [
            'prepared_by'           => $project->creator?->name ?? '',
            'pmd_assistant_manager' => (string) Setting::get('signatory_pmd_assistant_manager', ''),
            'pmd_manager'           => (string) Setting::get('signatory_pmd_manager', ''),
            'ecs_division_manager'  => (string) Setting::get('signatory_ecs_division_manager', ''),
            'operations_director'   => (string) Setting::get('signatory_operations_director', ''),
        ];
    }
}
