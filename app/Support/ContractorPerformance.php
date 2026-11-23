<?php

namespace App\Support;

use App\Models\ContractorEvaluation;
use App\Models\ContractorFinding;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\Supplier;
use App\Support\ContractorEvaluationScoring as Scoring;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Contractor-level consolidated evaluation (framework §6 and §8).
 *
 * A contractor is "assigned" a project by a live NTP on it (or by having been
 * evaluated on it). Each assigned project counts once, by its latest approved
 * evaluation inside the selected window; a project with none is "Not Yet
 * Evaluated" and is left out of the average rather than scored as zero.
 *
 *   Overall score = sum of project scores ÷ number of evaluated projects
 *
 * or, with the value basis, each project weighted by the contract value of
 * the contractor's NTPs on it — the framework's allowance for projects of
 * very different size.
 *
 * A contractor is identified by its Master Data supplier when the name matches
 * one, otherwise by its name as typed, so unlisted contractors still show.
 */
class ContractorPerformance
{
    /** A category scoring under this share of its weight counts as a deficiency. */
    public const DEFICIENCY_SHARE = 0.6;

    /** Overall scores under this are flagged as a low rating (below Satisfactory). */
    public const LOW_RATING = 70;

    public const PERIODS = ['all', 'month', 'quarter', 'year', 'custom'];

    private array $filters;

    private ?Carbon $from = null;

    private ?Carbon $to = null;

    /** @var array<string, array>|null contractor key => consolidated row */
    private ?array $contractors = null;

    public function __construct(array $filters = [])
    {
        $this->filters = [
            'period'        => in_array($filters['period'] ?? 'all', self::PERIODS, true) ? ($filters['period'] ?? 'all') : 'all',
            'from'          => $filters['from'] ?? null,
            'to'            => $filters['to'] ?? null,
            'period_type'   => $filters['period_type'] ?? null,
            'contractor'    => trim((string) ($filters['contractor'] ?? '')),
            'project'       => trim((string) ($filters['project'] ?? '')),
            'department'    => $filters['department'] ?? null,
            'category'      => $filters['category'] ?? null,
            'status'        => $filters['status'] ?? null,
            'project_type'  => $filters['project_type'] ?? null,
            'rating'        => $filters['rating'] ?? null,
            'evaluation'    => in_array($filters['evaluation'] ?? null, ['evaluated', 'pending'], true) ? $filters['evaluation'] : null,
            'basis'         => ($filters['basis'] ?? 'simple') === 'value' ? 'value' : 'simple',
        ];

        [$this->from, $this->to] = $this->window();
    }

    public function filters(): array
    {
        return [
            ...$this->filters,
            'window_from' => $this->from?->toDateString(),
            'window_to'   => $this->to?->toDateString(),
        ];
    }

    // ── Public read-outs ──────────────────────────────────────────────────────

    /** Contractors ranked best first; those with no evaluated project last. */
    public function ranking(): array
    {
        $rows = array_values($this->contractors());

        usort($rows, function ($a, $b) {
            if ($a['score'] === null || $b['score'] === null) {
                return ($a['score'] === null) <=> ($b['score'] === null) ?: strcasecmp($a['name'], $b['name']);
            }

            return $b['score'] <=> $a['score'] ?: strcasecmp($a['name'], $b['name']);
        });

        $rank = 0;
        foreach ($rows as &$row) {
            $row['rank'] = $row['score'] === null ? null : ++$rank;
            unset($row['assignments'], $row['history'], $row['findings']);
        }

        return $rows;
    }

    /** Every approved evaluation in the filtered set, newest first, named by contractor. */
    public function evaluations(): array
    {
        return collect($this->contractors())
            ->flatMap(fn ($c) => array_map(fn ($h) => ['contractor' => $c['name'], ...$h], $c['history']))
            ->sortByDesc('as_of')
            ->values()
            ->all();
    }

    /** The dashboard indicators of framework §8. */
    public function stats(): array
    {
        // A project with two contractors is evaluated only once both are, so
        // the tiles agree with the per-contractor "pending" in the ranking.
        $byProject = collect($this->contractors())->flatMap(fn ($c) => $c['assignments'])->groupBy('project_id');
        $evaluated = $byProject->filter(fn ($rows) => $rows->every(fn ($a) => $a['evaluation'] !== null))->count();

        return [
            'contractors'        => count($this->contractors()),
            'projects'           => $byProject->count(),
            'projects_evaluated' => $evaluated,
            'pending_evaluation' => $byProject->count() - $evaluated,
            'in_signoff'         => ContractorEvaluation::where('status', ContractorEvaluation::STATUS_FOR_APPROVAL)->count(),
        ];
    }

    /** One contractor's consolidated profile, or null when it is not in the filtered set. */
    public function profile(string $key): ?array
    {
        $row = $this->contractors()[$key] ?? null;

        if ($row === null) {
            return null;
        }

        $ranking = collect($this->ranking());
        $row['rank'] = $ranking->firstWhere('key', $key)['rank'] ?? null;
        $row['ranked_of'] = $ranking->whereNotNull('rank')->count();

        return $row;
    }

    /**
     * The non-compliance register across the filtered contractors (§9, §10),
     * optionally narrowed to one status — `overdue` meaning open or responded
     * past the target date — with its monitoring figures.
     */
    public function findingsRegister(?string $status = null): array
    {
        $all = collect($this->contractors())->flatMap(fn ($c) => $c['findings'])->sortByDesc('date_identified')->values();

        $closed = $all->where('status', ContractorFinding::STATUS_CLOSED);

        $stats = [
            'total'     => $all->count(),
            'open'      => $all->where('status', ContractorFinding::STATUS_OPEN)->count(),
            'responded' => $all->where('status', ContractorFinding::STATUS_RESPONDED)->count(),
            'overdue'   => $all->where('overdue', true)->count(),
            'closed'    => $closed->count(),
            'avg_days_to_close' => $closed->isEmpty() ? null : round($closed->avg('days_open'), 1),
        ];

        $rows = match ($status) {
            'open', 'responded', 'closed' => $all->where('status', $status),
            'overdue'                     => $all->where('overdue', true),
            'unclosed'                    => $all->where('status', '!=', ContractorFinding::STATUS_CLOSED),
            default                       => $all,
        };

        return ['stats' => $stats, 'findings' => $rows->values()->all()];
    }

    // ── Consolidation ─────────────────────────────────────────────────────────

    private function contractors(): array
    {
        if ($this->contractors !== null) {
            return $this->contractors;
        }

        $suppliers = Supplier::get(['id', 'company', 'email', 'accredited', 'is_active'])
            ->keyBy(fn (Supplier $s) => self::normalize($s->company));

        $contractors = [];

        $touch = function (?int $supplierId, string $name) use (&$contractors, $suppliers): string {
            $supplier = $supplierId
                ? $suppliers->first(fn ($s) => $s->id === $supplierId)
                : $suppliers->get(self::normalize($name));

            $key = $supplier ? 's' . $supplier->id : 'n:' . self::normalize($name);

            $contractors[$key] ??= [
                'key'         => $key,
                'name'        => $supplier?->company ?? trim($name),
                'supplier'    => $supplier ? [
                    'id'         => $supplier->id,
                    'company'    => $supplier->company,
                    'email'      => $supplier->email,
                    'accredited' => (bool) $supplier->accredited,
                    'active'     => (bool) $supplier->is_active,
                ] : null,
                'assignments' => [],
                'history'     => [],
                'findings'    => [],
            ];

            return $key;
        };

        // Assignments from live NTPs, with the contract value they carry.
        $ntps = ProjectNtp::query()
            ->where('status', '!=', 'rejected')
            ->whereNotNull('contractor_name')
            ->where('contractor_name', '!=', '')
            ->whereHas('project', fn (Builder $q) => $this->scopeProjects($q))
            ->with('project:id,project_no,title,dept_owner,category,status_key,project_type')
            ->get(['id', 'project_id', 'contractor_name', 'approved_cost']);

        foreach ($ntps as $ntp) {
            $key = $touch(null, $ntp->contractor_name);
            $this->assign($contractors[$key], $ntp->project);
            $contractors[$key]['assignments'][$ntp->project_id]['contract_value'] += (float) $ntp->approved_cost;
        }

        // Approved evaluations inside the window — they also assign the project.
        $evaluations = ContractorEvaluation::query()
            ->where('status', ContractorEvaluation::STATUS_APPROVED)
            ->when($this->filters['period_type'], fn ($q, $type) => $q->where('period_type', $type))
            ->when($this->from, fn ($q) => $q->whereRaw('COALESCE(period_end, DATE(approved_at)) >= ?', [$this->from->toDateString()]))
            ->when($this->to, fn ($q) => $q->whereRaw('COALESCE(period_end, DATE(approved_at)) <= ?', [$this->to->toDateString()]))
            ->whereHas('project', fn (Builder $q) => $this->scopeProjects($q))
            ->with(['project:id,project_no,title,dept_owner,category,status_key,project_type', 'categories', 'creator:id,name'])
            ->get();

        foreach ($evaluations as $evaluation) {
            $key = $touch($evaluation->supplier_id, $evaluation->contractor_name);
            $this->assign($contractors[$key], $evaluation->project);
            $contractors[$key]['history'][] = $this->historyRow($evaluation);
        }

        // Non-compliance records identified inside the window (§9).
        $findings = ContractorFinding::query()
            ->when($this->from, fn ($q) => $q->whereDate('date_identified', '>=', $this->from->toDateString()))
            ->when($this->to, fn ($q) => $q->whereDate('date_identified', '<=', $this->to->toDateString()))
            ->whereHas('project', fn (Builder $q) => $this->scopeProjects($q))
            ->with([...ContractorFindings::WITH, 'project:id,project_no,title,dept_owner,category,status_key,project_type'])
            ->latest('date_identified')
            ->get();

        foreach ($findings as $finding) {
            $key = $touch($finding->supplier_id, $finding->contractor_name);
            $this->assign($contractors[$key], $finding->project);
            $contractors[$key]['findings'][] = ContractorFindings::row($finding);
        }

        $rows = [];
        foreach ($contractors as $key => $contractor) {
            $row = $this->consolidate($contractor);

            if ($this->keep($row)) {
                $rows[$key] = $row;
            }
        }

        return $this->contractors = $rows;
    }

    private function assign(array &$contractor, Project $project): void
    {
        $contractor['assignments'][$project->id] ??= [
            'project_id'     => $project->id,
            'project_no'     => $project->project_no,
            'title'          => $project->title,
            'department'     => $project->dept_owner,
            'category'       => $project->category,
            'status'         => Project::STATUS_LABELS[$project->status_key] ?? $project->status_key,
            'project_type'   => $project->project_type,
            'contract_value' => 0.0,
            'evaluation'     => null,
        ];
    }

    private function historyRow(ContractorEvaluation $evaluation): array
    {
        $definitions = Scoring::categories();

        return [
            'id'             => $evaluation->id,
            'control_no'     => $evaluation->control_no,
            'project_id'     => $evaluation->project_id,
            'project_no'     => $evaluation->project?->project_no,
            'project_title'  => $evaluation->project?->title,
            'period_type'    => $evaluation->period_type,
            'period_label'   => $evaluation->period_label,
            'period_end'     => $evaluation->period_end?->toDateString(),
            'approved_at'    => $evaluation->approved_at?->toDateString(),
            // What orders evaluations in time: the period's end, else its approval.
            'as_of'          => ($evaluation->period_end ?? $evaluation->approved_at)?->toDateString(),
            'evaluated_by'   => $evaluation->creator?->name,
            'score'          => $evaluation->total_score,
            'classification' => Scoring::classify($evaluation->total_score)['label'] ?? null,
            'categories'     => $evaluation->categories
                ->sortBy(fn ($c) => array_search($c->category, array_keys($definitions), true))
                ->map(fn ($c) => [
                    'key'                => $c->category,
                    'letter'             => $definitions[$c->category]['letter'] ?? '',
                    'title'              => $definitions[$c->category]['title'] ?? $c->category,
                    'weight'             => $definitions[$c->category]['weight'] ?? 0,
                    'score'              => $c->score,
                    'corrective_actions' => $c->corrective_actions,
                ])->values()->all(),
        ];
    }

    private function consolidate(array $contractor): array
    {
        $definitions = Scoring::categories();

        // Newest first: each project counts by its latest approved evaluation.
        usort($contractor['history'], fn ($a, $b) => strcmp((string) $b['as_of'], (string) $a['as_of']) ?: $b['id'] <=> $a['id']);

        foreach ($contractor['history'] as $h) {
            $assignment = &$contractor['assignments'][$h['project_id']];
            $assignment['evaluation'] ??= [
                'id'             => $h['id'],
                'control_no'     => $h['control_no'],
                'period_label'   => $h['period_label'],
                'as_of'          => $h['as_of'],
                'score'          => $h['score'],
                'classification' => $h['classification'],
                'categories'     => $h['categories'],
            ];
            unset($assignment);
        }

        // "Evaluated" / "Not Yet Evaluated" narrows the projects counted.
        if ($this->filters['evaluation'] !== null) {
            $wantEvaluated = $this->filters['evaluation'] === 'evaluated';
            $contractor['assignments'] = array_filter(
                $contractor['assignments'],
                fn ($a) => ($a['evaluation'] !== null) === $wantEvaluated
            );
        }

        $evaluated = array_values(array_filter($contractor['assignments'], fn ($a) => $a['evaluation'] !== null && $a['evaluation']['score'] !== null));

        $simple = $evaluated === [] ? null
            : round(array_sum(array_map(fn ($a) => $a['evaluation']['score'], $evaluated)) / count($evaluated), 2);

        $valued = array_filter($evaluated, fn ($a) => $a['contract_value'] > 0);
        $totalValue = array_sum(array_map(fn ($a) => $a['contract_value'], $valued));
        // A value-weighted average needs every evaluated project to carry a value.
        $weighted = ($valued !== [] && count($valued) === count($evaluated) && $totalValue > 0)
            ? round(array_sum(array_map(fn ($a) => $a['evaluation']['score'] * $a['contract_value'], $valued)) / $totalValue, 2)
            : $simple;

        $score = $this->filters['basis'] === 'value' ? $weighted : $simple;

        // Each category as a share of its weight, averaged over the evaluated projects.
        $categoryAverages = [];
        foreach ($definitions as $key => $definition) {
            $shares = [];
            foreach ($evaluated as $a) {
                $c = collect($a['evaluation']['categories'])->firstWhere('key', $key);
                if ($c && $c['score'] !== null && $definition['weight'] > 0) {
                    $shares[] = $c['score'] / $definition['weight'];
                }
            }
            $categoryAverages[$key] = $shares === [] ? null : round(array_sum($shares) / count($shares) * 100, 1);
        }

        $classification = Scoring::classify($score);
        $findings = $contractor['findings'];
        $overdue = count(array_filter($findings, fn ($f) => $f['overdue']));

        return [
            'key'               => $contractor['key'],
            'name'              => $contractor['name'],
            'supplier'          => $contractor['supplier'],
            'projects'          => count($contractor['assignments']),
            'evaluated'         => count($evaluated),
            'pending'           => count($contractor['assignments']) - count($evaluated),
            'evaluations'       => count($contractor['history']),
            'contract_value'    => round(array_sum(array_map(fn ($a) => $a['contract_value'], $contractor['assignments'])), 2),
            'score'             => $score,
            'simple_score'      => $simple,
            'weighted_score'    => $weighted,
            'classification'    => $classification['label'] ?? null,
            'action'            => $classification['action'] ?? null,
            'category_averages' => $categoryAverages,
            'flags'             => $this->flags($contractor['history'], $score, $overdue),
            'last_evaluated'    => $contractor['history'][0]['as_of'] ?? null,
            'findings_open'     => count(array_filter($findings, fn ($f) => $f['status'] !== ContractorFinding::STATUS_CLOSED)),
            'findings_overdue'  => $overdue,
            'findings_total'    => count($findings),
            'assignments'       => array_values($contractor['assignments']),
            'history'           => $contractor['history'],
            'findings'          => $findings,
        ];
    }

    /**
     * Corrective-action monitoring (§9): a low overall rating, and any category
     * that fell short in two or more evaluations — a recurring deficiency.
     */
    private function flags(array $history, ?float $score, int $overdueFindings = 0): array
    {
        $flags = [];

        if ($score !== null && $score < self::LOW_RATING) {
            $flags[] = ['type' => 'low_rating', 'message' => 'Overall score below ' . self::LOW_RATING . ' — corrective action plan required.'];
        }

        if ($overdueFindings > 0) {
            $flags[] = ['type' => 'overdue', 'message' => "{$overdueFindings} corrective action(s) past their target date."];
        }

        $shortfalls = [];
        foreach ($history as $h) {
            foreach ($h['categories'] as $c) {
                if ($c['score'] !== null && $c['weight'] > 0 && $c['score'] / $c['weight'] < self::DEFICIENCY_SHARE) {
                    $shortfalls[$c['letter'] . '. ' . $c['title']] = ($shortfalls[$c['letter'] . '. ' . $c['title']] ?? 0) + 1;
                }
            }
        }

        foreach ($shortfalls as $category => $count) {
            if ($count >= 2) {
                $flags[] = ['type' => 'recurring', 'message' => "Recurring deficiency in {$category} ({$count} evaluations under " . (int) (self::DEFICIENCY_SHARE * 100) . '%).'];
            }
        }

        return $flags;
    }

    /** Contractor-level filters: name search and performance rating. */
    private function keep(array $row): bool
    {
        if ($row['projects'] === 0 && $row['evaluations'] === 0 && $row['findings_total'] === 0) {
            return false;
        }

        if ($this->filters['evaluation'] !== null && $row['projects'] === 0) {
            return false;
        }

        if ($this->filters['contractor'] !== '' && ! str_contains(mb_strtolower($row['name']), mb_strtolower($this->filters['contractor']))) {
            return false;
        }

        if ($rating = $this->filters['rating']) {
            return $rating === 'unrated' ? $row['score'] === null : $row['classification'] === $rating;
        }

        return true;
    }

    /** Project-level filters, applied to both the NTPs and the evaluations. */
    private function scopeProjects(Builder $query): Builder
    {
        $f = $this->filters;

        return $query
            ->when($f['project'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', '%' . $f['project'] . '%')
                ->orWhere('project_no', 'like', '%' . $f['project'] . '%')))
            ->when($f['department'], fn ($q, $v) => $q->where('dept_owner', $v))
            ->when($f['category'], fn ($q, $v) => $q->where('category', $v))
            ->when($f['status'], fn ($q, $v) => $q->where('status_key', $v))
            ->when($f['project_type'], fn ($q, $v) => $q->where('project_type', $v));
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function window(): array
    {
        $now = now();

        return match ($this->filters['period']) {
            'month'   => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'year'    => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom'  => [
                $this->filters['from'] ? Carbon::parse($this->filters['from'])->startOfDay() : null,
                $this->filters['to'] ? Carbon::parse($this->filters['to'])->endOfDay() : null,
            ],
            default   => [null, null],
        };
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }
}
