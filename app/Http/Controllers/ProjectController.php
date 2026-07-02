<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Category;
use App\Models\CostCode;
use App\Models\Department;
use App\Models\MasterClass;
use App\Models\MasterStatus;
use App\Models\Notification;
use App\Models\Priority;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\ServiceType;
use App\Models\Site;
use App\Models\Structure;
use App\Models\User;
use App\Models\WorkForce;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    private const STATUS_LABELS = [
        'PLANNING' => 'For Planning',
        'RFQ_SUBMITTED' => 'RFQ/RFP Submitted',
        'PROPOSAL_REVIEW' => 'Proposal Under Review',
        'DESIGN_REVIEW' => 'Detailed Design Under Review',
        'EXEC_ENDORSED' => 'Endorsed for Executive Approval',
        'NTP_PROCESSING' => 'NTP & Contract Processing',
        'SCHEDULING' => 'For Scheduling',
        'ONGOING' => 'Ongoing',
        'ON_HOLD' => 'On Hold',
        'COMPLETED' => 'Completed',
        'CLOSED' => 'Closed',
        'CANCELED' => 'Canceled',
    ];

    public function index(Request $request): Response
    {
        $query = Project::with(['manager', 'creator'])->latest();

        $user = $request->user();
        if (!$user->hasRole(['approver', 'admin'])) {
            $query->whereHas('projectRequest', fn ($q) => $q->where('requester_id', $user->id));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('project_no', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('project_manager_name', 'like', "%{$search}%")
                    ->orWhere('dept_owner', 'like', "%{$search}%")
                    ->orWhereHas('creator', fn ($creator) => $creator->where('name', 'like', "%{$search}%"));
            });
        }

        foreach ($this->filterableFields() as $field => $column) {
            if ($request->filled($field)) {
                $query->where($column, 'like', '%' . $request->input($field) . '%');
            }
        }

        foreach (['jip', 'need_civil', 'need_electrical', 'need_mechanical'] as $field) {
            if ($request->boolean($field)) {
                $query->where($field, true);
            }
        }

        $projects = $query->paginate(15)->withQueryString()
            ->through(fn (Project $project) => $this->projectListData($project));

        return Inertia::render('project-management/index', [
            'projects' => $projects,
            'filters' => $request->only([
                'search',
                ...array_keys($this->filterableFields()),
                'jip',
                'need_civil',
                'need_electrical',
                'need_mechanical',
            ]),
            'canCreate' => $request->user()->can('create', Project::class),
            ...$this->masterDataOptions(),
            // Advanced search filters by the denormalized project_manager_name column, not the user id.
            'managers' => User::orderBy('name')->get(['name'])
                ->map(fn (User $user) => ['value' => (string) $user->name, 'label' => $user->name]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Project::class);

        $projectRequest = $request->filled('request_id')
            ? ProjectRequest::with(['project', 'requester'])->findOrFail($request->integer('request_id'))
            : null;

        abort_if($projectRequest?->project, 409, 'This project request already has a project.');

        return Inertia::render('project-management/create', [
            'next_project_no' => $this->nextProjectNo(),
            'project' => $projectRequest ? $this->projectFormDataFromRequest($projectRequest) : null,
            ...$this->masterDataOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $data = $this->validatedProjectData($request);
        $manager = User::find($data['project_manager']);
        $sourceRequest = !empty($data['project_request_id'])
            ? ProjectRequest::with('project')->findOrFail($data['project_request_id'])
            : null;

        abort_if($sourceRequest && $sourceRequest->status !== 'approved', 422, 'Only approved requests can be converted to projects.');
        abort_if($sourceRequest?->project, 409, 'This project request already has a project.');

        if ($data['project_type'] === 'major' && !$request->hasFile('proposal_document')) {
            return back()->withErrors(['proposal_document' => 'Approved proposal document is required for major projects.'])->withInput();
        }

        $project = Project::create([
            'project_request_id' => $data['project_request_id'] ?? null,
            'project_no' => $this->nextProjectNo(),
            'title' => $data['title'],
            'project_manager_id' => $manager?->id,
            'project_manager_name' => $manager?->name,
            'site' => $data['site'],
            'asset_id' => $data['asset_id'],
            'class_name' => $data['cls'],
            'priority' => $data['priority'],
            'status_key' => $data['status'],
            'work_force' => $data['work_force'],
            'wr_no' => $data['wr_no'],
            'wr_date' => $data['wr_date'],
            'dept_owner' => $data['dept_owner'],
            'cost_code' => $data['cost_code'],
            'category' => $data['category'],
            'service_type' => $data['service_type'],
            'deadline' => $data['deadline'],
            'owner_email' => $data['owner_email'] ?? null,
            'structure_type' => $data['structure_type'] ?? null,
            'jip' => $request->boolean('jip'),
            'need_civil' => $request->boolean('need_civil'),
            'need_electrical' => $request->boolean('need_electrical'),
            'need_mechanical' => $request->boolean('need_mechanical'),
            'notes' => $data['notes'] ?? null,
            'project_type' => $data['project_type'],
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('proposal_document')) {
            $path = $request->file('proposal_document')->store('proposals', 'public');
            $project->update(['proposal_document' => $path]);
        }

        $project->statusLogs()->create([
            'user_id' => auth()->id(),
            'status_key' => $project->status_key,
            'status_label' => self::STATUS_LABELS[$project->status_key] ?? $project->status_key,
            'remarks' => 'Project registered.',
        ]);

        if ($sourceRequest && $sourceRequest->requester_id !== auth()->id()) {
            Notification::notify(
                $sourceRequest->requester_id,
                "A Project has been created from your Project Request #{$sourceRequest->request_no}",
                route('projects.show', $project->id, absolute: false)
            );
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project registered successfully.');
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('project-management/show', [
            'project'    => $this->projectDetailData($project->load(['manager', 'creator', 'statusLogs.user'])),
            'hub_counts' => $this->hubCounts($project),
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('project-management/edit', [
            'next_project_no' => $project->project_no,
            'project' => $this->projectFormData($project),
            ...$this->masterDataOptions(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $this->validatedProjectData($request);
        $manager = User::find($data['project_manager']);

        if ($data['project_type'] === 'major' && !$project->proposal_document && !$request->hasFile('proposal_document')) {
            return back()->withErrors(['proposal_document' => 'Approved proposal document is required for major projects.'])->withInput();
        }

        $project->update([
            'title' => $data['title'],
            'project_manager_id' => $manager?->id,
            'project_manager_name' => $manager?->name,
            'site' => $data['site'],
            'asset_id' => $data['asset_id'],
            'class_name' => $data['cls'],
            'priority' => $data['priority'],
            'status_key' => $data['status'],
            'work_force' => $data['work_force'],
            'wr_no' => $data['wr_no'],
            'wr_date' => $data['wr_date'],
            'dept_owner' => $data['dept_owner'],
            'cost_code' => $data['cost_code'],
            'category' => $data['category'],
            'service_type' => $data['service_type'],
            'deadline' => $data['deadline'],
            'owner_email' => $data['owner_email'] ?? null,
            'structure_type' => $data['structure_type'] ?? null,
            'jip' => $request->boolean('jip'),
            'need_civil' => $request->boolean('need_civil'),
            'need_electrical' => $request->boolean('need_electrical'),
            'need_mechanical' => $request->boolean('need_mechanical'),
            'notes' => $data['notes'] ?? null,
            'project_type' => $data['project_type'],
        ]);

        if ($request->hasFile('proposal_document')) {
            if ($project->proposal_document) {
                Storage::disk('public')->delete($project->proposal_document);
            }
            $path = $request->file('proposal_document')->store('proposals', 'public');
            $project->update(['proposal_document' => $path]);
        }

        AuditTrail::log('Project details updated', $project, ['module' => 'Project', 'type' => 'update']);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }

    public function status(Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        return redirect()->route('projects.show', $project);
    }

    public function updateStatus(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'status_key' => ['required', 'string', 'max:80'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $label = self::STATUS_LABELS[$data['status_key']] ?? $data['status_key'];

        $project->update(['status_key' => $data['status_key']]);
        $project->statusLogs()->create([
            'user_id' => auth()->id(),
            'status_key' => $data['status_key'],
            'status_label' => $label,
            'remarks' => $data['remarks'] ?? 'No remarks.',
        ]);

        \App\Models\AuditTrail::log("Project status changed to: {$label}", $project, ['module' => 'Project', 'type' => 'update']);

        return back()->with('success', 'Project status updated.');
    }

    public function hub(Project $project, string $section): Response
    {
        $this->authorize('view', $project);

        $validSections = ['rfq', 'ntp', 'permits', 'vof', 'qpp', 'mtr', 'rfp', 'ioc', 'acr', 'psr', 'at', 'todo'];
        abort_unless(in_array($section, $validSections, true), 404);

        $project->load(['manager', 'creator', 'statusLogs.user']);

        return Inertia::render('project-management/show', [
            'project'        => $this->projectDetailData($project),
            'active_section' => $section,
            'hub_data'       => $this->hubSectionData($project, $section),
            'hub_counts'     => $this->hubCounts($project),
        ]);
    }

    private function hubCounts(Project $project): array
    {
        return [
            'rfq'     => $project->rfqs()->count(),
            'ntp'     => $project->ntps()->count(),
            'permits' => $project->permits()->count(),
            'vof'     => $project->variationOrders()->count(),
            'qpp'     => $project->qualityDocs()->count(),
            'mtr'     => $project->mtrDocs()->count(),
            'rfp'     => $project->billings()->count(),
            'ioc'     => $project->iocItems()->count(),
            'acr'     => $project->iocItems()->count(),
            'psr'     => $project->weeklyReports()->count(),
            'at'      => AuditTrail::where('reference_type', Project::class)->where('reference_id', $project->id)->count(),
            'todo'    => $project->tasks()->count(),
        ];
    }

    private function hubSectionData(Project $project, string $section): array
    {
        return match ($section) {
            'rfq' => [
                'rfqs' => $project->rfqs()->with('items')->get()->map(fn ($rfq) => [
                    'id'              => $rfq->id,
                    'contractor'      => $rfq->contractor_name,
                    'sent'            => optional($rfq->sent_date)->format('M d, Y') ?? '-',
                    'due'             => optional($rfq->due_date)->format('M d, Y') ?? '-',
                    'due_raw'         => optional($rfq->due_date)->format('Y-m-d'),
                    'status'          => ucfirst($rfq->status),
                    'scope_of_work'   => $rfq->scope_of_work,
                    'duration_days'   => $rfq->duration_days,
                    'terms'           => $rfq->terms_conditions,
                    'inclusions'      => $rfq->inclusions,
                    'exclusions'      => $rfq->exclusions,
                    'quotation_file'  => $rfq->quotation_file ? Storage::disk('public')->url($rfq->quotation_file) : null,
                    'recipient_email' => $rfq->recipient_email,
                    'has_ntp'         => $project->ntps()->where('project_rfq_id', $rfq->id)->exists(),
                    'items'           => $rfq->items->map(fn ($item) => [
                        'seq'        => $item->seq,
                        'description'=> $item->description,
                        'qty'        => $item->qty,
                        'unit'       => $item->unit,
                        'unit_cost'  => $item->unit_cost,
                        'total_cost' => $item->total_cost,
                    ]),
                ])->values(),
            ],

            'ntp' => [
                'ntps' => $project->ntps()->with('rfq.items')->get()->map(fn ($ntp) => [
                    'id'             => $ntp->id,
                    'ntp_no'         => $ntp->ntp_no,
                    'contractor'     => $ntp->contractor_name,
                    'baseline_start' => optional($ntp->baseline_start)->format('M d, Y') ?? '-',
                    'baseline_end'   => optional($ntp->baseline_end)->format('M d, Y') ?? '-',
                    'approved_cost'  => (float) $ntp->approved_cost,
                    'issued_date'    => optional($ntp->issued_date)->format('M d, Y') ?? '-',
                    'scope_items'    => $ntp->rfq
                        ? $ntp->rfq->items->map(fn ($item) => [
                            'seq'         => $item->seq,
                            'description' => $item->description,
                            'qty'         => $item->qty,
                            'unit'        => $item->unit,
                        ])->values()->all()
                        : [],
                ])->values(),
            ],

            'permits' => [
                'permits' => $project->permits()->with('files')->get()->map(fn ($permit) => [
                    'id'       => $permit->id,
                    'label'    => $permit->label,
                    'doc_type' => $permit->doc_type,
                    'files'    => $permit->files->map(fn ($f) => [
                        'id'       => $f->id,
                        'filename' => $f->filename,
                        'url'      => Storage::disk('public')->url($f->path),
                        'mime'     => $f->mime_type,
                    ]),
                ])->values(),
            ],

            'vof' => [
                'vofs' => $project->variationOrders()->get()->map(fn ($vo) => [
                    'id'                => $vo->id,
                    'vo_no'             => $vo->vo_no,
                    'title'             => $vo->title,
                    'description'       => $vo->description,
                    'amount'            => (float) $vo->amount,
                    'status'            => ucfirst($vo->status),
                    'submitted_date'    => optional($vo->submitted_date)->format('M d, Y') ?? '-',
                    'approved_date'     => optional($vo->approved_date)->format('Y-m-d'),
                    'requestor'         => $vo->requestor,
                    'date_of_request'   => optional($vo->date_of_request)->format('Y-m-d'),
                    'priority'          => $vo->priority,
                    'attachment_url'    => $vo->attachment ? Storage::disk('public')->url($vo->attachment) : null,
                    'scope_original'    => $vo->scope_original,
                    'scope_proposed'    => $vo->scope_proposed,
                    'scope_remark'      => $vo->scope_remark,
                    'schedule_original' => $vo->schedule_original,
                    'schedule_proposed' => $vo->schedule_proposed,
                    'schedule_remark'   => $vo->schedule_remark,
                    'cost_original'     => $vo->cost_original,
                    'cost_proposed'     => $vo->cost_proposed,
                    'cost_remark'       => $vo->cost_remark,
                ])->values(),
            ],

            'qpp' => [
                'qpps' => $project->qualityDocs()->get()->map(fn ($doc) => [
                    'id'       => $doc->id,
                    'label'    => $doc->label,
                    'doc_type' => $doc->doc_type,
                    'filename' => $doc->filename,
                    'url'      => Storage::disk('public')->url($doc->file_path),
                    'remarks'  => $doc->remarks,
                    'created'  => $doc->created_at?->format('M d, Y') ?? '-',
                ])->values(),
            ],

            'mtr' => [
                'mtrs' => $project->mtrDocs()->get()->map(fn ($doc) => [
                    'id'            => $doc->id,
                    'label'         => $doc->label,
                    'material_type' => $doc->material_type,
                    'test_date'     => optional($doc->test_date)->format('M d, Y') ?? '-',
                    'filename'      => $doc->filename,
                    'url'           => Storage::disk('public')->url($doc->file_path),
                    'remarks'       => $doc->remarks,
                ])->values(),
            ],

            'rfp' => [
                'ntps'     => $project->ntps()->get()->map(fn ($n) => [
                    'id'            => $n->id,
                    'ntp_no'        => $n->ntp_no,
                    'contractor'    => $n->contractor_name,
                    'approved_cost' => (float) $n->approved_cost,
                ])->values(),
                'billings' => $project->billings()->with('ntp')->get()->map(fn ($b) => [
                    'id'              => $b->id,
                    'stmt_no'         => $b->stmt_no,
                    'billing_type'    => $b->billing_type,
                    'period_from'     => optional($b->period_from)->format('M d, Y') ?? '-',
                    'period_to'       => optional($b->period_to)->format('M d, Y') ?? '-',
                    'period_from_raw' => optional($b->period_from)->format('Y-m-d'),
                    'period_to_raw'   => optional($b->period_to)->format('Y-m-d'),
                    'amount'          => (float) $b->amount,
                    'progress_pct'    => $b->progress_pct !== null ? (float) $b->progress_pct : null,
                    'summary'         => $b->summary,
                    'remarks'         => $b->remarks,
                    'status'          => ucfirst($b->status),
                    'status_raw'      => $b->status,
                    'filename'        => $b->filename,
                    'url'             => $b->file_path ? Storage::disk('public')->url($b->file_path) : null,
                    'attachments'         => $b->attachments ?? [],
                    'recommendation'      => $b->recommendation,
                    'ntp_id'              => $b->project_ntp_id,
                    'ntp_no'              => $b->ntp?->ntp_no,
                    'ntp_contractor'      => $b->ntp?->contractor_name,
                    'ntp_approved_cost'   => $b->ntp ? (float) $b->ntp->approved_cost : null,
                ])->values(),
            ],

            'ioc', 'acr' => [
                'cost_codes' => CostCode::orderBy('name')->get(['name'])
                    ->map(fn ($row) => ['value' => (string) $row->name, 'label' => (string) $row->name])
                    ->values(),
                'iocs' => $project->iocItems()->get()->map(fn ($item) => [
                    'id'          => $item->id,
                    'description' => $item->description,
                    'cost_code'   => $item->cost_code,
                    'amount'      => (float) $item->amount,
                    'filename'    => $item->filename,
                    'url'         => $item->file_path ? Storage::disk('public')->url($item->file_path) : null,
                    'created'     => $item->created_at?->format('M d, Y') ?? '-',
                ])->values(),
            ],

            'psr' => [
                'reports' => $project->weeklyReports()->get()->map(fn ($r) => [
                    'id'                => $r->id,
                    'week_code'         => $r->week_code,
                    'completion_pct'    => $r->completion_pct,
                    'identified_issues' => $r->identified_issues,
                    'progress_updates'  => $r->progress_updates,
                    'submitted_date'    => optional($r->submitted_date)->format('M d, Y') ?? '-',
                    'filename'          => $r->filename,
                    'url'               => $r->file_path ? Storage::disk('public')->url($r->file_path) : null,
                ])->values(),
            ],

            'at' => [
                'logs' => AuditTrail::where('reference_type', Project::class)
                    ->where('reference_id', $project->id)
                    ->with('user')
                    ->latest()
                    ->limit(200)
                    ->get()
                    ->map(fn ($log) => [
                        'date'   => $log->created_at?->format('M d, Y') ?? '-',
                        'time'   => $log->created_at?->format('h:i A') ?? '-',
                        'user'   => $log->user?->name ?? 'System',
                        'action' => $log->action,
                        'module' => $log->changes['module'] ?? 'Project',
                        'ip'     => $log->ip_address ?? ($log->changes['ip'] ?? '—'),
                        'type'   => $log->changes['type'] ?? 'update',
                    ])->values(),
            ],

            'todo' => [
                'tasks' => $project->tasks()->get()->map(fn ($t) => [
                    'id'          => $t->id,
                    'task_name'   => $t->task_name,
                    'target_date' => $t->target_date,
                    'status'      => $t->status,
                ])->values(),
            ],

            default => [],
        };
    }

    private function validatedProjectData(Request $request): array
    {
        return $request->validate([
            'project_type' => ['required', 'in:major,minor'],
            'title' => ['required', 'string', 'max:255'],
            'project_manager' => ['required', 'exists:users,id'],
            'site' => ['required', 'string', 'max:255'],
            'asset_id' => ['required', 'string', 'max:255'],
            'cls' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:80'],
            'work_force' => ['required', 'string', 'max:255'],
            'wr_no' => ['required', 'string', 'max:255'],
            'wr_date' => ['required', 'date'],
            'dept_owner' => ['required', 'string', 'max:255'],
            'cost_code' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'service_type' => ['required', 'string', 'max:255'],
            'deadline' => ['required', 'date'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'structure_type' => ['nullable', 'string', 'max:255'],
            'jip' => ['boolean'],
            'need_civil' => ['boolean'],
            'need_electrical' => ['boolean'],
            'need_mechanical' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'project_request_id' => ['nullable', 'exists:project_requests,id', 'unique:projects,project_request_id'],
            'proposal_document' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx'],
        ]);
    }

    private function masterDataOptions(): array
    {
        $option = fn ($row) => ['value' => (string) $row->name, 'label' => (string) $row->name];

        return [
            'managers' => User::orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name]),
            'sites' => Site::orderBy('name')->get(['name'])->map($option),
            'assets' => Structure::orderBy('name')->get(['name'])->map($option),
            'departments' => Department::orderBy('name')->get(['name', 'description'])->map(fn ($row) => [
                'value' => (string) $row->name,
                'label' => $row->description ? "{$row->name} — {$row->description}" : (string) $row->name,
                'displayLabel' => (string) $row->name,
            ]),
            'classes' => MasterClass::orderBy('name')->get(['name'])->map($option),
            'priorities' => Priority::orderByRaw('CASE WHEN sequence_no IS NULL THEN 1 ELSE 0 END, sequence_no ASC')
                ->orderBy('name')
                ->get(['name'])
                ->map($option),
            'statuses' => MasterStatus::orderBy('name')->get(['name'])->map(fn ($row) => [
                'value' => $this->statusKeyFromName($row->name),
                'label' => $row->name,
            ])->values(),
            'workForces' => WorkForce::orderBy('name')->get(['name'])->map($option),
            'costCodes' => CostCode::orderBy('name')->get(['name'])->map($option),
            'categories' => Category::orderBy('name')->get(['name'])->map($option),
            'serviceTypes' => ServiceType::orderBy('name')->get(['name'])->map($option),
            'structures' => Structure::orderBy('name')->get(['name'])->map($option),
        ];
    }

    private function filterableFields(): array
    {
        return [
            'project_no' => 'project_no',
            'project_manager' => 'project_manager_name',
            'title' => 'title',
            'project_type' => 'project_type',
            'site' => 'site',
            'asset_id' => 'asset_id',
            'cls' => 'class_name',
            'priority_no' => 'priority',
            'status' => 'status_key',
            'wr_no' => 'wr_no',
            'wr_date_received' => 'wr_date',
            'dept_owner' => 'dept_owner',
            'cost_code' => 'cost_code',
            'category' => 'category',
            'service_type' => 'service_type',
            'work_force' => 'work_force',
            'structure_type' => 'structure_type',
        ];
    }

    private function nextProjectNo(): string
    {
        $year = now()->format('Y');
        $latest = Project::withTrashed()
            ->where('project_no', 'like', "PRJ-{$year}-%")
            ->orderByDesc('project_no')
            ->value('project_no');

        $next = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return 'PRJ-' . $year . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function projectListData(Project $project): array
    {
        return [
            'id' => $project->id,
            'project_no' => $project->project_no,
            'title' => $project->title,
            'type' => $this->projectType($project->class_name),
            'progress' => $project->completion_percent,
            'project_manager' => $project->project_manager_name ?? $project->manager?->name ?? 'Unassigned',
            'encoded_by' => $project->creator?->name ?? 'Unassigned',
            'dept_owner' => $project->dept_owner,
            'status' => self::STATUS_LABELS[$project->status_key] ?? $project->status_key,
            'created_at' => $project->created_at?->format('M d, Y h:i A'),
            'can' => [
                'update' => auth()->user()->can('update', $project),
                'delete' => auth()->user()->can('delete', $project),
            ],
        ];
    }

    private function projectDetailData(Project $project): array
    {
        $daysElapsed = $project->daysElapsed();
        $daysRemaining = $project->daysRemaining();

        return [
            'id' => $project->id,
            'project_no' => $project->project_no,
            'title' => $project->title,
            'site' => $project->site,
            'project_manager' => $project->project_manager_name ?? $project->manager?->name ?? 'Unassigned',
            'encoded_by' => $project->creator?->name ?? 'Unassigned',
            'encoded_at' => $project->created_at?->format('M d, Y h:i A') ?? '-',
            'status' => self::STATUS_LABELS[$project->status_key] ?? $project->status_key,
            'status_key' => $project->status_key,
            'deadline' => optional($project->deadline)->format('M d, Y') ?? '-',
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'budget_total' => (float) $project->budget_total,
            'budget_paid' => (float) $project->budget_paid,
            'completion_percent' => $project->completion_percent,
            'project_health' => $project->health(),
            'asset_id' => $project->asset_id,
            'cost_code' => $project->cost_code,
            'wr_no' => $project->wr_no,
            'wr_date' => optional($project->wr_date)->format('M d, Y') ?? '-',
            'priority' => $project->priority,
            'dept_owner' => $project->dept_owner,
            'owner_email' => $project->owner_email ?? '-',
            'cls' => $project->class_name,
            'category' => $project->category,
            'service_type' => $project->service_type,
            'work_force' => $project->work_force,
            'jip' => $project->jip ? 'Yes' : 'No',
            'structure_type' => $project->structure_type ?? '-',
            'technical_plans' => [
                'civil' => $project->need_civil,
                'electrical' => $project->need_electrical,
                'mechanical' => $project->need_mechanical,
            ],
            'admin_notes'          => $project->notes ?? 'No notes recorded.',
            'project_type'         => $project->project_type ?? 'minor',
            'proposal_document_url'=> $project->proposal_document
                ? Storage::disk('public')->url($project->proposal_document)
                : null,
            'status_logs' => $project->statusLogs->map(fn ($log) => [
                'id' => $log->id,
                'date' => $log->created_at?->format('M d, Y') ?? '',
                'time' => $log->created_at?->format('h:i A') ?? '',
                'user' => $log->user?->name ?? 'System',
                'status' => $log->status_label,
                'status_key' => $log->status_key,
                'remarks' => $log->remarks ?? '',
            ]),
            'can' => [
                'update' => auth()->user()->can('update', $project),
                'delete' => auth()->user()->can('delete', $project),
            ],
        ];
    }

    private function projectFormData(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'project_manager' => (string) $project->project_manager_id,
            'site' => $project->site,
            'asset_id' => $project->asset_id,
            'cls' => $project->class_name,
            'priority' => $project->priority,
            'status' => $project->status_key,
            'work_force' => $project->work_force,
            'wr_no' => $project->wr_no,
            'wr_date' => optional($project->wr_date)->format('Y-m-d'),
            'dept_owner' => $project->dept_owner,
            'cost_code' => $project->cost_code,
            'category' => $project->category,
            'service_type' => $project->service_type,
            'deadline' => optional($project->deadline)->format('Y-m-d'),
            'owner_email' => $project->owner_email ?? '',
            'structure_type' => $project->structure_type ?? '',
            'jip' => $project->jip,
            'need_civil' => $project->need_civil,
            'need_electrical' => $project->need_electrical,
            'need_mechanical' => $project->need_mechanical,
            'notes' => $project->notes ?? '',
            'project_type' => $project->project_type ?? 'minor',
            'proposal_document_url' => $project->proposal_document
                ? Storage::disk('public')->url($project->proposal_document)
                : null,
        ];
    }

    private function projectFormDataFromRequest(ProjectRequest $request): array
    {
        return [
            'project_request_id' => (string) $request->id,
            'title' => $request->title,
            'project_manager' => '',
            'site' => '',
            'asset_id' => '',
            'cls' => '',
            'priority' => '',
            'status' => '',
            'work_force' => '',
            'wr_no' => $request->request_no ?? '',
            'wr_date' => now()->format('Y-m-d'),
            'dept_owner' => '',
            'cost_code' => '',
            'category' => '',
            'service_type' => '',
            'deadline' => now()->addDays(30)->format('Y-m-d'),
            'owner_email' => $request->requester?->email ?? '',
            'structure_type' => '',
            'jip' => false,
            'need_civil' => false,
            'need_electrical' => false,
            'need_mechanical' => false,
            'notes' => '',
        ];
    }

    private function statusKeyFromName(string $name): string
    {
        $existing = array_search($name, self::STATUS_LABELS, true);

        return $existing ?: strtoupper(str_replace([' ', '-', '/', '&'], ['_', '_', '_', 'AND'], $name));
    }

    private function projectType(string $className): string
    {
        return str_contains(strtolower($className), 'major') || str_contains(strtolower($className), 'tier 1')
            ? 'Major'
            : 'Minor';
    }

}
