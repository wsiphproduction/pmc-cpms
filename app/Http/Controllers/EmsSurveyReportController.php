<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\EmsSurveyReport;
use App\Models\Project;
use App\Models\User;
use App\Support\EmsReportFlow;
use App\Support\EmsReportInput;
use App\Support\PdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * EMS Survey Reports (PMD-PRJ-FRM-19) from the project's side: the engineer
 * raising one, filling in what they know and sending it to MEPEO, plus the
 * signatures given from For Approval and the MEPEO page, and the printout.
 *
 * What the MEPEO representative fills in lives in MepeoController.
 */
class EmsSurveyReportController extends Controller
{
    public function __construct(private readonly EmsReportFlow $flow) {}

    /**
     * A new report. Its header starts from the project; the engineer may fill
     * in as much of the assessment as they know, and attach supporting
     * documents, or leave it all for MEPEO.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        [$data, $files] = $this->validatedForm($request);

        $report = $project->emsSurveyReports()->create([
            ...$data,
            'control_no' => EmsSurveyReport::nextControlNo(),
            'status'     => EmsSurveyReport::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        EmsReportInput::syncAttachments($report, $files);

        AuditTrail::log("EMS Survey Report {$report->control_no} created", $project, ['module' => 'EMS', 'type' => 'create']);

        return back()->with('success', "EMS Survey Report {$report->control_no} created. Send it to MEPEO to fill out.");
    }

    public function update(Request $request, Project $project, EmsSurveyReport $report): RedirectResponse
    {
        $this->ensureOwnOpenReport($project, $report);

        [$data, $files] = $this->validatedForm($request);

        $report->update($data);
        EmsReportInput::syncAttachments($report, $files);

        AuditTrail::log("EMS Survey Report {$report->control_no} details updated", $project, ['module' => 'EMS', 'type' => 'update']);

        return back()->with('success', "EMS Survey Report {$report->control_no} updated.");
    }

    /** To a MEPEO representative — again, to hand it to another one before they submit. */
    public function send(Request $request, Project $project, EmsSurveyReport $report): RedirectResponse
    {
        $this->ensureOwnOpenReport($project, $report);

        $data = $request->validate([
            'mepeo_rep_id' => ['required', 'integer', Rule::exists('users', 'id')->where(
                fn ($q) => $q->whereNull('deleted_at')->whereIn('id', User::role(User::ROLE_MEPEO_REP)->select('id'))
            )],
            'send_email' => ['boolean'],
        ], [
            'mepeo_rep_id.required' => 'Choose the MEPEO representative to send it to.',
            'mepeo_rep_id.exists'   => 'That user is not a MEPEO representative.',
        ]);

        $rep = User::findOrFail($data['mepeo_rep_id']);

        $this->flow->send($report, $rep, $data['send_email'] ?? true);

        return back()->with('success', "EMS Survey Report {$report->control_no} sent to {$rep->name}.");
    }

    public function destroy(Project $project, EmsSurveyReport $report): RedirectResponse
    {
        $this->ensureOwnOpenReport($project, $report);

        AuditTrail::log("EMS Survey Report {$report->control_no} deleted", $project, ['module' => 'EMS', 'type' => 'delete']);
        $report->attachments->each(fn ($attachment) => EmsReportInput::purge($attachment));
        $report->delete();

        return back()->with('success', "EMS Survey Report {$report->control_no} deleted.");
    }

    public function approve(Request $request, EmsSurveyReport $report): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);

        abort_unless(
            $this->flow->approve($report, $request->user(), $data['remarks'] ?? null),
            403,
            'This report is not awaiting your signature.'
        );

        return back()->with('success', "EMS Survey Report {$report->control_no} signed.");
    }

    public function returnToRep(Request $request, EmsSurveyReport $report): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'max:1000']], [
            'remarks.required' => 'Say what needs revising.',
        ]);

        abort_unless(
            $this->flow->returnToRep($report, $request->user(), $data['remarks']),
            403,
            'This report is not awaiting your signature.'
        );

        return back()->with('success', "EMS Survey Report {$report->control_no} returned to MEPEO.");
    }

    /**
     * The controlled form. Project-side users reach it as they reach the
     * project; MEPEO, which cannot open projects, reaches the reports it works.
     */
    public function print(Request $request, EmsSurveyReport $report, PdfRenderer $pdf): Response
    {
        $user = $request->user();
        $project = $report->project;

        abort_unless(
            $project && ($user->isMepeo() ? $report->isVisibleToMepeo($user) : $user->can('view', $project)),
            403
        );

        $report->load('creator', 'mepeoRep', 'approvals.user');

        return $pdf->stream('print.ems', [
            'project'     => $project,
            'report'      => $report,
            'pmdNames'    => [
                'pmd_asst_manager' => User::holderOf(User::ROLE_PMD_ASST_MANAGER) ?? '',
                'pmd_dept_manager' => User::holderOf(User::ROLE_PMD_DEPT_MANAGER) ?? '',
                'mepeo_manager'    => User::holderOf(User::ROLE_MEPEO_MANAGER) ?? '',
            ],
        ], "EMS Survey Report {$report->control_no}");
    }

    /**
     * Everything on the form but its signatures, and the supporting
     * documents, all checked before any of it is saved.
     *
     * @return array{0: array, 1: array{files: array, removed: int[]}}
     */
    private function validatedForm(Request $request): array
    {
        $header = EmsReportInput::header($request);
        $body = EmsReportInput::body($request);
        $files = EmsReportInput::attachments($request);

        return [[...$header, ...$body], $files];
    }

    /**
     * Only this project's own reports, and only until MEPEO submits: after
     * that the report is in sign-off and its header is fixed.
     */
    private function ensureOwnOpenReport(Project $project, EmsSurveyReport $report): void
    {
        abort_unless((int) $report->project_id === (int) $project->id, 404);
        abort_unless($report->isOpen(), 422, 'This report has already been submitted by MEPEO.');
    }
}
