<?php

namespace App\Http\Controllers;

use App\Models\ProjectNtp;
use App\Models\User;
use App\Support\ApprovalFlow;
use App\Support\NtpPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The department user's step on an NTP — the first signature in the chain.
 * Everything after it (PMD Assistant Manager, PMD Department Manager, Division
 * Manager) is settled from the approvals portal; both go through ApprovalFlow.
 */
class NtpReviewController extends Controller
{
    public function __construct(private readonly ApprovalFlow $flow)
    {
    }

    /**
     * List NTPs awaiting the department user's review.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Show the full history — records for review, issued, and rejected — not
        // just those pending review. Ordered so pending-review surfaces first.
        $query = ProjectNtp::with(['project.projectRequest', 'creator', 'reviewer', 'rfq.items', 'approvals.user'])
            ->orderByRaw("CASE WHEN status = 'pending_review' THEN 0 ELSE 1 END")
            ->latest();

        // A department user reviews NTPs on the projects they requested and on
        // the ones their department owns — an engineer can register a project
        // with no request behind it, and its NTPs still need a reviewer.
        // Admins can see every NTP.
        if (!$user->hasRole(User::ROLE_ADMIN)) {
            $query->whereHas('project', fn (Builder $q) => $q->forDepartmentUser($user));
        }

        return Inertia::render('ntp-reviews/index', [
            'ntps' => $query->get()->map(fn (ProjectNtp $ntp) => [
                ...NtpPresenter::row($ntp),
                // Only the department's own step is actionable from this page.
                'can_act' => $ntp->awaitingApprovalFrom($user) || $user->hasRole(User::ROLE_ADMIN),
            ])->values(),
        ]);
    }

    public function approve(Request $request, ProjectNtp $ntp): RedirectResponse
    {
        abort_unless(
            $this->flow->approveNtp($ntp, $request->user()),
            403,
            'This NTP is not awaiting your review.'
        );

        return back()->with('success', $ntp->fresh()->status === 'issued'
            ? "NTP {$ntp->ntp_no} approved and issued."
            : "NTP {$ntp->ntp_no} approved and endorsed to the " . User::roleLabel($ntp->currentApprovalRole()) . '.');
    }

    public function reject(Request $request, ProjectNtp $ntp): RedirectResponse
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless(
            $this->flow->rejectNtp($ntp, $request->user(), $data['remarks'] ?? null),
            403,
            'This NTP is not awaiting your review.'
        );

        return back()->with('success', "NTP {$ntp->ntp_no} rejected.");
    }
}
