<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectRequest;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const COMPLETED_STATUSES = ['COMPLETED', 'CLOSED'];

    private const STATUS_LABELS = [
        'PLANNING' => 'Planning',
        'RFQ_SUBMITTED' => 'RFQ Submitted',
        'PROPOSAL_REVIEW' => 'Review',
        'DESIGN_REVIEW' => 'Design Review',
        'EXEC_ENDORSED' => 'Approval',
        'NTP_PROCESSING' => 'NTP',
        'SCHEDULING' => 'Scheduling',
        'ONGOING' => 'Ongoing',
        'ON_HOLD' => 'On Hold',
        'COMPLETED' => 'Completed',
        'CLOSED' => 'Closed',
        'CANCELED' => 'Canceled',
    ];

    public function __invoke(): Response
    {
        $today = now()->startOfDay();
        $inactiveStatuses = [...self::COMPLETED_STATUSES, 'CANCELED'];
        $totalProjects = Project::count();

        return Inertia::render('dashboard', [
            'stats' => [
                'active_projects' => Project::whereNotIn('status_key', $inactiveStatuses)->count(),
                'critical_projects' => $this->criticalProjectsQuery($today)->count(),
                'about_to_lapse' => Project::whereNotIn('status_key', self::COMPLETED_STATUSES)
                    ->whereBetween('deadline', [$today, $today->copy()->addDays(7)])
                    ->count(),
                'pending_requests' => ProjectRequest::where('status', 'pending')->count(),
                'completion_target' => 90,
                'completion_actual' => (int) round((float) Project::avg('completion_percent')),
                'success_rate' => $this->successRate($totalProjects),
                'budget_total' => (float) Project::sum('budget_total'),
                'budget_paid' => (float) Project::sum('budget_paid'),
            ],
            'status_distribution' => $this->statusDistribution(),
            'latest_requests' => $this->latestRequests(),
            'critical_projects' => $this->criticalProjects($today),
        ]);
    }

    private function successRate(int $totalProjects): int
    {
        if ($totalProjects === 0) {
            return 0;
        }

        $completedProjects = Project::whereIn('status_key', self::COMPLETED_STATUSES)->count();

        return (int) round(($completedProjects / $totalProjects) * 100);
    }

    private function statusDistribution()
    {
        $statusCounts = Project::select('status_key')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status_key')
            ->get()
            ->pluck('total', 'status_key');

        return collect(self::STATUS_LABELS)->map(fn ($label, $key) => [
            'label' => $label,
            'count' => (int) ($statusCounts[$key] ?? 0),
        ])->values();
    }

    private function latestRequests()
    {
        return ProjectRequest::with('requester')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (ProjectRequest $request) => [
                'id' => $request->id,
                'ref' => $request->request_no ?? 'REQ-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT),
                'title' => $request->title,
                'requested_by' => $request->requester?->name ?? 'Unknown requester',
                'date' => $request->created_at?->format('M d, Y') ?? '-',
                'status' => $request->status,
            ]);
    }

    private function criticalProjects(Carbon $today)
    {
        return Project::whereNotIn('status_key', self::COMPLETED_STATUSES)
            ->where(function ($query) use ($today) {
                $query->where('status_key', 'ON_HOLD')
                    ->orWhereDate('deadline', '<', $today)
                    ->orWhereBetween('deadline', [$today, $today->copy()->addDays(7)]);
            })
            ->orderBy('deadline')
            ->take(4)
            ->get()
            ->map(fn (Project $project) => $this->criticalProjectData($project, $today));
    }

    private function criticalProjectsQuery(Carbon $today)
    {
        return Project::whereNotIn('status_key', self::COMPLETED_STATUSES)
            ->where(function ($query) use ($today) {
                $query->where('status_key', 'ON_HOLD')
                    ->orWhereDate('deadline', '<', $today);
            });
    }

    private function criticalProjectData(Project $project, Carbon $today): array
    {
        $deadline = $project->deadline ? Carbon::parse($project->deadline)->startOfDay() : null;
        $days = $deadline ? $today->diffInDays($deadline, false) : null;
        $isOverdue = $days !== null && $days < 0;

        return [
            'id' => $project->id,
            'name' => $project->title,
            'note' => $isOverdue
                ? 'Delayed by ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's')
                : ($days === 0 ? 'Due today' : 'Due in ' . $days . ' day' . ($days === 1 ? '' : 's')),
            'badge' => $isOverdue ? 'Critical' : ($project->status_key === 'ON_HOLD' ? 'On Hold' : 'Due Soon'),
            'progress' => $project->completion_percent,
        ];
    }
}
