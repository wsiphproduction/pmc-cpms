<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRfq;
use App\Models\Setting;
use App\Models\User;
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

        return $this->ntpForm($project, $ntp);
    }

    /**
     * The issued NTP, opened from the link in the vendor's email.
     *
     * The route's signature is what admits the contractor, and it expires on
     * its own (see NtpIssuedToVendor). Only an issued NTP is ever mailed, so
     * anything else is treated as not found rather than shown half-signed.
     */
    public function vendorNtp(ProjectNtp $ntp): Response
    {
        abort_unless($ntp->status === 'issued', 404);

        return $this->ntpForm($ntp->project, $ntp);
    }

    private function ntpForm(Project $project, ProjectNtp $ntp): Response
    {
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
     * engineer who registered the project; the PMD and division offices are
     * whoever holds that role in the system, falling back to the configured
     * name while the seat is vacant; the rest are configured offices only.
     *
     * @return array<string, string>
     */
    private function signatories(Project $project): array
    {
        $office = fn (string $role, string $setting) => User::holderOf($role) ?? (string) Setting::get($setting, '');

        return [
            'prepared_by'           => $project->creator?->name ?? '',
            'pmd_assistant_manager' => $office(User::ROLE_PMD_ASST_MANAGER, 'signatory_pmd_assistant_manager'),
            'pmd_manager'           => $office(User::ROLE_PMD_DEPT_MANAGER, 'signatory_pmd_manager'),
            'ecs_division_manager'  => $office(User::ROLE_DIVISION_MANAGER, 'signatory_ecs_division_manager'),
            'operations_director'   => (string) Setting::get('signatory_operations_director', ''),
        ];
    }
}
