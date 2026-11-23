<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AuditTrail;
use App\Models\ContractorEvaluation;
use App\Models\ContractorEvaluationCategory;
use App\Models\Notification;
use App\Models\Project;
use App\Models\RosterBreak;
use App\Models\User;
use App\Support\ContractorEvaluationScoring as Scoring;
use App\Support\ContractorFindings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Contractor Performance Evaluation, project level — the Operations Hub's
 * group G. The engineer raises an evaluation for a period, rates each of the
 * eight categories (forms A–H) with their evidence, and submits it; the PMD
 * Assistant Manager reviews it and the PMD Department Manager approves it.
 */
class ContractorEvaluationController extends Controller
{
    private const FILE_RULE = 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx';

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedHeader($request);

        $evaluation = DB::transaction(function () use ($project, $data, $request) {
            $evaluation = $project->contractorEvaluations()->create([
                ...$data,
                'control_no'  => ContractorEvaluation::nextControlNo(),
                'supplier_id' => ContractorEvaluation::supplierIdFor($data['contractor_name']),
                'status'      => ContractorEvaluation::STATUS_DRAFT,
                'created_by'  => $request->user()->id,
            ]);
            $evaluation->ensureCategories();

            return $evaluation;
        });

        AuditTrail::log("Contractor evaluation {$evaluation->control_no} created ({$evaluation->period_label}, {$evaluation->contractor_name})", $project, ['module' => 'Contractor Evaluation', 'type' => 'create']);

        return redirect()
            ->route('projects.hub.eval-schedule', ['project' => $project->id, 'evaluation' => $evaluation->id])
            ->with('success', "Contractor evaluation {$evaluation->control_no} created. Rate each category, then submit it for review.");
    }

    public function update(Request $request, Project $project, ContractorEvaluation $evaluation): RedirectResponse
    {
        $this->ensureOwnOpen($project, $evaluation);

        $data = $this->validatedHeader($request);

        $evaluation->update([
            ...$data,
            'supplier_id' => ContractorEvaluation::supplierIdFor($data['contractor_name']),
        ]);

        AuditTrail::log("Contractor evaluation {$evaluation->control_no} details updated", $project, ['module' => 'Contractor Evaluation', 'type' => 'update']);

        return back()->with('success', "Contractor evaluation {$evaluation->control_no} updated.");
    }

    /** Save one category's form: its ratings, comments, signed form and evidence. */
    public function saveCategory(Request $request, Project $project, ContractorEvaluation $evaluation, string $category): RedirectResponse
    {
        $this->ensureOwnOpen($project, $evaluation);
        abort_unless(Scoring::has($category), 404);

        $data = $request->validate([
            'ratings'                => ['nullable', 'array'],
            'ratings.*'              => ['nullable', 'integer', 'between:1,5'],
            'comments'               => ['nullable', 'string', 'max:5000'],
            'corrective_actions'     => ['nullable', 'string', 'max:5000'],
            'form'                   => ['nullable', 'file', 'max:20480', self::FILE_RULE],
            'evidence'               => ['nullable', 'array', 'max:20'],
            'evidence.*'             => ['file', 'max:20480', self::FILE_RULE],
            'removed_attachments'    => ['nullable', 'array'],
            'removed_attachments.*'  => ['integer'],
        ], [
            'form.max'        => 'The evaluation form must be 20 MB or smaller.',
            'evidence.*.max'  => 'Each evidence file must be 20 MB or smaller.',
            'form.mimes'      => 'The evaluation form must be a PDF, image, Word, Excel or PowerPoint file.',
            'evidence.*.mimes' => 'Evidence must be PDF, image, Word, Excel or PowerPoint files.',
        ]);

        $row = $evaluation->categories()->firstOrCreate(['category' => $category]);
        $ratings = Scoring::cleanRatings($category, $data['ratings'] ?? []);

        $row->update([
            'ratings'            => $ratings,
            'score'              => Scoring::categoryScore($category, $ratings),
            'comments'           => $data['comments'] ?? null,
            'corrective_actions' => $data['corrective_actions'] ?? null,
        ]);

        if (! empty($data['removed_attachments'])) {
            $row->attachments()->whereKey($data['removed_attachments'])->get()->each(fn (Attachment $a) => $this->purge($a));
        }

        if ($request->hasFile('form')) {
            // One signed form per category: a new upload replaces it.
            $row->attachments()->where('type', ContractorEvaluationCategory::FILE_FORM)->get()->each(fn (Attachment $a) => $this->purge($a));
            $this->attach($row, $evaluation, $request->file('form'), ContractorEvaluationCategory::FILE_FORM);
        }

        foreach ($request->file('evidence') ?? [] as $file) {
            $this->attach($row, $evaluation, $file, ContractorEvaluationCategory::FILE_EVIDENCE);
        }

        $evaluation->refreshTotal();

        $title = Scoring::categories()[$category]['title'];
        AuditTrail::log("Contractor evaluation {$evaluation->control_no}: {$title} saved", $project, ['module' => 'Contractor Evaluation', 'type' => 'update']);

        return back()->with('success', "{$title} saved.");
    }

    /**
     * The engineer's "Evaluated by": every category fully rated and backed by
     * evidence, then on to the PMD Assistant Manager. An evaluation returned
     * for revision starts its sign-off afresh.
     */
    public function submit(Request $request, Project $project, ContractorEvaluation $evaluation): RedirectResponse
    {
        $this->ensureOwnOpen($project, $evaluation);

        $evaluation->ensureCategories();
        $evaluation->load('categories.attachments');

        $missing = [];
        foreach (Scoring::categories() as $key => $definition) {
            $row = $evaluation->categories->firstWhere('category', $key);
            $label = "{$definition['letter']}. {$definition['title']}";

            if (! $row || ! Scoring::isComplete($key, $row->ratings)) {
                $missing[] = "{$label}: rate every criterion.";
            } elseif (! $row->attachments->contains('type', ContractorEvaluationCategory::FILE_EVIDENCE)) {
                $missing[] = "{$label}: attach at least one piece of evidence.";
            }
        }

        if ($missing !== []) {
            // One message, one line per category: the page shows a field's
            // first message only, and the engineer needs the whole list.
            throw ValidationException::withMessages(['submit' => implode("\n", $missing)]);
        }

        $evaluation->approvals()->delete();
        $evaluation->unsetRelation('approvals');

        $evaluation->update([
            'status'         => ContractorEvaluation::STATUS_FOR_APPROVAL,
            'submitted_at'   => now(),
            'approved_at'    => null,
            'return_remarks' => null,
        ]);
        $evaluation->refreshTotal();
        $evaluation->startApprovalChain();

        AuditTrail::log("Contractor evaluation {$evaluation->control_no} submitted for review by {$request->user()->name}", $project, ['module' => 'Contractor Evaluation', 'type' => 'update']);

        $this->notifyCurrentSigner($evaluation, "Contractor evaluation {$evaluation->control_no} ({$evaluation->contractor_name}, {$evaluation->period_label}) for project {$project->project_no} is awaiting your review.");

        return back()->with('success', "Contractor evaluation {$evaluation->control_no} submitted to the PMD Assistant Manager for review.");
    }

    public function destroy(Project $project, ContractorEvaluation $evaluation): RedirectResponse
    {
        $this->ensureOwnOpen($project, $evaluation);

        AuditTrail::log("Contractor evaluation {$evaluation->control_no} deleted", $project, ['module' => 'Contractor Evaluation', 'type' => 'delete']);

        $evaluation->load('categories.attachments');
        $evaluation->categories->each(fn ($row) => $row->attachments->each(fn (Attachment $a) => $this->purge($a)));
        $evaluation->delete();

        return redirect()
            ->route('projects.hub.eval-summary', $project->id)
            ->with('success', "Contractor evaluation {$evaluation->control_no} deleted.");
    }

    public function approve(Request $request, ContractorEvaluation $evaluation): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);
        $user = $request->user();

        abort_unless($this->mayAct($evaluation, $user), 403, 'This evaluation is not awaiting your signature.');

        $step = $evaluation->recordApproval($user, $data['remarks'] ?? null);
        $verb = ContractorEvaluation::SIGNATURE_VERBS[$step->role] ?? 'approved';
        $project = $evaluation->project;

        AuditTrail::log("Contractor evaluation {$evaluation->control_no} {$verb} by the {$step->officeLabel()}", $project, ['module' => 'Contractor Evaluation', 'type' => 'update']);

        if ($evaluation->approvalChainComplete()) {
            $evaluation->update(['status' => ContractorEvaluation::STATUS_APPROVED, 'approved_at' => now()]);

            // Each required corrective action becomes a tracked non-compliance record.
            $raised = ContractorFindings::raiseFromEvaluation($evaluation);
            $tracked = $raised > 0 ? " {$raised} corrective action(s) are now tracked under Non-Compliance and Corrective Actions." : '';

            if ($evaluation->created_by) {
                Notification::notify($evaluation->created_by, "Contractor evaluation {$evaluation->control_no} for project {$project->project_no} was approved.{$tracked}", $this->hubLink($evaluation));
            }

            return back()->with('success', "Contractor evaluation {$evaluation->control_no} approved.{$tracked}");
        }

        $this->notifyCurrentSigner($evaluation, "Contractor evaluation {$evaluation->control_no} ({$evaluation->contractor_name}, {$evaluation->period_label}) for project {$project->project_no} was reviewed and is awaiting your approval.");

        if ($evaluation->created_by) {
            Notification::notify(
                $evaluation->created_by,
                "Contractor evaluation {$evaluation->control_no} was {$verb} by the {$step->officeLabel()} and is now with the " . User::roleLabel($evaluation->currentApprovalRole()) . '.',
                $this->hubLink($evaluation)
            );
        }

        return back()->with('success', "Contractor evaluation {$evaluation->control_no} {$verb}.");
    }

    /** Back to the engineer to revise, from whichever step it waits on. */
    public function returnForRevision(Request $request, ContractorEvaluation $evaluation): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'max:1000']], [
            'remarks.required' => 'Say what needs revising.',
        ]);
        $user = $request->user();

        abort_unless($this->mayAct($evaluation, $user), 403, 'This evaluation is not awaiting your signature.');

        $step = $evaluation->recordRejection($user, $data['remarks']);

        $evaluation->update([
            'status'         => ContractorEvaluation::STATUS_DRAFT,
            'return_remarks' => $data['remarks'],
        ]);

        AuditTrail::log("Contractor evaluation {$evaluation->control_no} returned to the engineer by the {$step->officeLabel()}", $evaluation->project, ['module' => 'Contractor Evaluation', 'type' => 'update']);

        if ($evaluation->created_by) {
            Notification::notify(
                $evaluation->created_by,
                "Contractor evaluation {$evaluation->control_no} was returned to you by the {$step->officeLabel()} for revision. Reason: {$data['remarks']}",
                $this->hubLink($evaluation)
            );
        }

        return back()->with('success', "Contractor evaluation {$evaluation->control_no} returned to the engineer.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validatedHeader(Request $request): array
    {
        return $request->validate([
            'period_type'     => ['required', Rule::in(array_keys(config('contractor_evaluation.period_types')))],
            'period_label'    => ['required', 'string', 'max:100'],
            'period_start'    => ['nullable', 'date'],
            'period_end'      => ['nullable', 'date', 'after_or_equal:period_start'],
            'contractor_name' => ['required', 'string', 'max:255'],
        ], [
            'period_label.required'    => 'Name the evaluation period, e.g. "October 2026" or "Q4 2026".',
            'contractor_name.required' => 'Name the contractor being evaluated.',
        ]);
    }

    private function ensureOwnOpen(Project $project, ContractorEvaluation $evaluation): void
    {
        abort_unless((int) $evaluation->project_id === (int) $project->id, 404);
        abort_unless($evaluation->isOpen(), 422, 'This evaluation has already been submitted for review.');
    }

    /** Admins may settle any step; everyone else must hold the step being waited on. */
    private function mayAct(ContractorEvaluation $evaluation, User $user): bool
    {
        if ($evaluation->status !== ContractorEvaluation::STATUS_FOR_APPROVAL || $evaluation->currentApproval() === null) {
            return false;
        }

        return $user->hasRole(User::ROLE_ADMIN) || $evaluation->awaitingApprovalFrom($user);
    }

    private function attach(ContractorEvaluationCategory $row, ContractorEvaluation $evaluation, $file, string $type): void
    {
        $path = $file->store("contractor-evaluations/{$evaluation->id}", 'public');

        $attachment = $row->attachments()->create([
            'filename' => $file->getClientOriginalName(),
            'filepath' => $path,
            'type'     => $type,
        ]);

        $attachment->recordFileVersion($path, $file->getClientOriginalName(), mimeType: $file->getMimeType(), size: $file->getSize());
    }

    private function purge(Attachment $attachment): void
    {
        $attachment->purgeFileVersions();
        Storage::disk('public')->delete($attachment->filepath);
        $attachment->delete();
    }

    /** Whoever holds the step the chain now waits on, plus anyone covering it as OIC. */
    private function notifyCurrentSigner(ContractorEvaluation $evaluation, string $message): void
    {
        $role = $evaluation->currentApprovalRole();

        if ($role === null) {
            return;
        }

        $recipients = User::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->pluck('id')
            ->merge(RosterBreak::oicIdsCovering([$role]))
            ->unique()
            ->values();

        Notification::notify($recipients, $message, $this->hubLink($evaluation));
    }

    private function hubLink(ContractorEvaluation $evaluation): string
    {
        return route('projects.hub.eval-summary', ['project' => $evaluation->project_id, 'evaluation' => $evaluation->id], absolute: false);
    }
}
