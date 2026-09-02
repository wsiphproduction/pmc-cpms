<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\CostCode;
use App\Models\JobLocation;
use App\Models\JobType;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Support\ApprovalFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRequestController extends Controller
{
    public function __construct(private readonly ApprovalFlow $flow)
    {
    }

    public function index(Request $request): Response
    {
        $query = ProjectRequest::with(['requester', 'project', 'approvals.user'])->latest();

        // Everyone inside PMD sees every request; department users see their own.
        $user = $request->user();
        if (!$user->hasRole(User::INTERNAL_ROLES)) {
            $query->where('requester_id', $user->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('job_type', 'like', "%{$search}%")
                  ->orWhere('job_location', 'like', "%{$search}%")
                  ->orWhere('costcode', 'like', "%{$search}%")
                  ->orWhereHas('project', fn ($project) => $project->where('project_no', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('job_type'))     $query->where('job_type', $request->job_type);
        if ($request->filled('job_location')) $query->where('job_location', 'like', "%{$request->job_location}%");
        if ($request->filled('costcode'))     $query->where('costcode', 'like', "%{$request->costcode}%");
        if ($request->filled('status') && is_array($request->status)) {
            $query->whereIn('status', $request->status);
        }

        return Inertia::render('requests/index', [
            'requests' => $query->paginate(15)->withQueryString()
                ->through(fn (ProjectRequest $projectRequest) => $this->requestListData($projectRequest)),
            'filters'  => $request->only(['search', 'job_type', 'job_location', 'costcode', 'status']),
            'canCreate' => $user->can('create', ProjectRequest::class),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ProjectRequest::class);

        return Inertia::render('requests/create', [
            'jobTypes'     => JobType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'jobLocations' => JobLocation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'costCodes'    => $this->costCodeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProjectRequest::class);

        $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'job_type'        => ['required', 'string', 'max:255'],
            'description'     => ['required', 'string'],
            'job_location'    => ['required', 'string', 'max:255'],
            'costcode'        => [Rule::requiredIf(fn () => $request->boolean('opex') && $request->boolean('capex')), 'nullable', 'string', 'max:255'],
            'opex'            => ['boolean', $this->requiresFundingClassification($request)],
            'capex'           => ['boolean'],
            'for_budgeting'   => ['boolean'],

            'attachments'               => ['nullable', 'array'],
            'attachments.*.file'        => ['nullable', 'file'],
            'attachments.*.type'        => ['required_with:attachments.*.file', 'string', 'in:picture,drawing,report,other'],
            'attachments.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var ProjectRequest $projectRequest */
        $projectRequest = ProjectRequest::create([
            'request_no'    => $this->nextRequestNo(),
            'title'         => $request->title,
            'job_type'      => $request->job_type,
            'description'   => $request->description,
            'requester_id'  => auth()->id(),
            'job_location'  => $request->job_location,
            'costcode'      => $request->costcode ?: null,
            'opex'          => $request->boolean('opex'),
            'capex'         => $request->boolean('capex'),
            'for_budgeting' => $request->boolean('for_budgeting'),
            'status'        => 'pending',
        ]);

        // Engineer → PMD Assistant Manager → PMD Department Manager.
        $projectRequest->startApprovalChain();

        $this->storeAttachments($request, $projectRequest);

        $this->notifyApprovers(
            "New Project Request from {$request->user()->name}",
            $projectRequest
        );

        return redirect()->route('requests.index')
            ->with('success', 'Project request submitted successfully.');
    }

    public function show(ProjectRequest $projectRequest): Response
    {
        $this->authorize('view', $projectRequest);

        return Inertia::render('requests/show', [
            'projectRequest' => [
                ...$projectRequest->load(['requester', 'attachments.fileVersions.uploader', 'project', 'approvals.user'])->toArray(),
                'approvals' => $projectRequest->approvalTimeline(),
                'awaiting_role_label' => $projectRequest->currentApprovalRole()
                    ? User::roleLabel($projectRequest->currentApprovalRole())
                    : null,
                'can' => $this->abilities($projectRequest),
            ],
            'feedbacks' => $projectRequest->technicalFeedback()->with('user')->latest()->get()
                ->map(fn (\App\Models\TechnicalFeedback $f) => [
                    'id'          => $f->id,
                    'can_edit'    => auth()->id() === $f->user_id,
                    'author'      => $f->user->name ?? 'Unknown',
                    'date'        => $f->created_at->format('M d, Y h:i A'),
                    'priority'    => $f->priority,
                    'disciplines' => $f->disciplines ?? [],
                    'permits'     => $f->permits ?? [],
                    'remarks'     => $f->remarks,
                ]),
        ]);
    }

    public function edit(ProjectRequest $projectRequest): Response
    {
        $this->authorize('update', $projectRequest);

        return Inertia::render('requests/edit', [
            'projectRequest' => $projectRequest->load('attachments.fileVersions.uploader'),
            'jobTypes'       => JobType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'jobLocations'   => JobLocation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'costCodes'      => $this->costCodeOptions(),
        ]);
    }

    public function update(Request $request, ProjectRequest $projectRequest): RedirectResponse
    {
        // Status-only patch (approve/reject/resume from index or show page)
        if ($request->has('status') && count($request->all()) === 1) {
            $this->authorize('decide', $projectRequest);

            $link = route('requests.show', $projectRequest->id, absolute: false);

            // "resume" lifts a hold and restores the status the request had before it.
            if ($request->status === 'resume') {
                $restored = $projectRequest->status_before_hold ?: 'pending';

                $projectRequest->update([
                    'status'             => $restored,
                    'status_before_hold' => null,
                ]);

                Notification::notify(
                    $projectRequest->requester_id,
                    "Project Request #{$projectRequest->request_no} was taken OFF HOLD (status: " . ucfirst($restored) . ").",
                    $link
                );

                return back()->with('success', 'Request resumed.');
            }

            $request->validate([
                'status' => ['required', 'string', 'in:approved,rejected,ongoing,completed,pending,hold'],
            ]);

            // Approving or rejecting here is the engineer's signature — the first
            // step of the chain — so it goes through the flow, which decides
            // whether the request advances to PMD or stops dead.
            if (in_array($request->status, ['approved', 'rejected'], true)) {
                $signed = $request->status === 'approved'
                    ? $this->flow->approveRequest($projectRequest, $request->user())
                    : $this->flow->rejectRequest($projectRequest, $request->user());

                abort_unless($signed, 403, 'This request is not awaiting your decision.');

                $projectRequest->refresh();

                return back()->with('success', $projectRequest->status === 'approved'
                    ? 'Request fully approved.'
                    : ($request->status === 'rejected'
                        ? 'Request rejected.'
                        : 'Request endorsed to the ' . User::roleLabel($projectRequest->currentApprovalRole()) . '.'));
            }

            $old = $projectRequest->status;

            $projectRequest->update([
                'status'             => $request->status,
                // Remember the pre-hold status when moving TO hold; clear it otherwise.
                'status_before_hold' => $request->status === 'hold'
                    ? ($projectRequest->status_before_hold ?: $old)
                    : null,
            ]);

            Notification::notify(
                $projectRequest->requester_id,
                "Project Request #{$projectRequest->request_no} status was changed to: " . ucfirst($request->status),
                $link
            );

            return back()->with('success', 'Request status updated.');
        }

        $this->authorize('update', $projectRequest);

        // Full update (from edit form)
        $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'job_type'        => ['required', 'string', 'max:255'],
            'description'     => ['required', 'string'],
            'job_location'    => ['required', 'string', 'max:255'],
            'costcode'        => [Rule::requiredIf(fn () => $request->boolean('opex') && $request->boolean('capex')), 'nullable', 'string', 'max:255'],
            'opex'            => ['boolean', $this->requiresFundingClassification($request)],
            'capex'           => ['boolean'],
            'for_budgeting'   => ['boolean'],

            'attachments'               => ['nullable', 'array'],
            'attachments.*.file'        => ['nullable', 'file'],
            'attachments.*.type'        => ['required_with:attachments.*.file', 'string', 'in:picture,drawing,report,other'],
            'attachments.*.description' => ['nullable', 'string', 'max:255'],

            'deleted_attachments'   => ['nullable', 'array'],
            'deleted_attachments.*' => ['integer'],
        ]);

        // If the request is currently ON HOLD, the requester editing it means they
        // have addressed the engineer's concern — resume the pre-hold status.
        $wasHold = $projectRequest->status === 'hold';

        $projectRequest->update([
            'title'         => $request->title,
            'job_type'      => $request->job_type,
            'description'   => $request->description,
            'job_location'  => $request->job_location,
            'costcode'      => $request->costcode ?: null,
            'opex'          => $request->boolean('opex'),
            'capex'         => $request->boolean('capex'),
            'for_budgeting' => $request->boolean('for_budgeting'),
        ]);

        if ($wasHold) {
            $projectRequest->update([
                'status'             => $projectRequest->status_before_hold ?: 'pending',
                'status_before_hold' => null,
            ]);
        }

        // Delete removed attachments
        if ($request->filled('deleted_attachments')) {
            $toDelete = Attachment::whereIn('id', $request->deleted_attachments)
                ->where('reference_id', $projectRequest->id)
                ->where('reference_type', ProjectRequest::class)
                ->get();

            foreach ($toDelete as $att) {
                // Removing the attachment removes its whole history — every
                // superseded file goes with it, not just the current one.
                $att->purgeFileVersions();
                Storage::disk('public')->delete($att->filepath); // ✅ fixed: was Storage::delete()
                $att->delete();
            }
        }

        $this->storeAttachments($request, $projectRequest);

        $this->notifyApprovers(
            "Project Request #{$projectRequest->request_no} has been updated",
            $projectRequest
        );

        return redirect()->route('requests.index')
            ->with('success', 'Project request updated successfully.');
    }

    public function destroy(ProjectRequest $projectRequest): RedirectResponse
    {
        $this->authorize('delete', $projectRequest);

        foreach ($projectRequest->attachments as $att) {
            $att->purgeFileVersions();
            Storage::disk('public')->delete($att->filepath);
            $att->delete(); // 👈 this was missing!
        }

        $projectRequest->delete();

        return redirect()->route('requests.index')
            ->with('success', 'Project request deleted.');
    }

    private function storeAttachments(Request $request, ProjectRequest $projectRequest): void
    {
        $attachments = $request->file('attachments') ?? [];

        foreach ($attachments as $index => $item) {
            $file = is_array($item) ? ($item['file'] ?? null) : $item;
            if (empty($file)) continue;

            // NOTE: $request->file() returns ONLY the uploaded-file elements of
            // each row — the sibling 'type'/'description' keys are not files, so
            // they must be read from the input bag by index, otherwise every
            // attachment falls back to 'other'.
            $type = $request->input("attachments.{$index}.type", 'other');
            $desc = $request->input("attachments.{$index}.description");

            $type = in_array($type, ['picture', 'drawing', 'report', 'other'], true) ? $type : 'other';

            $folder   = "requests/{$projectRequest->id}/{$type}s";
            $filepath = $file->store($folder, 'public');

            $attachment = Attachment::create([
                'filename'       => $file->getClientOriginalName(),
                'filepath'       => $filepath,
                'type'           => $type,
                'reference_id'   => $projectRequest->id,
                'reference_type' => ProjectRequest::class,
                'description'    => $desc,
            ]);

            $attachment->recordFileVersion(
                $filepath,
                $file->getClientOriginalName(),
                mimeType: $file->getMimeType(),
                size: $file->getSize(),
            );
        }
    }

    /**
     * Swap the file behind an attachment for a newer one. The attachment keeps
     * its identity, type and description — only the file moves on, to v2, v3,
     * and so on. The file it replaces stays on disk and stays downloadable
     * from the attachment's history.
     */
    public function replaceAttachment(Request $request, ProjectRequest $projectRequest, Attachment $attachment): RedirectResponse
    {
        $this->authorize('update', $projectRequest);

        abort_unless(
            (int) $attachment->reference_id === (int) $projectRequest->id
                && $attachment->reference_type === ProjectRequest::class,
            403,
        );

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $file    = $request->file('file');
        $folder  = "requests/{$projectRequest->id}/{$attachment->type}s";
        $version = $attachment->storeVersionedFile($file, $folder, note: $request->input('note'));

        $attachment->update([
            'filename' => $version->filename,
            'filepath' => $version->filepath,
        ]);

        $this->notifyApprovers(
            "An attachment on Project Request #{$projectRequest->request_no} was replaced ({$version->label})",
            $projectRequest
        );

        return back()->with('success', "Attachment updated to {$version->label}.");
    }

    private function notifyApprovers(string $message, ProjectRequest $projectRequest): void
    {
        Notification::notify(
            User::whereHas('roles', fn ($q) => $q->whereIn('name', ['approver', 'assistant_manager']))->pluck('id'),
            $message,
            route('requests.show', $projectRequest->id, absolute: false)
        );
    }

    private function requiresFundingClassification(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request) {
            if (!$request->boolean('opex') && !$request->boolean('capex') && !$request->boolean('for_budgeting')) {
                $fail('Select at least one funding classification (OPEX, CAPEX, or For Budgeting).');
            }
        };
    }

    private function requestListData(ProjectRequest $projectRequest): array
    {
        return [
            'id' => $projectRequest->id,
            'request_no' => $projectRequest->request_no,
            'title' => $projectRequest->title,
            'job_type' => $projectRequest->job_type,
            'job_location' => $projectRequest->job_location,
            'status' => $projectRequest->status,
            'costcode' => $projectRequest->costcode,
            'created_at' => $projectRequest->created_at?->format('M d, Y h:i A'),
            'requester' => $projectRequest->requester ? [
                'name' => $projectRequest->requester->name,
                'department' => $projectRequest->requester->department,
            ] : null,
            'project' => $projectRequest->project ? [
                'id' => $projectRequest->project->id,
                'project_no' => $projectRequest->project->project_no,
            ] : null,
            'approvals' => $projectRequest->approvalTimeline(),
            'awaiting_role' => $projectRequest->currentApprovalRole(),
            'awaiting_role_label' => $projectRequest->currentApprovalRole()
                ? User::roleLabel($projectRequest->currentApprovalRole())
                : null,
            'can' => $this->abilities($projectRequest),
        ];
    }

    private function abilities(ProjectRequest $projectRequest): array
    {
        $user = auth()->user();

        return [
            'update' => $user->can('update', $projectRequest),
            'delete' => $user->can('delete', $projectRequest),
            'decide' => $user->can('decide', $projectRequest),
            'canCreateProject' => $user->can('create', Project::class),
        ];
    }

    /**
     * Cost-code dropdown options. `label` is the full "code — department —
     * description" line; the stored value stays the bare code.
     */
    private function costCodeOptions()
    {
        return CostCode::where('is_active', true)->orderBy('name')->get()
            ->map(fn (CostCode $code) => [
                'id'    => $code->id,
                'name'  => $code->name,
                'label' => $code->optionLabel(),
            ]);
    }

    private function nextRequestNo(): string
    {
        $year = now()->format('Y');
        $latest = ProjectRequest::withTrashed()
            ->where('request_no', 'like', "REQ-{$year}-%")
            ->orderByDesc('request_no')
            ->value('request_no');

        $next = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return 'REQ-' . $year . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

}
