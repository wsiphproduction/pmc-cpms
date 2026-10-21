<?php

namespace App\Http\Controllers;

use App\Models\ProjectNtp;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Support\ApprovalFlow;
use App\Support\NtpPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "For Approval" portal for the PMD and division sign-off roles.
 *
 * It shows only what is actually sitting in the signed-in role's queue — the
 * step the chain is currently waiting on — plus a record of what that role has
 * already settled. Requests stop at the PMD Department Manager; NTPs carry on
 * to the Division Manager, so a Division Manager sees no request queue. After
 * the Division Manager an NTP leaves this portal for the project's own
 * reviewers (department user, then division manager user), who sign from the
 * NTP Reviews page.
 */
class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalFlow $flow)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $roles = $user->actingApprovalRoles();
        // Their own seat heads the page; a role covered as OIC is bannered
        // separately (the Dept Manager covering an Assistant Manager is still
        // the Dept Manager here).
        $role = collect(User::APPROVAL_ROLES)->first(fn (string $r) => $user->hasRole($r)) ?? $roles[0] ?? null;

        // The break the user is covering under, or away on, today — the page
        // banners both so nobody wonders why a queue grew or who is minding it.
        $duty = $user->activeOicDuty();
        $away = $user->activeRosterBreak();

        return Inertia::render('approvals/index', [
            'role'       => $role,
            'role_label' => User::roleLabel($role),
            'requests'   => $this->pendingRequests($roles),
            'ntps'       => $this->pendingNtps($user, $roles),
            'history'    => $this->history($user),
            // A Division Manager only signs NTPs; hide the request queue entirely
            // rather than showing them a tab that can never hold anything.
            'shows_requests' => array_intersect($roles, ProjectRequest::APPROVAL_CHAIN) !== [],
            // Who the PMD Department Manager may name to run an approved request.
            'engineers' => User::role(User::ROLE_ENGINEER)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $engineer) => ['value' => (string) $engineer->id, 'label' => $engineer->name]),
            'oic_for' => $duty ? [
                'manager'    => $duty->user?->name,
                'role_label' => User::roleLabel($duty->role),
                'ends_on'    => $duty->ends_on->format('M d, Y'),
                // Whatever the manager left for their OIC when scheduling.
                'notes'      => $duty->notes,
            ] : null,
            'on_break' => $away ? [
                'oic'     => $away->oic?->name,
                'ends_on' => $away->ends_on->format('M d, Y'),
            ] : null,
        ]);
    }

    public function approveRequest(Request $request, ProjectRequest $projectRequest): RedirectResponse
    {
        $data = $request->validate([
            'remarks'     => ['nullable', 'string', 'max:1000'],
            // Optional: the PMD Department Manager may name who runs the project.
            'engineer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(
                fn ($q) => $q->whereIn('id', User::role(User::ROLE_ENGINEER)->select('id'))
            )],
        ]);

        abort_unless(
            $this->flow->approveRequest(
                $projectRequest,
                $request->user(),
                $data['remarks'] ?? null,
                isset($data['engineer_id']) ? (int) $data['engineer_id'] : null,
            ),
            403,
            'This request is not awaiting your approval.'
        );

        return back()->with('success', "Request #{$projectRequest->request_no} approved.");
    }

    public function rejectRequest(Request $request, ProjectRequest $projectRequest): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);

        abort_unless(
            $this->flow->rejectRequest($projectRequest, $request->user(), $data['remarks'] ?? null),
            403,
            'This request is not awaiting your approval.'
        );

        return back()->with('success', "Request #{$projectRequest->request_no} rejected.");
    }

    public function approveNtp(Request $request, ProjectNtp $ntp): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);

        abort_unless(
            $this->flow->approveNtp($ntp, $request->user(), $data['remarks'] ?? null),
            403,
            'This NTP is not awaiting your approval.'
        );

        return back()->with('success', "NTP {$ntp->ntp_no} approved.");
    }

    public function rejectNtp(Request $request, ProjectNtp $ntp): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);

        abort_unless(
            $this->flow->rejectNtp($ntp, $request->user(), $data['remarks'] ?? null),
            403,
            'This NTP is not awaiting your approval.'
        );

        return back()->with('success', "NTP {$ntp->ntp_no} rejected.");
    }

    /**
     * How many items are waiting on this user right now — shared with the nav
     * so the badge and the portal never disagree.
     */
    public static function pendingCountFor(User $user): int
    {
        $roles = $user->actingApprovalRoles();

        if ($roles === []) {
            return 0;
        }

        return ProjectRequest::awaitingAnyRole($roles)->count()
            + self::ntpsAwaiting($user, $roles)->count();
    }

    /**
     * The NTPs waiting on this user: their roles' queue, less the steps a
     * project has named somebody else for, plus any a project names them for.
     *
     * @param  array<int, string>  $roles
     */
    private static function ntpsAwaiting(User $user, array $roles, array $with = []): \Illuminate\Support\Collection
    {
        return ProjectNtp::signableBy($user, $roles)
            ->with($with)
            ->latest()
            ->get()
            ->filter(fn (ProjectNtp $ntp) => $ntp->awaitingApprovalFrom($user))
            ->values();
    }

    /** @param  array<int, string>  $roles */
    private function pendingRequests(array $roles): array
    {
        if ($roles === []) {
            return [];
        }

        return ProjectRequest::awaitingAnyRole($roles)
            ->with(['requester', 'approvals.user', 'approvals.onBehalfOf', 'attachments'])
            ->latest()
            ->get()
            ->map(fn (ProjectRequest $projectRequest) => [
                'id'           => $projectRequest->id,
                'request_no'   => $projectRequest->request_no,
                'title'        => $projectRequest->title,
                'job_type'     => $projectRequest->job_type,
                'job_location' => $projectRequest->job_location,
                'description'  => $projectRequest->description,
                'costcode'     => $projectRequest->costcode,
                'opex'         => $projectRequest->opex,
                'capex'        => $projectRequest->capex,
                'for_budgeting' => $projectRequest->for_budgeting,
                'status'       => $projectRequest->status,
                'created_at'   => $projectRequest->created_at?->format('M d, Y h:i A'),
                'attachments'  => $projectRequest->attachments->count(),
                'requester'    => [
                    'name'       => $projectRequest->requester?->name ?? 'Unknown',
                    'department' => $projectRequest->requester?->department,
                ],
                'approvals'    => $projectRequest->approvalTimeline(),
                // The final signature, where an engineer may be assigned.
                'assigns_engineer' => $projectRequest->currentApprovalRole() === User::ROLE_PMD_DEPT_MANAGER,
            ])->values()->all();
    }

    /** @param  array<int, string>  $roles */
    private function pendingNtps(User $user, array $roles): array
    {
        if ($roles === []) {
            return [];
        }

        // `signableBy` keeps out NTPs whose project was deleted: the presenter
        // dereferences the project, and one soft-deleted project would take
        // down the whole portal.
        return self::ntpsAwaiting($user, $roles, ['creator', 'reviewer', 'rfq.items', 'approvals.user', 'approvals.onBehalfOf'])
            ->map(fn (ProjectNtp $ntp) => NtpPresenter::row($ntp))
            ->values()->all();
    }

    /** What this user has already settled, newest first. */
    private function history(User $user): array
    {
        return $user->approvalSteps()
            ->with(['approvable', 'onBehalfOf'])
            ->where('status', '!=', 'pending')
            ->latest('acted_at')
            ->take(30)
            ->get()
            ->map(function ($step) {
                $record = $step->approvable;

                return [
                    'id'       => $step->id,
                    'type'     => $record instanceof ProjectNtp ? 'NTP' : 'Request',
                    'label'    => $record instanceof ProjectNtp
                        ? ($record->ntp_no . ' — ' . $record->contractor_name)
                        : ($record?->request_no . ' — ' . $record?->title),
                    'status'   => $step->status,
                    'remarks'  => $step->remarks,
                    // Named when this decision was given as OIC for that manager.
                    'on_behalf_of' => $step->onBehalfOf?->name,
                    'acted_at' => $step->acted_at?->format('M d, Y h:i A'),
                    // The page builds the href with Ziggy so it carries the same base
                    // URL as every other link — a server-side relative route drops
                    // the app's path prefix and 404s on a sub-path deployment.
                    'link_id'  => $record instanceof ProjectNtp ? $record->project_id : $record?->id,
                ];
            })->values()->all();
    }

}
