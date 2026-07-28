<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Project;
use App\Models\ProjectWeeklyReport;
use App\Models\User;
use App\Support\AccomplishmentReportWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReportController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        // Preview counts so the page can say what an export would contain.
        $projectIds = Project::where('created_by', $user->id)->pluck('id');

        return Inertia::render('reports/index', [
            'summary' => [
                'projects'      => $projectIds->count(),
                'reports'       => ProjectWeeklyReport::whereIn('project_id', $projectIds)->count(),
                'engineer_name' => $user->name,
            ],
        ]);
    }

    /**
     * Accomplishment report for the signed-in engineer: every project they
     * registered, plus the weekly progress reports filed against each. The
     * optional date range filters the weekly reports, not the projects, so a
     * period export still shows the full project list for context.
     */
    public function accomplishment(Request $request): Response
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $from = $data['from'] ?? null;
        $to   = $data['to'] ?? null;

        $projects = $this->engineerProjects($user, $from, $to);

        $writer = new AccomplishmentReportWriter($user, $projects, $from, $to);

        AuditTrail::log(
            "Generated accomplishment report ({$projects->count()} project(s))",
            $user,
            ['module' => 'Reports', 'type' => 'download']
        );

        return response($writer->build(), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $writer->filename() . '"',
        ]);
    }

    /** @return Collection<int, Project> */
    private function engineerProjects(User $user, ?string $from, ?string $to): Collection
    {
        return Project::where('created_by', $user->id)
            ->with([
                'parent:id,project_no',
                'manager:id,name',
                // Needed by effectiveCompletionPercent() for the roll-up.
                'children:id,parent_id,completion_percent',
                'weeklyReports' => function ($query) use ($from, $to) {
                    $query->with('ntp:id,ntp_no,contractor_name');
                    if ($from) {
                        $query->whereDate('submitted_date', '>=', $from);
                    }
                    if ($to) {
                        $query->whereDate('submitted_date', '<=', $to);
                    }
                },
            ])
            ->orderBy('project_no')
            ->get();
    }
}
