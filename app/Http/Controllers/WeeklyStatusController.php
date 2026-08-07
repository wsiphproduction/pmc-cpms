<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Project;
use App\Models\ProjectWeeklyReport;
use App\Support\PsrTemplateWriter;
use App\Support\WeeklyReportSheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Weekly Status — a project engineer's own view of the weekly reporting they owe
 * across every project they handle, rather than one operations hub at a time.
 *
 * It writes the same ProjectWeeklyReport rows the PSR hub does, so a report filed
 * here shows up in the project's hub (and rolls into its completion) unchanged.
 * The one difference is the bulk template, which carries a `project_no` column so
 * a single upload can cover a whole week of mixed projects, sub-projects and NTPs.
 */
class WeeklyStatusController extends Controller
{
    public function index(Request $request)
    {
        $projects = $this->scopedProjects($request)->with('ntps:id,project_id,ntp_no,contractor_name')->get();

        $reports = ProjectWeeklyReport::whereIn('project_id', $projects->pluck('id'))
            ->with(['ntp:id,ntp_no,contractor_name', 'project:id,project_no,title', 'creator:id,name'])
            ->latest('submitted_date')
            ->latest('id')
            ->limit(200)
            ->get();

        return Inertia::render('weekly-status/index', [
            'projects' => $projects->map(fn (Project $project) => [
                'id'                 => $project->id,
                'project_no'         => $project->project_no,
                'title'              => $project->title,
                'is_sub'             => $project->parent_id !== null,
                'status'             => Project::STATUS_LABELS[$project->status_key] ?? $project->status_key,
                'completion_percent' => (int) $project->completion_percent,
                'ntps'               => $project->ntps->map(fn ($ntp) => [
                    'id'         => $ntp->id,
                    'ntp_no'     => $ntp->ntp_no,
                    'contractor' => $ntp->contractor_name,
                ])->values(),
            ])->values(),
            'reports' => $reports->map(fn (ProjectWeeklyReport $report) => [
                'id'                => $report->id,
                'project_id'        => $report->project_id,
                'project_no'        => $report->project?->project_no,
                'project_title'     => $report->project?->title,
                'week_code'         => $report->week_code,
                'completion_pct'    => $report->completion_pct,
                'identified_issues' => $report->identified_issues,
                'progress_updates'  => $report->progress_updates,
                'checklist'         => $report->checklist ?? [],
                'issues'            => $report->issues ?? [],
                'submitted_date'    => optional($report->submitted_date)->format('M d, Y') ?? '-',
                'submitted_by'      => $report->creator?->name,
                'filename'          => $report->filename,
                'url'               => $report->file_path ? Storage::disk('public')->url($report->file_path) : null,
                'ntp_id'            => $report->project_ntp_id,
                'ntp_no'            => $report->ntp?->ntp_no,
                'ntp_contractor'    => $report->ntp?->contractor_name,
                // Governs both the Edit and Delete actions on the row.
                'can_manage'        => $request->user()->can('update', $report->project),
            ])->values(),
            // Same form definition the operations hub uses, so a report reads
            // back identically wherever it was filed from.
            'checklist'          => config('psr.checklist'),
            'issue_rows'         => config('psr.issue_rows'),
            'statuses'           => config('psr.statuses'),
            'suggested_week_code' => $this->suggestedWeekCode(),
        ]);
    }

    /** The cross-project workbook, pre-loaded with the engineer's own projects. */
    public function template(Request $request): Response
    {
        $projects = $this->scopedProjects($request)->with('ntps:id,project_id,ntp_no,contractor_name')->get()
            ->map(fn (Project $project) => [
                'project_no' => $project->project_no,
                'title'      => $project->title,
                'ntps'       => $project->ntps->pluck('ntp_no')->implode(', '),
            ])->values()->all();

        return response((new PsrTemplateWriter)->build($projects), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="weekly-status-template.xlsx"',
        ]);
    }

    /**
     * Bulk-import a week's reports across several projects at once. Each row
     * names its project in `project_no`; rows pointing at a project outside the
     * engineer's scope (or at no known project) are reported back rather than
     * silently dropped.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
        ]);

        try {
            $sheet = new WeeklyReportSheet($request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not read the uploaded file.');
        }

        if (! $sheet->hasWeekCode()) {
            return back()->with('error', 'The file must contain a "week_code" column.');
        }
        if (! $sheet->hasProjectNo()) {
            return back()->with('error', 'The file must contain a "project_no" column — download the Weekly Status template.');
        }

        // Project number → project, limited to what this user may report on.
        $projects = $this->scopedProjects($request)->with('ntps:id,project_id,ntp_no')->get()
            ->keyBy(fn (Project $project) => strtolower(trim($project->project_no)));

        $imported = 0;
        $touched  = collect();
        $skipped  = [];

        foreach ($sheet->reports() as $report) {
            $key     = strtolower(trim((string) $report['project_no']));
            $project = $key !== '' ? $projects->get($key) : null;

            if (! $project) {
                $skipped[] = $report['project_no'] ?? '(blank)';
                continue;
            }

            $ntpId = null;
            if ($report['ntp_no'] !== null) {
                $ntpId = $project->ntps
                    ->first(fn ($ntp) => strtolower(trim((string) $ntp->ntp_no)) === strtolower($report['ntp_no']))?->id;
            }

            $project->weeklyReports()->create([
                'project_ntp_id'    => $ntpId,
                'week_code'         => $report['week_code'],
                'completion_pct'    => $report['completion_pct'],
                'identified_issues' => $report['identified_issues'],
                'progress_updates'  => $report['progress_updates'],
                'checklist'         => $report['checklist'] ?: null,
                'issues'            => $report['issues'] ?: null,
                'submitted_date'    => $report['submitted_date'],
                'created_by'        => auth()->id(),
            ]);

            $imported++;
            $touched->put($project->id, $project);
        }

        foreach ($touched as $project) {
            $project->refreshCompletionFromReports();
            AuditTrail::log('Weekly status filed via bulk upload', $project, ['module' => 'PSR', 'type' => 'upload']);
        }

        if ($imported === 0) {
            return back()->with('error', $skipped
                ? 'No rows matched a project you handle. Unmatched project numbers: ' . $this->listUnmatched($skipped)
                : 'No valid weekly reports found in the uploaded file.');
        }

        $message = "Filed {$imported} weekly report(s) across {$touched->count()} project(s).";
        if ($skipped) {
            $message .= ' Skipped ' . count($skipped) . ' row(s) with an unknown project number: ' . $this->listUnmatched($skipped);
        }

        return back()->with('success', $message);
    }

    /** File a single report — the same form the operations hub uses. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            ...$this->reportRules(),
        ]);

        $project = $this->scopedProjects($request)->findOrFail($data['project_id']);

        $filePath = null;
        $filename = null;
        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $filePath = $file->store("hub/psr/{$project->id}", 'public');
            $filename = $file->getClientOriginalName();
        }

        $checklist = $this->normalizeChecklist($data['checklist'] ?? []);
        $issues    = $this->normalizeIssues($data['issues'] ?? []);

        $report = $project->weeklyReports()->create([
            'project_ntp_id'    => $this->resolveNtpId($project, $data['project_ntp_id'] ?? null),
            'week_code'         => $data['week_code'],
            'completion_pct'    => $data['completion_pct'],
            // The first issue row doubles as the report's headline issue,
            // matching the hub form and the importer.
            'identified_issues' => $issues[0]['issue'] ?? null,
            'progress_updates'  => $data['progress_updates'] ?? null,
            'checklist'         => $checklist ?: null,
            'issues'            => $issues ?: null,
            'file_path'         => $filePath,
            'filename'          => $filename,
            'submitted_date'    => now()->toDateString(),
            'created_by'        => auth()->id(),
        ]);

        $project->refreshCompletionFromReports();

        AuditTrail::log("Weekly report {$report->week_code} submitted — {$data['completion_pct']}% complete", $project, ['module' => 'PSR', 'type' => 'upload']);

        return back()->with('success', "Weekly report {$report->week_code} filed for {$project->project_no}.");
    }

    /**
     * Revise a filed report. The project it belongs to is fixed — moving a report
     * between projects would strand both projects' completion figures — but every
     * other field is editable, including attaching the PDF a bulk upload couldn't
     * carry.
     */
    public function update(Request $request, ProjectWeeklyReport $report): RedirectResponse
    {
        $data = $request->validate([
            ...$this->reportRules(),
            'remove_file' => ['nullable', 'boolean'],
        ]);

        $project = $this->scopedProjects($request)->findOrFail($report->project_id);
        $this->authorize('update', $project);

        $filePath = $report->file_path;
        $filename = $report->filename;

        // A replacement upload wins over the remove flag; either way the old file
        // goes, so revisions don't leave orphans on disk.
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            $filePath = $file->store("hub/psr/{$project->id}", 'public');
            $filename = $file->getClientOriginalName();
        } elseif ($request->boolean('remove_file') && $filePath) {
            Storage::disk('public')->delete($filePath);
            $filePath = null;
            $filename = null;
        }

        $checklist = $this->normalizeChecklist($data['checklist'] ?? []);
        $issues    = $this->normalizeIssues($data['issues'] ?? []);

        $report->update([
            'project_ntp_id'    => $this->resolveNtpId($project, $data['project_ntp_id'] ?? null),
            'week_code'         => $data['week_code'],
            'completion_pct'    => $data['completion_pct'],
            'identified_issues' => $issues[0]['issue'] ?? null,
            'progress_updates'  => $data['progress_updates'] ?? null,
            'checklist'         => $checklist ?: null,
            'issues'            => $issues ?: null,
            'file_path'         => $filePath,
            'filename'          => $filename,
        ]);

        // The edited report may be the project's latest, so its figure can move.
        $project->refreshCompletionFromReports();

        AuditTrail::log("Weekly report {$report->week_code} updated — {$data['completion_pct']}% complete", $project, ['module' => 'PSR', 'type' => 'update']);

        return back()->with('success', "Weekly report {$report->week_code} updated.");
    }

    /** Validation shared by filing a new report and revising an existing one. */
    private function reportRules(): array
    {
        return [
            'project_ntp_id'           => ['nullable', 'exists:project_ntps,id'],
            'week_code'                => ['required', 'string', 'max:20'],
            'completion_pct'           => ['required', 'integer', 'min:0', 'max:100'],
            'progress_updates'         => ['nullable', 'string'],
            'checklist'                => ['nullable', 'array'],
            'checklist.*.seq'          => ['required', 'string', 'max:20'],
            'checklist.*.status'       => ['nullable', Rule::in((array) config('psr.statuses'))],
            'checklist.*.remarks'      => ['nullable', 'string'],
            'issues'                   => ['nullable', 'array'],
            'issues.*.issue'           => ['nullable', 'string'],
            'issues.*.action'          => ['nullable', 'string'],
            'issues.*.commitment_date' => ['nullable', 'date'],
            'file'                     => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    /**
     * Keep only the checklist items that were answered, so an untouched item
     * doesn't read back as a deliberate blank.
     */
    private function normalizeChecklist(array $rows): array
    {
        return collect($rows)
            ->map(fn ($c) => [
                'seq'     => $c['seq'],
                'status'  => $c['status'] ?? null,
                'remarks' => $c['remarks'] ?? null,
            ])
            ->filter(fn ($c) => $c['status'] !== null || $c['remarks'] !== null)
            ->values()->all();
    }

    /** Drop issue rows that were left entirely blank. */
    private function normalizeIssues(array $rows): array
    {
        return collect($rows)
            ->map(fn ($i) => [
                'issue'           => $i['issue'] ?? null,
                'action'          => $i['action'] ?? null,
                'commitment_date' => isset($i['commitment_date'])
                    ? \Carbon\Carbon::parse($i['commitment_date'])->toDateString()
                    : null,
            ])
            ->filter(fn ($i) => $i['issue'] !== null || $i['action'] !== null || $i['commitment_date'] !== null)
            ->values()->all();
    }

    /** An NTP only counts when it belongs to the project being reported on. */
    private function resolveNtpId(Project $project, $ntpId): ?int
    {
        $ntpId = ($ntpId ?? '') ?: null;

        if ($ntpId === null || ! $project->ntps()->whereKey($ntpId)->exists()) {
            return null;
        }

        return (int) $ntpId;
    }

    public function destroy(Request $request, ProjectWeeklyReport $report): RedirectResponse
    {
        $project = $this->scopedProjects($request)->findOrFail($report->project_id);
        $this->authorize('update', $project);

        AuditTrail::log("Weekly report {$report->week_code} deleted", $project, ['module' => 'PSR', 'type' => 'delete']);

        if ($report->file_path) {
            Storage::disk('public')->delete($report->file_path);
        }
        $report->delete();

        $project->refreshCompletionFromReports();

        return back()->with('success', 'Weekly report deleted.');
    }

    /**
     * Projects this user may file a weekly status for: the ones they created or
     * are the assigned manager of, sub-projects included. Admins and assistant
     * managers cover for engineers, so they see everything — the same split the
     * project-update policy draws.
     */
    private function scopedProjects(Request $request)
    {
        $query = Project::query()->orderBy('project_no');
        $user  = $request->user();

        if (! $user->hasRole(['admin', 'assistant_manager'])) {
            $query->where(fn ($q) => $q
                ->where('created_by', $user->id)
                ->orWhere('project_manager_id', $user->id));
        }

        return $query;
    }

    /** At most a handful of unmatched numbers, so the message stays readable. */
    private function listUnmatched(array $skipped): string
    {
        $unique = collect($skipped)->unique()->values();

        return $unique->take(5)->implode(', ') . ($unique->count() > 5 ? ', …' : '');
    }

    /**
     * A sensible default week code (e.g. W2-AUG) from today's date, so the
     * engineer usually just confirms it.
     */
    private function suggestedWeekCode(): string
    {
        $today = now();

        return 'W' . (int) ceil($today->day / 7) . '-' . strtoupper($today->format('M'));
    }
}
