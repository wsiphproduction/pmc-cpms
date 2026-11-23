<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AuditTrail;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\RosterBreak;
use App\Models\User;
use App\Support\AssessmentForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Hub documents with an electronic approver process (Phase 3): Safety Risk
 * Assessment, Geological Assessment, Billing Validation Report, and the
 * sign-off of the two completion certificates. Every type shares this flow;
 * what differs — the form and who signs — is configuration.
 */
class ProjectAssessmentController extends Controller
{
    private const FILE_RULE = 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx,dwg';

    /**
     * A new document. For a completion certificate there is no form to fill —
     * the completion details are the content — so it goes straight to sign-off.
     */
    public function store(Request $request, Project $project, string $type): RedirectResponse
    {
        abort_unless(ProjectAssessment::isType($type), 404);
        $definition = ProjectAssessment::types()[$type];
        $isCompletion = ($definition['source'] ?? null) === 'completion';

        if ($isCompletion) {
            abort_unless($project->status_key === 'COMPLETED' && $project->completion?->isCertificateFilled(), 422,
                'Fill in the completion details on a Completed project first.');
            abort_if(
                $project->assessments()->where('type', $type)->where('status', '!=', ProjectAssessment::STATUS_DRAFT)->exists(),
                422,
                "The {$definition['label']} is already in sign-off or approved."
            );
            // A returned one is resubmitted rather than duplicated.
            $assessment = $project->assessments()->where('type', $type)->where('status', ProjectAssessment::STATUS_DRAFT)->first();
        }

        // Check the form before anything is written, so a rejected save leaves no empty draft behind.
        $data = $isCompletion ? null : $this->validatedForm($request, $definition, $project);

        $assessment ??= $project->assessments()->create([
            'type'       => $type,
            'control_no' => ProjectAssessment::nextControlNo($type),
            'status'     => ProjectAssessment::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        if ($isCompletion) {
            $this->startSignoff($assessment, $request->user());

            return back()->with('success', "{$definition['label']} submitted for electronic approval.");
        }

        $this->persistForm($request, $assessment, $data);

        AuditTrail::log("{$definition['label']} {$assessment->control_no} created", $project, ['module' => $definition['label'], 'type' => 'create']);

        return back()->with('success', "{$definition['label']} {$assessment->control_no} saved as a draft.");
    }

    public function update(Request $request, Project $project, ProjectAssessment $assessment): RedirectResponse
    {
        $this->ensureOwnOpen($project, $assessment);
        abort_if($assessment->isCompletionSignoff(), 422);

        $this->persistForm($request, $assessment, $this->validatedForm($request, $assessment->definition(), $project));

        AuditTrail::log("{$assessment->label()} {$assessment->control_no} updated", $project, ['module' => $assessment->label(), 'type' => 'update']);

        return back()->with('success', "{$assessment->label()} {$assessment->control_no} saved.");
    }

    /** The engineer's "Prepared by": checked complete, then on to the first signer. */
    public function submit(Request $request, Project $project, ProjectAssessment $assessment): RedirectResponse
    {
        $this->ensureOwnOpen($project, $assessment);

        if (! $assessment->isCompletionSignoff()) {
            $missing = AssessmentForm::missing($assessment->definition(), $assessment->data ?? []);
            if ($missing !== []) {
                throw ValidationException::withMessages(['submit' => implode("\n", $missing)]);
            }
        } else {
            abort_unless($project->completion?->isCertificateFilled(), 422, 'Fill in the completion details first.');
        }

        $this->startSignoff($assessment, $request->user());

        return back()->with('success', "{$assessment->label()} {$assessment->control_no} submitted to the " . User::roleLabel($assessment->currentApprovalRole()) . '.');
    }

    public function destroy(Project $project, ProjectAssessment $assessment): RedirectResponse
    {
        $this->ensureOwnOpen($project, $assessment);

        AuditTrail::log("{$assessment->label()} {$assessment->control_no} deleted", $project, ['module' => $assessment->label(), 'type' => 'delete']);
        $assessment->attachments->each(fn (Attachment $a) => $this->purge($a));
        $assessment->delete();

        return back()->with('success', "{$assessment->label()} {$assessment->control_no} deleted.");
    }

    public function approve(Request $request, ProjectAssessment $assessment): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);
        $user = $request->user();

        abort_unless($this->mayAct($assessment, $user), 403, 'This document is not awaiting your signature.');

        $step = $assessment->recordApproval($user, $data['remarks'] ?? null);
        $verb = $assessment->signatureVerb($step->role);
        $project = $assessment->project;
        $name = "{$assessment->label()} {$assessment->control_no}";

        AuditTrail::log("{$name} {$verb} by the {$step->officeLabel()}", $project, ['module' => $assessment->label(), 'type' => 'update']);

        if ($assessment->approvalChainComplete()) {
            $assessment->update(['status' => ProjectAssessment::STATUS_APPROVED, 'approved_at' => now()]);

            if ($assessment->created_by) {
                Notification::notify($assessment->created_by, "{$name} for project {$project->project_no} was approved.", $this->hubLink($assessment));
            }

            return back()->with('success', "{$name} approved.");
        }

        $this->notifyCurrentSigner($assessment, "{$name} for project {$project->project_no} was {$verb} and is awaiting your signature.");

        if ($assessment->created_by) {
            Notification::notify($assessment->created_by, "{$name} was {$verb} by the {$step->officeLabel()} and is now with the " . User::roleLabel($assessment->currentApprovalRole()) . '.', $this->hubLink($assessment));
        }

        return back()->with('success', "{$name} {$verb}.");
    }

    public function returnForRevision(Request $request, ProjectAssessment $assessment): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'max:1000']], ['remarks.required' => 'Say what needs revising.']);
        $user = $request->user();

        abort_unless($this->mayAct($assessment, $user), 403, 'This document is not awaiting your signature.');

        $step = $assessment->recordRejection($user, $data['remarks']);
        $assessment->update(['status' => ProjectAssessment::STATUS_DRAFT, 'return_remarks' => $data['remarks']]);
        $name = "{$assessment->label()} {$assessment->control_no}";

        AuditTrail::log("{$name} returned for revision by the {$step->officeLabel()}", $assessment->project, ['module' => $assessment->label(), 'type' => 'update']);

        if ($assessment->created_by) {
            Notification::notify($assessment->created_by, "{$name} was returned to you by the {$step->officeLabel()} for revision. Reason: {$data['remarks']}", $this->hubLink($assessment));
        }

        return back()->with('success', "{$name} returned for revision.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** The request's files and form, checked against the type's definition. */
    private function validatedForm(Request $request, array $definition, Project $project): array
    {
        $request->validate([
            'data'                  => ['nullable', 'array'],
            'files'                 => ['nullable', 'array', 'max:20'],
            'files.*'               => ['file', 'max:20480', self::FILE_RULE],
            'removed_attachments'   => ['nullable', 'array'],
            'removed_attachments.*' => ['integer'],
        ], ['files.*.max' => 'Each file must be 20 MB or smaller.']);

        return AssessmentForm::clean($definition, $request->input('data', []), $project);
    }

    private function persistForm(Request $request, ProjectAssessment $assessment, array $data): void
    {
        $assessment->update(['data' => $data, 'title' => AssessmentForm::title($assessment->definition(), $data)]);

        if ($removed = $request->input('removed_attachments', [])) {
            $assessment->attachments()->whereKey($removed)->get()->each(fn (Attachment $a) => $this->purge($a));
        }

        foreach ($request->file('files') ?? [] as $file) {
            $path = $file->store("assessments/{$assessment->id}", 'public');
            $attachment = $assessment->attachments()->create(['filename' => $file->getClientOriginalName(), 'filepath' => $path, 'type' => 'assessment']);
            $attachment->recordFileVersion($path, $file->getClientOriginalName(), mimeType: $file->getMimeType(), size: $file->getSize());
        }
    }

    private function startSignoff(ProjectAssessment $assessment, User $user): void
    {
        $assessment->approvals()->delete();
        $assessment->unsetRelation('approvals');
        $assessment->update([
            'status'         => ProjectAssessment::STATUS_FOR_APPROVAL,
            'submitted_at'   => now(),
            'approved_at'    => null,
            'return_remarks' => null,
        ]);
        $assessment->startApprovalChain();

        $project = $assessment->project;
        $name = "{$assessment->label()} {$assessment->control_no}";

        AuditTrail::log("{$name} submitted for approval by {$user->name}", $project, ['module' => $assessment->label(), 'type' => 'update']);

        $this->notifyCurrentSigner($assessment, "{$name} for project {$project->project_no} is awaiting your signature.");
    }

    private function ensureOwnOpen(Project $project, ProjectAssessment $assessment): void
    {
        abort_unless((int) $assessment->project_id === (int) $project->id, 404);
        abort_unless($assessment->isOpen(), 422, 'This document has already been submitted for approval.');
    }

    private function mayAct(ProjectAssessment $assessment, User $user): bool
    {
        if ($assessment->status !== ProjectAssessment::STATUS_FOR_APPROVAL || $assessment->currentApproval() === null) {
            return false;
        }

        return $user->hasRole(User::ROLE_ADMIN) || $assessment->awaitingApprovalFrom($user);
    }

    private function notifyCurrentSigner(ProjectAssessment $assessment, string $message): void
    {
        $role = $assessment->currentApprovalRole();

        if ($role === null) {
            return;
        }

        $recipients = User::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->pluck('id')
            ->merge(RosterBreak::oicIdsCovering([$role]))
            ->unique()
            ->values();

        Notification::notify($recipients, $message, $this->hubLink($assessment));
    }

    private function hubLink(ProjectAssessment $assessment): string
    {
        return route('projects.hub.' . $assessment->type, $assessment->project_id, absolute: false);
    }

    private function purge(Attachment $attachment): void
    {
        $attachment->purgeFileVersions();
        Storage::disk('public')->delete($attachment->filepath);
        $attachment->delete();
    }
}
