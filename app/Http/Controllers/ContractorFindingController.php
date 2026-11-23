<?php

namespace App\Http\Controllers;

use App\Mail\ContractorFindingNotice;
use App\Models\AuditTrail;
use App\Models\ContractorEvaluation;
use App\Models\ContractorFinding;
use App\Models\Project;
use App\Models\Supplier;
use App\Support\ContractorEvaluationScoring as Scoring;
use App\Support\ContractorFindings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Non-compliance records from the project's side (hub group G): logging a
 * deficiency and its corrective action, sending it to the contractor,
 * recording their response, and closing it once the action is verified.
 */
class ContractorFindingController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validated($request);

        $finding = $project->contractorFindings()->create([
            ...$data,
            'supplier_id' => ContractorEvaluation::supplierIdFor($data['contractor_name']),
            'status'      => ContractorFinding::STATUS_OPEN,
            'created_by'  => $request->user()->id,
        ]);

        ContractorFindings::attach($finding, $request->file('evidence') ?? [], ContractorFinding::FILE_EVIDENCE);

        AuditTrail::log("Non-compliance record {$finding->control_no} logged against {$finding->contractor_name}", $project, ['module' => 'Non-Compliance', 'type' => 'create']);

        return back()->with('success', "Non-compliance record {$finding->control_no} logged.");
    }

    public function update(Request $request, Project $project, ContractorFinding $finding): RedirectResponse
    {
        $this->ensureOwn($project, $finding);
        abort_if($finding->isClosed(), 422, 'Reopen the record before changing it.');

        $data = $this->validated($request);
        $removed = $request->validate(['removed_attachments' => ['nullable', 'array'], 'removed_attachments.*' => ['integer']])['removed_attachments'] ?? [];

        $finding->update([...$data, 'supplier_id' => ContractorEvaluation::supplierIdFor($data['contractor_name'])]);

        if ($removed !== []) {
            $finding->attachments()->where('type', ContractorFinding::FILE_EVIDENCE)->whereKey($removed)->get()
                ->each(fn ($a) => ContractorFindings::purge($a));
        }
        ContractorFindings::attach($finding, $request->file('evidence') ?? [], ContractorFinding::FILE_EVIDENCE);

        AuditTrail::log("Non-compliance record {$finding->control_no} updated", $project, ['module' => 'Non-Compliance', 'type' => 'update']);

        return back()->with('success', "Non-compliance record {$finding->control_no} updated.");
    }

    /** Email the contractor the record and their link to respond. */
    public function send(Request $request, Project $project, ContractorFinding $finding): RedirectResponse
    {
        $this->ensureOwn($project, $finding);
        abort_if($finding->isClosed(), 422, 'This record is already closed.');

        $data = $request->validate(['email' => ['required', 'string', 'max:500']], [
            'email.required' => 'Enter the contractor’s email address.',
        ]);

        $emails = Supplier::parseEmails($data['email']);
        $invalid = array_filter($emails, fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($emails === [] || $invalid !== []) {
            throw ValidationException::withMessages(['email' => 'Enter valid email addresses, separated by commas.']);
        }

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new ContractorFindingNotice($finding));
            } catch (\Throwable $e) {
                Log::error("Non-compliance notice to {$email} could not be queued for {$finding->control_no}: " . $e->getMessage());
            }
        }

        $finding->update(['notified_email' => implode(', ', $emails), 'notified_at' => now()]);

        AuditTrail::log("Non-compliance record {$finding->control_no} sent to the contractor (" . implode(', ', $emails) . ')', $project, ['module' => 'Non-Compliance', 'type' => 'update']);

        return back()->with('success', "Non-compliance record {$finding->control_no} sent to " . implode(', ', $emails) . '.');
    }

    /** The contractor's explanation, received some other way and entered by PMD. */
    public function recordResponse(Request $request, Project $project, ContractorFinding $finding): RedirectResponse
    {
        $this->ensureOwn($project, $finding);
        abort_if($finding->isClosed(), 422, 'This record is already closed.');

        $data = $request->validate([
            'contractor_response' => ['required', 'string', 'max:5000'],
            'files'               => ['nullable', 'array', 'max:20'],
            'files.*'             => ['file', 'max:20480', ContractorFindings::FILE_RULE],
        ], ['contractor_response.required' => 'Enter the contractor’s response.']);

        $finding->update([
            'contractor_response'  => $data['contractor_response'],
            'responded_at'         => now(),
            'response_recorded_by' => $request->user()->id,
            'status'               => ContractorFinding::STATUS_RESPONDED,
        ]);
        ContractorFindings::attach($finding, $request->file('files') ?? [], ContractorFinding::FILE_RESPONSE);

        AuditTrail::log("Contractor response to {$finding->control_no} recorded by {$request->user()->name}", $project, ['module' => 'Non-Compliance', 'type' => 'update']);

        return back()->with('success', "Response to {$finding->control_no} recorded.");
    }

    /** The corrective action is verified done: close the record. */
    public function close(Request $request, Project $project, ContractorFinding $finding): RedirectResponse
    {
        $this->ensureOwn($project, $finding);
        abort_if($finding->isClosed(), 422, 'This record is already closed.');

        $data = $request->validate([
            'closure_date'    => ['required', 'date', 'after_or_equal:' . $finding->date_identified->toDateString(), 'before_or_equal:today'],
            'closure_remarks' => ['required', 'string', 'max:5000'],
            'files'           => ['nullable', 'array', 'max:20'],
            'files.*'         => ['file', 'max:20480', ContractorFindings::FILE_RULE],
        ], [
            'closure_remarks.required'     => 'Say how the corrective action was verified.',
            'closure_date.after_or_equal'  => 'The closure date cannot be before the date identified.',
            'closure_date.before_or_equal' => 'The closure date cannot be in the future.',
        ]);

        $finding->update([
            'closure_date'    => $data['closure_date'],
            'closure_remarks' => $data['closure_remarks'],
            'closed_by'       => $request->user()->id,
            'status'          => ContractorFinding::STATUS_CLOSED,
        ]);
        ContractorFindings::attach($finding, $request->file('files') ?? [], ContractorFinding::FILE_CLOSURE);

        AuditTrail::log("Non-compliance record {$finding->control_no} closed by {$request->user()->name}", $project, ['module' => 'Non-Compliance', 'type' => 'update']);

        return back()->with('success', "Non-compliance record {$finding->control_no} closed.");
    }

    public function reopen(Request $request, Project $project, ContractorFinding $finding): RedirectResponse
    {
        $this->ensureOwn($project, $finding);
        abort_unless($finding->isClosed(), 422, 'This record is not closed.');

        $finding->update([
            'status'          => $finding->contractor_response ? ContractorFinding::STATUS_RESPONDED : ContractorFinding::STATUS_OPEN,
            'closure_date'    => null,
            'closure_remarks' => null,
            'closed_by'       => null,
        ]);

        AuditTrail::log("Non-compliance record {$finding->control_no} reopened by {$request->user()->name}", $project, ['module' => 'Non-Compliance', 'type' => 'update']);

        return back()->with('success', "Non-compliance record {$finding->control_no} reopened.");
    }

    public function destroy(Project $project, ContractorFinding $finding): RedirectResponse
    {
        $this->ensureOwn($project, $finding);
        abort_if($finding->isClosed(), 422, 'A closed record is kept for the contractor’s history.');

        AuditTrail::log("Non-compliance record {$finding->control_no} deleted", $project, ['module' => 'Non-Compliance', 'type' => 'delete']);
        $finding->attachments->each(fn ($a) => ContractorFindings::purge($a));
        $finding->delete();

        return back()->with('success', "Non-compliance record {$finding->control_no} deleted.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contractor_name'   => ['required', 'string', 'max:255'],
            'category'          => ['nullable', Rule::in(array_keys(Scoring::categories()))],
            'deficiency'        => ['required', 'string', 'max:5000'],
            'date_identified'   => ['required', 'date', 'before_or_equal:today'],
            'corrective_action' => ['required', 'string', 'max:5000'],
            'target_date'       => ['nullable', 'date', 'after_or_equal:date_identified'],
            'evidence'          => ['nullable', 'array', 'max:20'],
            'evidence.*'        => ['file', 'max:20480', ContractorFindings::FILE_RULE],
        ], [
            'deficiency.required'        => 'Describe the deficiency.',
            'corrective_action.required' => 'State the corrective action required.',
            'target_date.after_or_equal' => 'The target date cannot be before the date identified.',
        ]);
    }

    private function ensureOwn(Project $project, ContractorFinding $finding): void
    {
        abort_unless((int) $finding->project_id === (int) $project->id, 404);
    }
}
