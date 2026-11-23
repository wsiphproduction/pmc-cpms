<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Project;
use App\Support\ContractorPerformance;
use App\Support\ContractorPerformanceReportWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Contractor-level consolidated evaluation: the ranking dashboard, each
 * contractor's profile, and the Excel reports — framework §6 and §8.
 */
class ContractorPerformanceController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $performance = new ContractorPerformance($this->filters($request));

        return Inertia::render('contractor-performance/index', [
            'stats'       => $performance->stats(),
            'contractors' => $performance->ranking(),
            'filters'     => $performance->filters(),
            'options'     => $this->options(),
        ]);
    }

    /** One contractor's profile; `c` is its key — `s<id>` on Master Data, else `n:<name>`. */
    public function show(Request $request): InertiaResponse
    {
        $key = (string) $request->query('c', '');
        $performance = new ContractorPerformance($this->filters($request));
        $profile = $performance->profile($key);

        abort_if($profile === null, 404, 'No evaluations or projects for that contractor in the selected filters.');

        return Inertia::render('contractor-performance/show', [
            'profile'     => $profile,
            'filters'     => $performance->filters(),
            'options'     => $this->options(),
            'definition'  => collect(config('contractor_evaluation.categories'))
                ->map(fn ($c, $k) => ['key' => $k, 'letter' => $c['letter'], 'title' => $c['title'], 'weight' => $c['weight']])
                ->values(),
        ]);
    }

    /** Non-compliance and corrective action monitoring (§9, §10) across contractors. */
    public function findings(Request $request): InertiaResponse
    {
        $performance = new ContractorPerformance($this->filters($request));
        $status = $this->findingStatus($request);
        $register = $performance->findingsRegister($status);

        return Inertia::render('contractor-performance/findings', [
            'stats'      => $register['stats'],
            'findings'   => $register['findings'],
            'ncr_status' => $status,
            'filters'    => $performance->filters(),
            'options'    => $this->options(),
        ]);
    }

    private function findingStatus(Request $request): ?string
    {
        $status = $request->query('ncr_status');

        return in_array($status, ['open', 'responded', 'overdue', 'closed', 'unclosed'], true) ? $status : null;
    }

    /** Consolidated / ranking (default), one contractor, or project-level evaluations. */
    public function export(Request $request): Response
    {
        $performance = new ContractorPerformance($this->filters($request));
        $writer = new ContractorPerformanceReportWriter($request->user()->name);
        $filters = $performance->filters();
        $report = $request->query('report', 'consolidated');
        $stamp = now()->format('Ymd');

        [$bytes, $filename, $label] = match ($report) {
            'contractor' => (function () use ($performance, $writer, $filters, $request, $stamp) {
                $profile = $performance->profile((string) $request->query('c', ''));
                abort_if($profile === null, 404);
                $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $profile['name']);

                return [$writer->individual($profile, $filters), "Contractor-Performance-{$slug}-{$stamp}.xlsx", "individual contractor report ({$profile['name']})"];
            })(),
            'evaluations' => [$writer->projectLevel($performance->evaluations(), $filters), "Project-Level-Evaluations-{$stamp}.xlsx", 'project-level evaluation report'],
            'noncompliance' => [$writer->nonCompliance($performance->findingsRegister($this->findingStatus($request))['findings'], $filters), "Non-Compliance-Report-{$stamp}.xlsx", 'non-compliance and deficiency report'],
            'corrective'  => (function () use ($performance, $writer, $filters, $request, $stamp) {
                $register = $performance->findingsRegister($this->findingStatus($request));

                return [$writer->correctiveActions($register['findings'], $register['stats'], $filters), "Corrective-Action-Monitoring-{$stamp}.xlsx", 'corrective action monitoring report'];
            })(),
            default       => [$writer->consolidated($performance->ranking(), $filters), "Contractor-Performance-Ranking-{$stamp}.xlsx", 'consolidated contractor performance report'],
        };

        AuditTrail::log("Generated {$label}", $request->user(), ['module' => 'Contractor Performance', 'type' => 'download']);

        return response($bytes, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'period'       => ['nullable', 'in:' . implode(',', ContractorPerformance::PERIODS)],
            'from'         => ['nullable', 'date'],
            'to'           => ['nullable', 'date', 'after_or_equal:from'],
            'period_type'  => ['nullable', 'string', 'max:20'],
            'contractor'   => ['nullable', 'string', 'max:255'],
            'project'      => ['nullable', 'string', 'max:255'],
            'department'   => ['nullable', 'string', 'max:255'],
            'category'     => ['nullable', 'string', 'max:255'],
            'status'       => ['nullable', 'string', 'max:30'],
            'project_type' => ['nullable', 'in:major,minor'],
            'rating'       => ['nullable', 'string', 'max:30'],
            'evaluation'   => ['nullable', 'in:evaluated,pending'],
            'basis'        => ['nullable', 'in:simple,value'],
        ]);
    }

    /** What the filter dropdowns offer, from the data actually on file. */
    private function options(): array
    {
        $distinct = fn (string $column) => Project::query()->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column)->values();

        return [
            'departments'  => $distinct('dept_owner'),
            'categories'   => $distinct('category'),
            'statuses'     => collect(Project::STATUS_LABELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'period_types' => collect(config('contractor_evaluation.period_types'))->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'ratings'      => collect(config('contractor_evaluation.classifications'))->pluck('label')->values(),
        ];
    }
}
