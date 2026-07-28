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
use App\Models\ProjectCompletion;
use App\Models\ProjectNtp;
use App\Models\ProjectRequest;
use App\Models\ServiceType;
use App\Models\Setting;
use App\Models\Site;
use App\Models\Structure;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkForce;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /** Defined on the model so exports and reports share one mapping. */
    private const STATUS_LABELS = Project::STATUS_LABELS;

    public function index(Request $request): Response
    {
        // Sub-projects live under their parent's operations hub and are never
        // listed here — the management list only shows top-level projects.
        // Children are eager-loaded for the completion roll-up.
        $query = Project::with(['manager', 'creator', 'children:id,parent_id,completion_percent'])
            ->whereNull('parent_id')->latest();

        $user = $request->user();
        if (!$user->hasRole(['approver', 'assistant_manager', 'admin'])) {
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

        // Sub-project mode: started from an issued NTP on a parent project.
        if ($request->filled('parent') && $request->filled('ntp')) {
            $parent = Project::findOrFail($request->integer('parent'));
            $ntp = ProjectNtp::where('project_id', $parent->id)->findOrFail($request->integer('ntp'));

            abort_unless($ntp->status === 'issued', 422, 'Sub-projects can only be created from an issued NTP.');
            abort_if(Project::where('source_ntp_id', $ntp->id)->exists(), 409, 'This NTP already has a sub-project.');

            return Inertia::render('project-management/create', [
                'next_project_no' => $this->subProjectNo($parent),
                'project' => $this->subProjectFormData($parent, $ntp),
                'sub_context' => [
                    'parent_id'     => $parent->id,
                    'parent_no'     => $parent->project_no,
                    'parent_title'  => $parent->title,
                    'source_ntp_id' => $ntp->id,
                    'ntp_no'        => $ntp->ntp_no,
                    'contractor'    => $ntp->contractor_name,
                ],
                ...$this->masterDataOptions(),
            ]);
        }

        $projectRequest = $request->filled('request_id')
            ? ProjectRequest::with(['project', 'requester'])->findOrFail($request->integer('request_id'))
            : null;

        abort_if($projectRequest?->project, 409, 'This project request already has a project.');

        return Inertia::render('project-management/create', [
            'next_project_no' => $this->nextProjectNo(),
            'project' => $projectRequest ? $this->projectFormDataFromRequest($projectRequest) : null,
            'sub_context' => null,
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

        // Sub-project mode: validate the parent/NTP pairing and derive the number.
        $sub = $request->validate([
            'parent_id'     => ['nullable', 'exists:projects,id'],
            'source_ntp_id' => ['nullable', 'exists:project_ntps,id'],
        ]);
        $parent = null;
        if (!empty($sub['parent_id'])) {
            $parent = Project::findOrFail($sub['parent_id']);
            $ntp = ProjectNtp::where('project_id', $parent->id)->findOrFail($sub['source_ntp_id']);
            abort_unless($ntp->status === 'issued', 422, 'Sub-projects can only be created from an issued NTP.');
            abort_if(Project::where('source_ntp_id', $ntp->id)->exists(), 409, 'This NTP already has a sub-project.');
        }

        // Major projects need an approved proposal — except sub-projects, which
        // inherit the parent's already-approved proposal.
        if (!$parent && $data['project_type'] === 'major' && !$request->hasFile('proposal_document')) {
            return back()->withErrors(['proposal_document' => 'Approved proposal document is required for major projects.'])->withInput();
        }

        $project = Project::create([
            'project_request_id' => $data['project_request_id'] ?? null,
            'parent_id' => $parent?->id,
            'source_ntp_id' => $parent ? $sub['source_ntp_id'] : null,
            'project_no' => $parent ? $this->subProjectNo($parent) : $this->nextProjectNo(),
            'title' => $data['title'],
            'project_manager_id' => $manager?->id,
            'project_manager_name' => $manager?->name,
            'site' => $data['site'],
            'asset_id' => $data['asset_id'],
            'class_name' => $data['cls'] ?? '',
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
            'budget_total' => $data['project_cost'] ?? 0,
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
            ->with('success', $parent ? 'Sub-project created successfully.' : 'Project registered successfully.');
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        // Department users (requesters) get a read-only summary with the RFQ list
        // only — the full operations hub is hidden from them.
        $deptView = auth()->user()->hasRole('requestor');

        return Inertia::render('project-management/show', [
            'project'        => $this->projectDetailData($project->load(['manager', 'creator', 'statusLogs.user', 'completion', 'parent', 'children'])),
            'hub_counts'     => $this->hubCounts($project),
            'is_dept_view'   => $deptView,
            'active_section' => $deptView ? 'rfq' : null,
            'hub_data'       => $deptView ? $this->hubSectionData($project, 'rfq') : [],
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
            'class_name' => $data['cls'] ?? '',
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
            'budget_total' => $data['project_cost'] ?? 0,
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

    /**
     * Store/update the completion details used to print the Project Completion
     * & Acceptance Certificate (FRM-06) and Project Completion Summary (FRM-12).
     */
    public function saveCompletion(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'reference_no'        => ['nullable', 'string', 'max:100'],
            'sub_project_title'   => ['nullable', 'string', 'max:191'],
            'classification'      => ['nullable', 'string', 'max:100'],
            'plan_baseline_start' => ['nullable', 'date'],
            'plan_baseline_end'   => ['nullable', 'date'],
            'plan_actual_start'   => ['nullable', 'date'],
            'plan_actual_end'     => ['nullable', 'date'],
            'con_baseline_start'  => ['nullable', 'date'],
            'con_baseline_end'    => ['nullable', 'date'],
            'con_actual_start'    => ['nullable', 'date'],
            'con_actual_end'      => ['nullable', 'date'],
            'contractor'          => ['nullable', 'string', 'max:191'],
            'baseline_amount'     => ['nullable', 'numeric'],
            'actual_amount'       => ['nullable', 'numeric'],
            'payment_status'      => ['nullable', 'string', 'max:100'],
            'completion_status'   => ['nullable', 'string', 'max:100'],
            'request_date'        => ['nullable', 'date'],
            'date_prepared'       => ['nullable', 'date'],
            'issued_on'           => ['nullable', 'date'],
            'received_by'         => ['nullable', 'string', 'max:191'],
            'accepted_by'         => ['nullable', 'string', 'max:191'],
            'acknowledged_by'     => ['nullable', 'string', 'max:191'],
            'keep_photos'         => ['nullable', 'array'],
            'keep_photos.*'       => ['string'],
            'photos'              => ['nullable', 'array', 'max:12'],
            'photos.*'            => ['image', 'max:5120'],
        ]);

        $existing = $project->completion;

        // Reconcile photos: keep the paths the client kept, delete the rest, add uploads.
        $keep    = $data['keep_photos'] ?? [];
        $current = $existing?->photos ?? [];
        foreach (array_diff($current, $keep) as $removed) {
            Storage::disk('public')->delete($removed);
        }
        $photos = array_values(array_intersect($current, $keep));

        foreach ($request->file('photos', []) as $file) {
            $photos[] = $file->store("projects/{$project->id}/completion", 'public');
        }

        $payload = collect($data)->except(['keep_photos', 'photos'])->all();
        $payload['photos']   = $photos;
        $payload['saved_by'] = auth()->id();

        $project->completion()->updateOrCreate(['project_id' => $project->id], $payload);

        AuditTrail::log('Completion certificate details saved', $project, ['module' => 'Project', 'type' => 'update']);

        return back()->with('success', 'Completion details saved.');
    }

    public function hub(Project $project, string $section): Response
    {
        $this->authorize('view', $project);

        $validSections = ['rfq', 'ntp', 'permits', 'vof', 'qpp', 'mtr', 'rfp', 'ioc', 'acr', 'psr', 'at', 'todo'];
        abort_unless(in_array($section, $validSections, true), 404);

        // RFQ and NTP are procurement steps owned by the main project (NTP is
        // what spawns sub-projects) — sub-projects never have their own.
        abort_if($project->parent_id && in_array($section, ['rfq', 'ntp'], true), 404);

        $project->load(['manager', 'creator', 'statusLogs.user', 'completion', 'parent', 'children']);

        // Department users only ever see the read-only RFQ list, never the rest
        // of the hub — force the section regardless of which URL they hit.
        $deptView = auth()->user()->hasRole('requestor');

        return Inertia::render('project-management/show', [
            'project'        => $this->projectDetailData($project),
            'active_section' => $deptView ? 'rfq' : $section,
            'hub_data'       => $this->hubSectionData($project, $deptView ? 'rfq' : $section),
            'hub_counts'     => $this->hubCounts($project),
            'is_dept_view'   => $deptView,
        ]);
    }

    private function hubCounts(Project $project): array
    {
        // Counts span the project and its sub-projects, matching the rolled-up
        // hub views so the sidebar badges agree with what's listed.
        $ids = collect([$project->id])->concat($project->children->pluck('id'))->all();
        $countAcross = fn (string $relation) => $project->newQuery()
            ->whereIn('id', $ids)->withCount($relation)->get()->sum("{$relation}_count");

        return [
            'rfq'     => $countAcross('rfqs'),
            'ntp'     => $countAcross('ntps'),
            'permits' => $countAcross('permits'),
            'vof'     => $countAcross('variationOrders'),
            'qpp'     => $countAcross('qualityDocs'),
            'mtr'     => $countAcross('mtrDocs'),
            'rfp'     => $countAcross('billings'),
            'ioc'     => $countAcross('iocItems'),
            'acr'     => $countAcross('iocItems'),
            'psr'     => $countAcross('weeklyReports'),
            'at'      => AuditTrail::where('reference_type', Project::class)->whereIn('reference_id', $ids)->count(),
            'todo'    => $countAcross('tasks'),
        ];
    }

    private function hubSectionData(Project $project, string $section): array
    {
        // Per-RFQ audit history: project audit entries tagged with an rfq_id,
        // grouped so each RFQ row can show its own trail.
        $rfqAudits = $section === 'rfq'
            ? AuditTrail::where('reference_type', Project::class)
                ->where('reference_id', $project->id)
                ->with('user')
                ->latest()
                ->get()
                ->filter(fn ($a) => !empty($a->changes['rfq_id']))
                ->groupBy(fn ($a) => (int) $a->changes['rfq_id'])
            : collect();

        // Sub-projects keyed by the NTP they were spawned from, so each NTP row
        // can show either a "Create Sub-Project" or "View Sub-Project" action.
        $subProjectsByNtp = $section === 'ntp'
            ? $project->children()->whereNotNull('source_ntp_id')->get()->keyBy('source_ntp_id')
            : collect();

        return match ($section) {
            'rfq' => [
                // Own RFQs plus, for a parent, its sub-projects' RFQs (read-only,
                // tagged). NTP status is resolved against each row's owning project.
                'rfqs' => $this->collectHubRows(
                    $project,
                    fn ($p) => $p->rfqs()->with('items')->get(),
                    fn ($rfq, $sub) => $this->rfqRow($rfq, $sub ?? $project, $sub, $sub ? collect() : ($rfqAudits[$rfq->id] ?? collect())),
                ),
                // Only accredited (and active) suppliers are selectable when dispatching RFQs.
                'suppliers' => Supplier::where('is_active', true)->where('accredited', true)->orderBy('company')->get(['company', 'email'])
                    ->map(fn ($s) => ['name' => $s->company, 'email' => $s->email ?? ''])
                    ->values(),
            ],

            'ntp' => [
                'ntps' => $this->collectHubRows(
                    $project,
                    fn ($p) => $p->ntps()->with('rfq.items', 'reviewer')->get(),
                    fn ($ntp, $sub) => $this->ntpRow($ntp, $sub, $subProjectsByNtp),
                ),
            ],

            'permits' => [
                'permits' => $this->collectHubRows($project, fn ($p) => $p->permits()->with('files')->get(), fn ($m, $sub) => $this->permitRow($m, $sub)),
            ],

            'vof' => [
                'vofs' => $this->collectHubRows($project, fn ($p) => $p->variationOrders()->get(), fn ($m, $sub) => $this->vofRow($m, $sub)),
            ],

            'qpp' => [
                'qpps' => $this->collectHubRows($project, fn ($p) => $p->qualityDocs()->get(), fn ($m, $sub) => $this->qppRow($m, $sub)),
            ],

            'mtr' => [
                'mtrs' => $this->collectHubRows($project, fn ($p) => $p->mtrDocs()->get(), fn ($m, $sub) => $this->mtrRow($m, $sub)),
            ],

            'rfp' => [
                'can_manage_status' => auth()->user()->can('manageBillingStatus', $project),
                'ntps'     => $project->ntps()->get()->map(fn ($n) => [
                    'id'            => $n->id,
                    'ntp_no'        => $n->ntp_no,
                    'contractor'    => $n->contractor_name,
                    'approved_cost' => (float) $n->approved_cost,
                ])->values(),
                // Own billings plus, for a parent, its sub-projects' billings
                // (read-only in the UI, tagged with their sub-project).
                'billings' => $this->collectHubRows($project, fn ($p) => $p->billings()->with(['ntp', 'statusLogs.user'])->get(), fn ($b, $sub) => $this->billingRow($b, $sub)),
            ],

            'ioc', 'acr' => [
                'cost_codes' => CostCode::where('is_active', true)->orderBy('name')->get(['name'])
                    ->map(fn ($row) => ['value' => (string) $row->name, 'label' => (string) $row->name])
                    ->values(),
                'iocs' => $this->collectHubRows($project, fn ($p) => $p->iocItems()->get(), fn ($m, $sub) => $this->iocRow($m, $sub)),
            ],

            'psr' => [
                // Form definition, so the submission form, the report view and the
                // import template all render the same checklist.
                'checklist'   => config('psr.checklist'),
                'issue_rows'  => config('psr.issue_rows'),
                'statuses'    => config('psr.statuses'),
                'ntps' => $project->ntps()->get()->map(fn ($n) => [
                    'id'         => $n->id,
                    'ntp_no'     => $n->ntp_no,
                    'contractor' => $n->contractor_name,
                ])->values(),
                // Own reports plus, for a parent, its sub-projects' reports
                // (read-only in the UI, tagged with their sub-project).
                'reports' => $this->collectHubRows($project, fn ($p) => $p->weeklyReports()->with('ntp')->get(), fn ($r, $sub) => $this->weeklyReportRow($r, $sub)),
            ],

            'at' => [
                // Own logs plus, for a parent, its sub-projects' logs, tagged with
                // the originating project. Combined and re-sorted, newest first.
                'logs' => (function () use ($project) {
                    $noById = collect([$project])->concat($project->children)
                        ->mapWithKeys(fn ($p) => [$p->id => $p->id === $project->id ? null : $p->project_no]);

                    return AuditTrail::where('reference_type', Project::class)
                        ->whereIn('reference_id', $noById->keys())
                        ->with('user')
                        ->latest()
                        ->limit(300)
                        ->get()
                        ->map(fn ($log) => [
                            'date'   => $log->created_at?->format('M d, Y') ?? '-',
                            'time'   => $log->created_at?->format('h:i A') ?? '-',
                            'user'   => $log->user?->name ?? 'System',
                            'action' => $log->action,
                            'module' => $log->changes['module'] ?? 'Project',
                            'ip'     => $log->ip_address ?? ($log->changes['ip'] ?? '—'),
                            'type'   => $log->changes['type'] ?? 'update',
                            'fields' => $log->changes['fields'] ?? [],
                            'sub_project_no' => $noById->get($log->reference_id),
                        ])->values();
                })(),
            ],

            'todo' => [
                'tasks' => $this->collectHubRows($project, fn ($p) => $p->tasks()->get(), fn ($m, $sub) => $this->todoRow($m, $sub)),
            ],

            default => [],
        };
    }

    /**
     * Collect a hub section's rows for a project and, when it's a parent, its
     * sub-projects too. Each sub-project's rows are tagged (via $mapRow's second
     * arg) so the UI can render them read-only. `$fetch` returns a project's raw
     * models; `$mapRow($model, ?Project $sub)` serializes one row.
     */
    private function collectHubRows(Project $project, callable $fetch, callable $mapRow): array
    {
        $rows = $fetch($project)->map(fn ($m) => $mapRow($m, null));

        foreach ($project->children as $child) {
            $rows = $rows->concat($fetch($child)->map(fn ($m) => $mapRow($m, $child)));
        }

        return $rows->values()->all();
    }

    private function billingRow($b, ?Project $sub): array
    {
        return [
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
            'ntp_date'            => optional($b->ntp?->issued_date)->format('M d, Y'),
            'ntp_contractor'      => $b->ntp?->contractor_name,
            'ntp_approved_cost'   => $b->ntp ? (float) $b->ntp->approved_cost : null,
            // null for the project's own rows; set for a sub-project's rows.
            'sub_project_id'      => $sub?->id,
            'sub_project_no'      => $sub?->project_no,
            'status_logs'         => $b->statusLogs->map(fn ($log) => [
                'id'      => $log->id,
                'date'    => $log->created_at?->format('M d, Y') ?? '-',
                'time'    => $log->created_at?->format('h:i A') ?? '-',
                'user'    => $log->user?->name ?? 'System',
                'status'  => ucfirst($log->status),
                'remarks' => $log->remarks ?? '—',
            ])->values(),
        ];
    }

    private function weeklyReportRow($r, ?Project $sub): array
    {
        return [
            'id'                => $r->id,
            'week_code'         => $r->week_code,
            'completion_pct'    => $r->completion_pct,
            'identified_issues' => $r->identified_issues,
            'progress_updates'  => $r->progress_updates,
            'checklist'         => $r->checklist ?? [],
            'issues'            => $r->issues ?? [],
            'submitted_date'    => optional($r->submitted_date)->format('M d, Y') ?? '-',
            'filename'          => $r->filename,
            'url'               => $r->file_path ? Storage::disk('public')->url($r->file_path) : null,
            'ntp_id'            => $r->project_ntp_id,
            'ntp_no'            => $r->ntp?->ntp_no,
            'ntp_contractor'    => $r->ntp?->contractor_name,
            // null for the project's own rows; set for a sub-project's rows.
            'sub_project_id'    => $sub?->id,
            'sub_project_no'    => $sub?->project_no,
        ];
    }

    /** $owner is the project the row belongs to (parent or the sub itself). */
    private function rfqRow($rfq, Project $owner, ?Project $sub, $audits): array
    {
        $activeNtp = $owner->ntps()->where('project_rfq_id', $rfq->id)->where('status', '!=', 'rejected');

        return [
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
            // A rejected NTP no longer blocks the RFQ — treat it as "no NTP".
            'has_ntp'         => (clone $activeNtp)->exists(),
            'ntp_status'      => optional((clone $activeNtp)->first())->status,
            'audit_trail'     => collect($audits)->map(fn ($a) => [
                'action' => $a->action,
                'user'   => $a->user?->name ?? 'System',
                'date'   => $a->created_at?->format('M d, Y h:i A') ?? '-',
                'type'   => $a->changes['type'] ?? 'update',
                'fields' => $a->changes['fields'] ?? [],
            ])->values(),
            'sub_project_id'  => $sub?->id,
            'sub_project_no'  => $sub?->project_no,
            'items'           => $rfq->items->map(fn ($item) => [
                'seq'        => $item->seq,
                'description'=> $item->description,
                'qty'        => $item->qty,
                'unit'       => $item->unit,
                'unit_cost'  => $item->unit_cost,
                'total_cost' => $item->total_cost,
            ]),
        ];
    }

    private function ntpRow($ntp, ?Project $sub, $spawnedByNtp): array
    {
        $spawned = $spawnedByNtp->get($ntp->id);

        return [
            'id'             => $ntp->id,
            'ntp_no'         => $ntp->ntp_no,
            'contractor'     => $ntp->contractor_name,
            'baseline_start' => optional($ntp->baseline_start)->format('M d, Y') ?? '-',
            'baseline_end'   => optional($ntp->baseline_end)->format('M d, Y') ?? '-',
            'approved_cost'  => (float) $ntp->approved_cost,
            'status'         => $ntp->status,
            'issued_date'    => optional($ntp->issued_date)->format('M d, Y') ?? '-',
            'reviewed_by'    => $ntp->reviewer->name ?? null,
            'review_remarks' => $ntp->review_remarks,
            // The sub-project spawned FROM this issued NTP (parent rows only).
            'spawned_sub_id' => optional($spawned)->id,
            'spawned_sub_no' => optional($spawned)->project_no,
            // The sub-project this NTP itself belongs to (roll-up tag).
            'sub_project_id' => $sub?->id,
            'sub_project_no' => $sub?->project_no,
            'scope_items'    => $ntp->rfq
                ? $ntp->rfq->items->map(fn ($item) => [
                    'seq'         => $item->seq,
                    'description' => $item->description,
                    'qty'         => $item->qty,
                    'unit'        => $item->unit,
                    'unit_cost'   => $item->unit_cost !== null ? (float) $item->unit_cost : null,
                    'total_cost'  => $item->total_cost !== null ? (float) $item->total_cost : null,
                ])->values()->all()
                : [],
        ];
    }

    private function permitRow($permit, ?Project $sub): array
    {
        return [
            'id'       => $permit->id,
            'label'    => $permit->label,
            'doc_type' => $permit->doc_type,
            'sub_project_id' => $sub?->id,
            'sub_project_no' => $sub?->project_no,
            'files'    => $permit->files->map(fn ($f) => [
                'id'       => $f->id,
                'filename' => $f->filename,
                'url'      => Storage::disk('public')->url($f->path),
                'mime'     => $f->mime_type,
            ]),
        ];
    }

    private function vofRow($vo, ?Project $sub): array
    {
        return [
            'id'                => $vo->id,
            'vo_no'             => $vo->vo_no,
            'title'             => $vo->title,
            'description'       => $vo->description,
            'amount'            => (float) $vo->amount,
            'duration_days'     => $vo->duration_days,
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
            'sub_project_id'    => $sub?->id,
            'sub_project_no'    => $sub?->project_no,
        ];
    }

    private function qppRow($doc, ?Project $sub): array
    {
        return [
            'id'       => $doc->id,
            'label'    => $doc->label,
            'doc_type' => $doc->doc_type,
            'filename' => $doc->filename,
            'url'      => Storage::disk('public')->url($doc->file_path),
            'remarks'  => $doc->remarks,
            'created'  => $doc->created_at?->format('M d, Y') ?? '-',
            'sub_project_id' => $sub?->id,
            'sub_project_no' => $sub?->project_no,
        ];
    }

    private function mtrRow($doc, ?Project $sub): array
    {
        return [
            'id'            => $doc->id,
            'label'         => $doc->label,
            'material_type' => $doc->material_type,
            'test_date'     => optional($doc->test_date)->format('M d, Y') ?? '-',
            'filename'      => $doc->filename,
            'url'           => Storage::disk('public')->url($doc->file_path),
            'remarks'       => $doc->remarks,
            'sub_project_id' => $sub?->id,
            'sub_project_no' => $sub?->project_no,
        ];
    }

    private function iocRow($item, ?Project $sub): array
    {
        return [
            'id'          => $item->id,
            'description' => $item->description,
            'cost_code'   => $item->cost_code,
            'amount'      => (float) $item->amount,
            'filename'    => $item->filename,
            'url'         => $item->file_path ? Storage::disk('public')->url($item->file_path) : null,
            'created'     => $item->created_at?->format('M d, Y') ?? '-',
            'sub_project_id' => $sub?->id,
            'sub_project_no' => $sub?->project_no,
        ];
    }

    private function todoRow($t, ?Project $sub): array
    {
        return [
            'id'          => $t->id,
            'task_name'   => $t->task_name,
            'target_date' => $t->target_date,
            'status'      => $t->status,
            'sub_project_id' => $sub?->id,
            'sub_project_no' => $sub?->project_no,
        ];
    }

    private function validatedProjectData(Request $request): array
    {
        return $request->validate([
            'project_type' => ['required', 'in:major,minor'],
            'title' => ['required', 'string', 'max:255'],
            'project_manager' => ['required', 'exists:users,id'],
            'site' => ['required', 'string', 'max:255'],
            'asset_id' => ['required', 'string', 'max:255'],
            // Class field is hidden in the UI; keep it optional. class_name is NOT NULL,
            // so an empty value is coalesced to '' on store/update.
            'cls' => ['nullable', 'string', 'max:255'],
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
            'project_cost' => ['nullable', 'numeric', 'min:0'],
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
            // Project Managers are drawn from Project Engineers (the "approver" role).
            'managers' => User::role('approver')->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name]),
            'sites' => Site::where('is_active', true)->orderBy('name')->get(['name'])->map($option),
            'assets' => Structure::where('is_active', true)->orderBy('name')->get(['name'])->map($option),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['name', 'description'])->map(fn ($row) => [
                'value' => (string) $row->name,
                'label' => $row->description ? "{$row->name} — {$row->description}" : (string) $row->name,
                'displayLabel' => (string) $row->name,
            ]),
            'classes' => MasterClass::where('is_active', true)->orderBy('name')->get(['name'])->map($option),
            'priorities' => Priority::where('is_active', true)
                ->orderByRaw('CASE WHEN sequence_no IS NULL THEN 1 ELSE 0 END, sequence_no ASC')
                ->orderBy('name')
                ->get(['name'])
                ->map($option),
            'statuses' => MasterStatus::where('is_active', true)->orderBy('name')->get(['name'])->map(fn ($row) => [
                'value' => $this->statusKeyFromName($row->name),
                'label' => $row->name,
            ])->values(),
            'workForces' => WorkForce::where('is_active', true)->orderBy('name')->get(['name'])->map($option),
            'costCodes' => CostCode::where('is_active', true)->orderBy('name')->get(['name'])->map($option),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['name'])->map($option),
            'serviceTypes' => ServiceType::where('is_active', true)->orderBy('name')->get(['name'])->map($option),
            'structures' => Structure::where('is_active', true)->orderBy('name')->get(['name'])->map($option),
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

    /**
     * Sub-project numbers derive from the parent (e.g. PRJ-2026-0001 → …-01, -02),
     * making the relationship obvious at a glance. Sequence is per-parent.
     */
    private function subProjectNo(Project $parent): string
    {
        $latest = Project::withTrashed()
            ->where('parent_id', $parent->id)
            ->where('project_no', 'like', $parent->project_no . '-%')
            ->orderByDesc('project_no')
            ->value('project_no');

        $next = $latest ? ((int) substr($latest, -2)) + 1 : 1;

        return $parent->project_no . '-' . str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Prefill the project form for a sub-project: carry over the parent's
     * details, then overlay what the NTP defines (contractor cost, baseline
     * end date, and a title that identifies the NTP). Fully editable after.
     */
    private function subProjectFormData(Project $parent, ProjectNtp $ntp): array
    {
        return [
            ...$this->projectFormData($parent),
            'id' => null,
            'title' => "{$parent->title} — {$ntp->ntp_no}" . ($ntp->contractor_name ? " ({$ntp->contractor_name})" : ''),
            'status' => 'PLANNING',
            'project_cost' => $ntp->approved_cost !== null ? (float) $ntp->approved_cost : (float) $parent->budget_total,
            'deadline' => optional($ntp->baseline_end)->format('Y-m-d') ?? optional($parent->deadline)->format('Y-m-d'),
        ];
    }

    private function projectListData(Project $project): array
    {
        return [
            'id' => $project->id,
            'project_no' => $project->project_no,
            'title' => $project->title,
            'type' => $this->projectType($project->class_name),
            'progress' => $project->effectiveCompletionPercent(),
            'project_manager' => $project->project_manager_name ?? $project->manager?->name ?? 'Unassigned',
            'encoded_by' => $project->creator?->name ?? 'Unassigned',
            'dept_owner' => $project->dept_owner,
            'status' => self::STATUS_LABELS[$project->status_key] ?? $project->status_key,
            'created_at' => $project->created_at?->format('M d, Y h:i A'),
            'budget_total' => (float) $project->budget_total,
            'budget_paid' => (float) $project->budget_paid,
            'deadline' => optional($project->deadline)->format('M d, Y') ?? '—',
            'days_remaining' => $project->deadline ? $project->daysRemaining() : null,
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
            'completion_percent' => $project->effectiveCompletionPercent(),
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
            'owner_name'           => $project->projectRequest?->requester?->name ?? $project->dept_owner,
            'project_request_id'   => $project->project_request_id,
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
            'completion'  => $this->completionData($project),
            'signatories' => $this->signatoryData($project),
            // Sub-project links: the parent (if this is a sub-project) and any
            // children (if this is a parent). Hidden from the management list,
            // so this is the primary way to navigate between them.
            'parent' => $project->parent ? [
                'id'         => $project->parent->id,
                'project_no' => $project->parent->project_no,
                'title'      => $project->parent->title,
            ] : null,
            'sub_projects' => $project->children->map(fn (Project $child) => [
                'id'                 => $child->id,
                'project_no'         => $child->project_no,
                'title'              => $child->title,
                'status'             => self::STATUS_LABELS[$child->status_key] ?? $child->status_key,
                'completion_percent' => $child->completion_percent,
            ])->values(),
            'can' => [
                'update' => auth()->user()->can('update', $project),
                'delete' => auth()->user()->can('delete', $project),
            ],
        ];
    }

    /**
     * Serialize the saved completion record (dates as Y-m-d for form inputs),
     * or null if the project has not been completed/filled yet.
     */
    private function completionData(Project $project): ?array
    {
        $c = $project->completion;
        if (! $c) {
            return null;
        }

        $d = fn ($date) => optional($date)->format('Y-m-d');

        return [
            'reference_no'        => $c->reference_no,
            'sub_project_title'   => $c->sub_project_title,
            'classification'      => $c->classification,
            'plan_baseline_start' => $d($c->plan_baseline_start),
            'plan_baseline_end'   => $d($c->plan_baseline_end),
            'plan_actual_start'   => $d($c->plan_actual_start),
            'plan_actual_end'     => $d($c->plan_actual_end),
            'con_baseline_start'  => $d($c->con_baseline_start),
            'con_baseline_end'    => $d($c->con_baseline_end),
            'con_actual_start'    => $d($c->con_actual_start),
            'con_actual_end'      => $d($c->con_actual_end),
            'contractor'          => $c->contractor,
            'baseline_amount'     => $c->baseline_amount !== null ? (float) $c->baseline_amount : null,
            'actual_amount'       => $c->actual_amount !== null ? (float) $c->actual_amount : null,
            'payment_status'      => $c->payment_status,
            'completion_status'   => $c->completion_status,
            'request_date'        => $d($c->request_date),
            'date_prepared'       => $d($c->date_prepared),
            'issued_on'           => $d($c->issued_on),
            'received_by'         => $c->received_by,
            'accepted_by'         => $c->accepted_by,
            'acknowledged_by'     => $c->acknowledged_by,
            'photos'              => collect($c->photos ?? [])
                ->map(fn ($p) => ['path' => $p, 'url' => Storage::disk('public')->url($p)])
                ->values()
                ->all(),
        ];
    }

    /**
     * Names printed on the completion certificates. The three PMD-side roles
     * come from Signatory Settings; "Prepared by" is always the project creator.
     */
    private function signatoryData(Project $project): array
    {
        return [
            'prepared_by'           => $project->creator?->name ?? '',
            'pmd_assistant_manager' => (string) Setting::get('signatory_pmd_assistant_manager', ''),
            'pmd_manager'           => (string) Setting::get('signatory_pmd_manager', ''),
            'ecs_division_manager'  => (string) Setting::get('signatory_ecs_division_manager', ''),
            'operations_director'   => (string) Setting::get('signatory_operations_director', ''),
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
            'project_cost' => (float) $project->budget_total,
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
            'site' => $request->job_location ?? '',
            'asset_id' => '',
            'cls' => '',
            'priority' => '',
            'status' => 'PLANNING',
            'work_force' => '',
            'wr_no' => $request->request_no ?? '',
            'wr_date' => now()->format('Y-m-d'),
            'dept_owner' => $request->requester?->department ?? '',
            'cost_code' => $request->costcode ?? '',
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
