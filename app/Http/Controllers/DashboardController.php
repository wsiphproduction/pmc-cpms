<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
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

    private const INACTIVE_STATUSES = ['COMPLETED', 'CLOSED', 'CANCELED'];

    public function __invoke(): Response
    {
        /** @var User $user */
        $user = auth()->user();
        $isDeptUser = !$user->hasRole(['approver', 'assistant_manager', 'admin']);
        $today = now()->startOfDay();

        // Sub-projects are components of their parent, not standalone entries —
        // exclude them everywhere on the dashboard so counts/averages don't double up.
        $projectsQuery = Project::query()
            ->whereNull('parent_id')
            ->when($isDeptUser, fn (Builder $q) => $q->whereHas('projectRequest', fn ($rq) => $rq->where('requester_id', $user->id)));

        $requestsQuery = ProjectRequest::query()
            ->when($isDeptUser, fn (Builder $q) => $q->where('requester_id', $user->id));

        $visibleProjectIds = $isDeptUser ? (clone $projectsQuery)->pluck('id') : null;

        return Inertia::render('dashboard', [
            'stats' => $isDeptUser
                ? $this->deptStats($user->id, $projectsQuery, $requestsQuery)
                : $this->engineerStats($today),
            'kpi' => $isDeptUser ? null : [
                'target' => (int) Setting::get('project_completion_kpi', 80),
                // Average the effective (rolled-up) completion of top-level projects.
                'actual' => (int) round(
                    Project::whereNull('parent_id')
                        ->with('children:id,parent_id,completion_percent')
                        ->get()
                        ->avg(fn (Project $p) => $p->effectiveCompletionPercent()) ?? 0
                ),
            ],
            'tables' => [
                'notifications' => $this->notifications($user->id),
                'projects' => (clone $projectsQuery)->with('children:id,parent_id,completion_percent')->latest()->take(5)->get()->map(fn (Project $p) => [
                    'id' => $p->id,
                    'project_no' => $p->project_no,
                    'title' => $p->title,
                    'status' => self::STATUS_LABELS[$p->status_key] ?? $p->status_key,
                    'health' => $p->health(),
                    'progress' => $p->effectiveCompletionPercent(),
                ]),
                'requests' => (clone $requestsQuery)->with('requester')->latest()->take(5)->get()->map(fn (ProjectRequest $r) => [
                    'id' => $r->id,
                    'request_no' => $r->request_no,
                    'title' => $r->title,
                    'requester' => $r->requester?->name ?? 'Unknown',
                    'status' => $r->status,
                    'created_at' => $r->created_at?->format('M d, Y') ?? '-',
                ]),
                'audit_trail' => AuditTrail::where('reference_type', Project::class)
                    ->when($isDeptUser, fn ($q) => $q->whereIn('reference_id', $visibleProjectIds))
                    ->with('user')
                    ->latest()
                    ->take(8)
                    ->get()
                    ->map(fn (AuditTrail $log) => [
                        'id' => $log->id,
                        'date' => $log->created_at?->format('M d, Y h:i A') ?? '-',
                        'user' => $log->user?->name ?? 'System',
                        'action' => $log->action,
                        'module' => $log->changes['module'] ?? 'Project',
                    ]),
            ],
        ]);
    }

    private function deptStats(int $userId, Builder $projectsQuery, Builder $requestsQuery): array
    {
        return [
            'active_projects' => (clone $projectsQuery)->whereNotIn('status_key', self::INACTIVE_STATUSES)->count(),
            'unread_messages' => Notification::where('recipient', $userId)
                ->where('is_read', false)
                ->where('message', 'like', 'New Comment%')
                ->count(),
            'my_requests' => (clone $requestsQuery)->count(),
        ];
    }

    private function engineerStats(Carbon $today): array
    {
        $activeProjects = Project::whereNull('parent_id')
            ->whereNotIn('status_key', self::INACTIVE_STATUSES)
            ->with('children:id,parent_id,completion_percent')
            ->get();

        return [
            'active_project' => $activeProjects->count(),
            'delayed' => $activeProjects->filter(fn (Project $p) => $p->health() === 'Delayed')->count(),
            'about_to_lapse' => Project::whereNull('parent_id')
                ->whereNotIn('status_key', self::INACTIVE_STATUSES)
                ->whereBetween('deadline', [$today, $today->copy()->addDays(7)])
                ->count(),
            'pending_request' => ProjectRequest::where('status', 'pending')->count(),
        ];
    }

    private function notifications(int $userId)
    {
        return Notification::where('recipient', $userId)
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'message' => $n->message,
                'link' => $n->link,
                'is_read' => $n->is_read,
                'created_at' => $n->created_at?->diffForHumans(),
            ]);
    }
}
