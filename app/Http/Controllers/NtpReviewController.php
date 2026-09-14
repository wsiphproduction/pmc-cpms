<?php

namespace App\Http\Controllers;

use App\Models\ProjectNtp;
use App\Models\User;
use App\Support\ApprovalFlow;
use App\Support\NtpPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The project-side steps on an NTP: the department user's, and after it the
 * division manager user's over that department. They come once PMD (Assistant
 * Manager, Department Manager, Division Manager) has signed from the approvals
 * portal; both paths go through ApprovalFlow.
 */
class NtpReviewController extends Controller
{
    public function __construct(private readonly ApprovalFlow $flow)
    {
    }

    /**
     * List the NTPs this reviewer follows, whichever step they are on.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Show the full history — records for review, issued, and rejected — not
        // just those pending review. Ordered so pending-review surfaces first.
        $query = ProjectNtp::with(['project.projectRequest', 'creator', 'reviewer', 'rfq.items', 'approvals.user'])
            ->orderByRaw("CASE WHEN status = 'pending_review' THEN 0 ELSE 1 END")
            ->latest();

        // A department user follows NTPs on the projects they requested and on
        // the ones their department owns — an engineer can register a project
        // with no request behind it, and its NTPs still need a reviewer. A
        // division manager user follows every department in their division.
        // Admins can see every NTP.
        $query->reviewableBy($user);

        return Inertia::render('ntp-reviews/index', [
            'ntps' => $query->get()->map(fn (ProjectNtp $ntp) => [
                ...NtpPresenter::row($ntp),
                // Only the reviewer's own step is actionable from this page.
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
