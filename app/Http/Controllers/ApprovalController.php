<?php

namespace App\Http\Controllers;

use App\Models\ProjectNtp;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Support\ApprovalFlow;
use App\Support\NtpPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "For Approval" portal for the PMD and division sign-off roles.
 *
 * It shows only what is actually sitting in the signed-in role's queue — the
 * step the chain is currently waiting on — plus a record of what that role has
 * already settled. Requests stop at the PMD Department Manager; NTPs carry on
 * to the Division Manager, so a Division Manager sees no request queue.
 */
class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalFlow $flow)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $this->actingRole($user);

        return Inertia::render('approvals/index', [
            'role'       => $role,
            'role_label' => User::roleLabel($role),
            'requests'   => $this->pendingRequests($role),
            'ntps'       => $this->pendingNtps($role),
            'history'    => $this->history($user),
            // A Division Manager only signs NTPs; hide the request queue entirely
            // rather than showing them a tab that can never hold anything.
            'shows_requests' => in_array($role, ProjectRequest::APPROVAL_CHAIN, true),
        ]);
    }

    public function approveRequest(Request $request, ProjectRequest $projectRequest): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);

        abort_unless(
            $this->flow->approveRequest($projectRequest, $request->user(), $data['remarks'] ?? null),
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
        $role = self::resolveActingRole($user);

        if ($role === null) {
            return 0;
        }

        return ProjectRequest::awaitingRole($role)->count()
            + ProjectNtp::awaitingRole($role)->count();
    }

    private function pendingRequests(?string $role): array
    {
        if ($role === null) {
            return [];
        }

        return ProjectRequest::awaitingRole($role)
            ->with(['requester', 'approvals.user', 'attachments'])
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
            ])->values()->all();
    }

    private function pendingNtps(?string $role): array
    {
        if ($role === null) {
            return [];
        }

        return ProjectNtp::awaitingRole($role)
            // The presenter dereferences the project, and deleting a project
            // leaves its NTPs behind — without this one soft-deleted project
            // takes down the whole portal.
            ->whereHas('project')
            ->with(['project.projectRequest', 'creator', 'reviewer', 'rfq.items', 'approvals.user'])
            ->latest()
            ->get()
            ->map(fn (ProjectNtp $ntp) => NtpPresenter::row($ntp))
            ->values()->all();
    }

    /** What this user has already settled, newest first. */
    private function history(User $user): array
    {
        return $user->approvalSteps()
            ->with('approvable')
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
                    'acted_at' => $step->acted_at?->format('M d, Y h:i A'),
                    'link'     => $record instanceof ProjectNtp
                        ? ($record->project_id ? route('projects.hub.ntp', $record->project_id, absolute: false) : null)
                        : ($record ? route('requests.show', $record->id, absolute: false) : null),
                ];
            })->values()->all();
    }

    private function actingRole(User $user): ?string
    {
        return self::resolveActingRole($user);
    }

    /**
     * The approval role this user acts as. Admins have no queue of their own —
     * they can settle any step from the record's own screen — so the portal
     * shows them nothing rather than a role they do not hold.
     */
    private static function resolveActingRole(User $user): ?string
    {
        foreach (User::APPROVAL_ROLES as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }
}
