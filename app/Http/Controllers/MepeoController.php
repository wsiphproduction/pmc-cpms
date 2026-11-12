<?php

namespace App\Http\Controllers;

use App\Models\EmsSurveyReport;
use App\Models\User;
use App\Support\EmsReportFlow;
use App\Support\EmsReportInput;
use App\Support\EmsReportPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The MEPEO page — the only part of the system the environmental office
 * sees. Once PMD has reviewed and noted an Environment Survey Report, the
 * representative named on it fills in MEPEO's Status and Remarks and
 * verifies it; the MEPEO Manager then approves it.
 */
class MepeoController extends Controller
{
    private const WITH = EmsReportPresenter::WITH;

    public function __construct(private readonly EmsReportFlow $flow) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $reports = EmsSurveyReport::query()
            ->visibleToMepeo($user)
            ->whereHas('project')
            ->with(self::WITH)
            ->latest('sent_at')
            ->latest('id')
            ->get();

        return Inertia::render('mepeo/index', [
            'reports'    => $reports->map(fn (EmsSurveyReport $report) => [
                ...EmsReportPresenter::row($report),
                'needs_me' => self::needsActionFrom($report, $user),
            ])->values(),
            'is_manager' => $user->hasRole(User::ROLE_MEPEO_MANAGER),
        ]);
    }

    public function show(Request $request, EmsSurveyReport $report): Response
    {
        $user = $request->user();

        abort_unless($report->project && $report->isVisibleToMepeo($user), 404);

        $report->load(self::WITH);

        $canEdit = $report->editableBy($user);

        return Inertia::render('mepeo/show', [
            'report'   => EmsReportPresenter::row($report),
            'options'  => EmsReportPresenter::options(),
            // The representative, filling in MEPEO's part to verify it.
            'can_edit' => $canEdit,
            // The MEPEO Manager, while it waits on their approval.
            'can_sign' => ! $canEdit && $report->status === EmsSurveyReport::STATUS_FOR_APPROVAL && $report->awaitingApprovalFrom($user),
        ]);
    }

    /** Save MEPEO's part filled in so far. */
    public function update(Request $request, EmsSurveyReport $report): RedirectResponse
    {
        abort_unless($report->editableBy($request->user()), 403);

        $this->save($request, $report, false);

        return back()->with('success', 'Saved.');
    }

    /** Save and verify — "Inspected/Verified by" — passing it to the MEPEO Manager. */
    public function submit(Request $request, EmsSurveyReport $report): RedirectResponse
    {
        $user = $request->user();

        abort_unless($report->editableBy($user), 403);

        $this->save($request, $report, true);

        abort_unless($this->flow->approve($report->fresh(), $user), 403, 'This report is not awaiting your verification.');

        return redirect()->route('mepeo.index')
            ->with('success', "Environment Survey Report {$report->control_no} verified and passed to the MEPEO Manager.");
    }

    /**
     * What sits with this user: the reports a representative is to verify,
     * and the reports at the MEPEO Manager's signature. Shared with the nav badge.
     */
    public static function pendingCountFor(User $user): int
    {
        if (! $user->isMepeo()) {
            return 0;
        }

        $toFill = EmsSurveyReport::where('status', EmsSurveyReport::STATUS_FOR_APPROVAL)
            ->awaitingRole(User::ROLE_MEPEO_REP)
            ->where('mepeo_rep_id', $user->id)
            ->whereHas('project')
            ->count();

        $toSign = $user->hasRole(User::ROLE_MEPEO_MANAGER)
            ? EmsSurveyReport::where('status', EmsSurveyReport::STATUS_FOR_APPROVAL)
                ->awaitingRole(User::ROLE_MEPEO_MANAGER)
                ->whereHas('project')
                ->count()
            : 0;

        return $toFill + $toSign;
    }

    private static function needsActionFrom(EmsSurveyReport $report, User $user): bool
    {
        return $report->editableBy($user)
            || ($report->status === EmsSurveyReport::STATUS_FOR_APPROVAL && $report->awaitingApprovalFrom($user));
    }

    /** MEPEO's part of the form (Status, Remarks/Comments) and supporting documents. */
    private function save(Request $request, EmsSurveyReport $report, bool $verifying): void
    {
        $files = EmsReportInput::attachments($request);
        $finding = EmsReportInput::finding($request, $verifying);

        $report->update($finding);
        EmsReportInput::syncAttachments($report, $files);
    }
}
