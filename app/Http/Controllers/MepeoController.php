<?php

namespace App\Http\Controllers;

use App\Models\EmsSurveyReport;
use App\Models\User;
use App\Support\EmsReportFlow;
use App\Support\EmsReportPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The MEPEO page — the only part of the system the environmental office
 * sees. A representative fills in the EMS Survey Reports sent to them; the
 * MEPEO Manager approves what the representatives submit.
 */
class MepeoController extends Controller
{
    private const WITH = ['project', 'creator', 'mepeoRep', 'approvals.user', 'approvals.onBehalfOf'];

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

        $report->update($this->validatedBody($request, false));

        return back()->with('success', 'Saved.');
    }

    /** Save and send up for the MEPEO Manager's approval. */
    public function submit(Request $request, EmsSurveyReport $report): RedirectResponse
    {
        $user = $request->user();

        abort_unless($report->editableBy($user), 403);

        $report->update($this->validatedBody($request, true));

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

    /**
     * The representative's part of the form. Submitting asks for MEPEO's
     * finding and at least one ticked activity and aspect (or an "Others").
     */
    private function validatedBody(Request $request, bool $submitting): array
    {
        $activities = array_keys(config('ems.major_activities'));
        $aspects = array_keys(config('ems.aspects'));

        $data = $request->validate([
            'major_activities'       => ['array'],
            'major_activities.*'     => ['string', Rule::in($activities)],
            'major_activities_other' => ['nullable', 'string', 'max:255'],
            'other_scope'            => ['nullable', 'string', 'max:5000'],
            'aspects'                => ['array'],
            'aspects.*'              => ['string', Rule::in($aspects)],
            'aspects_other'          => ['nullable', 'string', 'max:255'],
            'impacts'                => ['nullable', 'string', 'max:5000'],
            'mitigation'             => ['nullable', 'string', 'max:5000'],
            'mepeo_status'           => [$submitting ? 'required' : 'nullable', Rule::in(array_keys(config('ems.statuses')))],
            'remarks'                => ['nullable', 'string', 'max:5000'],
        ], [
            'mepeo_status.required' => 'Choose the Status before submitting.',
        ]);

        $data['major_activities'] = array_values(array_unique($data['major_activities'] ?? []));
        $data['aspects'] = array_values(array_unique($data['aspects'] ?? []));

        if ($submitting) {
            $errors = [];

            if ($data['major_activities'] === [] && blank($data['major_activities_other'] ?? null)) {
                $errors['major_activities'] = 'Tick at least one major activity, or fill in Others.';
            }

            if ($data['aspects'] === [] && blank($data['aspects_other'] ?? null)) {
                $errors['aspects'] = 'Tick at least one environmental aspect, or fill in Others.';
            }

            if ($errors !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages($errors);
            }
        }

        return $data;
    }
}
