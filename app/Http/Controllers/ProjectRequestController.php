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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ProjectRequest::with(['requester', 'project'])->latest();

        $user = $request->user();
        if (!$user->hasRole(['approver', 'admin'])) {
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
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ProjectRequest::class);

        return Inertia::render('requests/create', [
            'jobTypes'     => JobType::orderBy('name')->get(['id', 'name']),
            'jobLocations' => JobLocation::orderBy('name')->get(['id', 'name']),
            'costCodes'    => CostCode::orderBy('name')->get(['id', 'name']),
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

            'attachments'               => ['required', 'array', 'min:1'],
            'attachments.*.file'        => ['required', 'file', 'max:10240'],
            'attachments.*.type'        => ['required', 'string', 'in:picture,drawing,report'],
            'attachments.*.description' => ['nullable', 'string', 'max:255'],
        ]);

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
                ...$projectRequest->load(['requester', 'attachments', 'project'])->toArray(),
                'can' => $this->abilities($projectRequest),
            ],
        ]);
    }

    public function edit(ProjectRequest $projectRequest): Response
    {
        $this->authorize('update', $projectRequest);

        return Inertia::render('requests/edit', [
            'projectRequest' => $projectRequest->load('attachments'),
            'jobTypes'       => JobType::orderBy('name')->get(['id', 'name']),
            'jobLocations'   => JobLocation::orderBy('name')->get(['id', 'name']),
            'costCodes'      => CostCode::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, ProjectRequest $projectRequest): RedirectResponse
    {
        // Status-only patch (approve/reject from index or show page)
        if ($request->has('status') && count($request->all()) === 1) {
            $this->authorize('decide', $projectRequest);

            $request->validate([
                'status' => ['required', 'string', 'in:approved,rejected,ongoing,completed,pending'],
            ]);

            $projectRequest->update(['status' => $request->status]);

            Notification::notify(
                $projectRequest->requester_id,
                "Project Request #{$projectRequest->request_no} status was changed to: " . ucfirst($request->status),
                route('requests.show', $projectRequest->id, absolute: false)
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
            'attachments.*.file'        => ['nullable', 'file', 'max:10240'],
            'attachments.*.type'        => ['required_with:attachments.*.file', 'string', 'in:picture,drawing,report'],
            'attachments.*.description' => ['nullable', 'string', 'max:255'],

            'deleted_attachments'   => ['nullable', 'array'],
            'deleted_attachments.*' => ['integer'],
        ]);

        $existingCount = $projectRequest->attachments()->count();
        $deletedCount  = count($request->input('deleted_attachments', []));
        $newFileCount  = count(array_filter($request->file('attachments', []), fn ($a) => !empty($a['file'])));

        if ($existingCount - $deletedCount + $newFileCount < 1) {
            return back()->withErrors(['attachments' => 'At least one attachment is required.'])->withInput();
        }

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

        // Delete removed attachments
        if ($request->filled('deleted_attachments')) {
            $toDelete = Attachment::whereIn('id', $request->deleted_attachments)
                ->where('reference_id', $projectRequest->id)
                ->where('reference_type', ProjectRequest::class)
                ->get();

            foreach ($toDelete as $att) {
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

        foreach ($attachments as $item) {
            if (empty($item['file'])) continue;

            $file = $item['file'];
            $type = $item['type'] ?? 'other';
            $desc = $item['description'] ?? null;

            $folder   = "requests/{$projectRequest->id}/{$type}s";
            $filepath = $file->store($folder, 'public');

            Attachment::create([
                'filename'       => $file->getClientOriginalName(),
                'filepath'       => $filepath,
                'reference_id'   => $projectRequest->id,
                'reference_type' => ProjectRequest::class,
                'description'    => $desc,
            ]);
        }
    }

    private function notifyApprovers(string $message, ProjectRequest $projectRequest): void
    {
        Notification::notify(
            User::whereHas('roles', fn ($q) => $q->where('name', 'approver'))->pluck('id'),
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
            ] : null,
            'project' => $projectRequest->project ? [
                'id' => $projectRequest->project->id,
                'project_no' => $projectRequest->project->project_no,
            ] : null,
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
