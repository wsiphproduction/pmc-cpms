<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\ContractorFinding;
use App\Models\Notification;
use App\Support\ContractorFindings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The contractor's answer to a non-compliance record (framework §9,
 * "Contractor response").
 *
 * Contractors have no accounts. The link emailed to them carries the record's
 * unguessable portal token, and holding it is the whole of the authorisation —
 * so everything here is scoped to that one record, and the contractor sees
 * only the finding and their own response, never PMD's internal evidence.
 */
class ContractorFindingPortalController extends Controller
{
    public function show(string $token): Response
    {
        $finding = $this->resolve($token);
        $project = $finding->project;

        return Inertia::render('contractor-finding/index', [
            'finding' => [
                'control_no'        => $finding->control_no,
                'contractor_name'   => $finding->contractor_name,
                'category'          => $finding->categoryLabel(),
                'deficiency'        => $finding->deficiency,
                'corrective_action' => $finding->corrective_action,
                'date_identified'   => $finding->date_identified->format('F d, Y'),
                'target_date'       => $finding->target_date?->format('F d, Y'),
                'status'            => $finding->status,
                'status_label'      => $finding->statusLabel(),
                'contractor_response' => $finding->contractor_response,
                'responded_at'      => $finding->responded_at?->format('F d, Y h:i A'),
                'response_files'    => $finding->attachments->where('type', ContractorFinding::FILE_RESPONSE)
                    ->map(fn ($a) => ['id' => $a->id, 'filename' => $a->filename, 'url' => $a->url])->values(),
                'closure_date'      => $finding->closure_date?->format('F d, Y'),
                'closure_remarks'   => $finding->closure_remarks,
                'token'             => $finding->portal_token,
            ],
            'project' => [
                'project_no' => $project->project_no,
                'title'      => $project->title,
                'site'       => $project->site,
            ],
        ]);
    }

    public function respond(Request $request, string $token): RedirectResponse
    {
        $finding = $this->resolve($token);
        abort_if($finding->isClosed(), 422, 'This record has been closed and no longer takes responses.');

        $data = $request->validate([
            'contractor_response' => ['required', 'string', 'max:5000'],
            'files'               => ['nullable', 'array', 'max:20'],
            'files.*'             => ['file', 'max:20480', ContractorFindings::FILE_RULE],
        ], [
            'contractor_response.required' => 'Please write your explanation or comments.',
            'files.*.max'                  => 'Each file must be 20 MB or smaller.',
            'files.*.mimes'                => 'Attach PDF, image, Word, Excel or PowerPoint files.',
        ]);

        $finding->update([
            'contractor_response'  => $data['contractor_response'],
            'responded_at'         => now(),
            'response_recorded_by' => null,
            'status'               => ContractorFinding::STATUS_RESPONDED,
        ]);
        ContractorFindings::attach($finding, $request->file('files') ?? [], ContractorFinding::FILE_RESPONSE);

        $project = $finding->project;

        AuditTrail::log("{$finding->contractor_name} responded to non-compliance record {$finding->control_no}", $project, ['module' => 'Non-Compliance', 'type' => 'update']);

        // Tell whoever raised it, and the project's engineer.
        $recipients = collect([$finding->created_by, $project->project_manager_id, $project->created_by])->filter()->unique()->values();
        if ($recipients->isNotEmpty()) {
            Notification::notify(
                $recipients,
                "{$finding->contractor_name} responded to non-compliance record {$finding->control_no} on project {$project->project_no}.",
                route('projects.hub.ncr', $project->id, absolute: false)
            );
        }

        return back()->with('success', 'Thank you — your response has been sent to the Project Management Department.');
    }

    private function resolve(string $token): ContractorFinding
    {
        $finding = ContractorFinding::where('portal_token', $token)->with('project', 'attachments')->first();

        abort_if($finding === null || $finding->project === null, 404);

        return $finding;
    }
}
