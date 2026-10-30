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
 * sees. A representative fills in the EMS Survey Reports sent to them; the
 * MEPEO Manager approves what the representatives submit.
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

        return Inertia::render('mepeo/show', [
            'report'   => EmsReportPresenter::row($report),
            'options'  => EmsReportPresenter::options(),
            'can_edit' => $report->editableBy($user),
            'can_sign' => $report->status === EmsSurveyReport::STATUS_FOR_APPROVAL && $report->awaitingApprovalFrom($user),
        ]);
    }

    /** Save what has been filled in so far. */
    public function update(Request $request, EmsSurveyReport $report): RedirectResponse
    {
        abort_unless($report->editableBy($request->user()), 403);

        $this->save($request, $report, false);

        return back()->with('success', 'Saved.');
    }

    /** Save and send up for the MEPEO Manager's approval. */
    public function submit(Request $request, EmsSurveyReport $report): RedirectResponse
    {
        $user = $request->user();

        abort_unless($report->editableBy($user), 403);

        $this->save($request, $report, true);

        $this->flow->submit($report, $user);

        return redirect()->route('mepeo.index')
            ->with('success', "EMS Survey Report {$report->control_no} submitted to the MEPEO Manager.");
    }

    /**
     * What sits with this user: a representative's reports to fill out, and
     * the reports at the MEPEO Manager's signature. Shared with the nav badge.
     */
    public static function pendingCountFor(User $user): int
    {
        if (! $user->isMepeo()) {
            return 0;
        }

        $toFill = EmsSurveyReport::where('status', EmsSurveyReport::STATUS_WITH_MEPEO)
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

    /** The representative's part of the form and the supporting documents. */
    private function save(Request $request, EmsSurveyReport $report, bool $submitting): void
    {
        $files = EmsReportInput::attachments($request);
        $body = EmsReportInput::body($request, $submitting);

        $report->update($body);
        EmsReportInput::syncAttachments($report, $files);
    }
}
