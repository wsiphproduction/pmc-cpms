<?php

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\EmsSurveyReport;
use App\Models\Notification;
use App\Models\RosterBreak;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Moves an Environment Survey Report up its sign-off chain and tells each
 * person along the way: the engineer submits it, the PMD Assistant Manager
 * reviews it and the PMD Manager notes it (from For Approval), then the
 * MEPEO representative verifies it and the MEPEO Manager approves it (from
 * the MEPEO page). The rules live here rather than in either controller.
 */
class EmsReportFlow
{
    /**
     * The engineer's "Prepared by": submit for review, naming the MEPEO
     * representative who verifies it once PMD has signed. A report sent back
     * to be revised starts the chain afresh.
     */
    public function submit(EmsSurveyReport $report, User $engineer, User $rep, bool $mail = true): void
    {
        $report->approvals()->delete();
        $report->unsetRelation('approvals');

        $report->update([
            'status'         => EmsSurveyReport::STATUS_FOR_APPROVAL,
            'mepeo_rep_id'   => $rep->id,
            'submitted_at'   => now(),
            'sent_at'        => null,
            'return_remarks' => null,
        ]);
        $report->startApprovalChain();

        $project = $report->project;

        AuditTrail::log("Environment Survey Report {$report->control_no} submitted for review by {$engineer->name}", $project, ['module' => 'EMS', 'type' => 'update']);

        $this->notifyCurrentSigner(
            $report,
            "Environment Survey Report {$report->control_no} for project {$project->project_no} is awaiting your review.",
            $mail
        );
    }

    /**
     * Name another representative, any time before the verification is
     * given. If it already waits on them, the new one is told.
     */
    public function reassign(EmsSurveyReport $report, User $rep, bool $mail = true): void
    {
        $report->update(['mepeo_rep_id' => $rep->id]);

        $project = $report->project;

        AuditTrail::log("Environment Survey Report {$report->control_no} reassigned to {$rep->name} (MEPEO)", $project, ['module' => 'EMS', 'type' => 'update']);

        if ($report->currentApprovalRole() === User::ROLE_MEPEO_REP) {
            Notification::notifyAboutEmsReport(
                $report,
                $rep->id,
                "Environment Survey Report {$report->control_no} for project {$project->project_no} was passed to you to verify.",
                $this->mepeoLink($report),
                $mail
            );
        }
    }

    /**
     * Sign the current step. Returns false when the user was not the one being
     * waited on — or, for the representative, when MEPEO's Status is not set.
     */
    public function approve(EmsSurveyReport $report, User $user, ?string $remarks = null): bool
    {
        if (! $this->mayAct($report, $user)) {
            return false;
        }

        if ($report->currentApprovalRole() === User::ROLE_MEPEO_REP && blank($report->mepeo_status)) {
            return false;
        }

        $step = $report->recordApproval($user, $remarks);
        $verb = EmsSurveyReport::SIGNATURE_VERBS[$step->role] ?? 'approved';
        $label = $step->officeLabel();
        $project = $report->project;

        AuditTrail::log("Environment Survey Report {$report->control_no} {$verb} by the {$label}", $project, ['module' => 'EMS', 'type' => 'update']);

        if ($report->approvalChainComplete()) {
            $report->update(['status' => EmsSurveyReport::STATUS_APPROVED]);

            $message = "Environment Survey Report {$report->control_no} for project {$project->project_no} completed its sign-off.";

            if ($report->created_by) {
                Notification::notifyAboutEmsReport($report, $report->created_by, $message, $this->hubLink($report));
            }

            if ($report->mepeo_rep_id) {
                Notification::notifyAboutEmsReport($report, $report->mepeo_rep_id, $message, $this->mepeoLink($report));
            }

            return true;
        }

        // The PMD Manager's note passes it to MEPEO.
        if ($report->currentApprovalRole() === User::ROLE_MEPEO_REP && $report->sent_at === null) {
            $report->update(['sent_at' => now()]);
        }

        $this->notifyCurrentSigner(
            $report,
            $report->currentApprovalRole() === User::ROLE_MEPEO_REP
                ? "Environment Survey Report {$report->control_no} for project {$project->project_no} was reviewed and noted by PMD and is awaiting your verification."
                : "Environment Survey Report {$report->control_no} for project {$project->project_no} is awaiting your signature."
        );

        if ($report->created_by) {
            Notification::notifyAboutEmsReport(
                $report,
                $report->created_by,
                "Environment Survey Report {$report->control_no} was {$verb} by the {$label} and is now with the "
                    . User::roleLabel($report->currentApprovalRole()) . '.',
                $this->hubLink($report)
            );
        }

        return true;
    }

    /**
     * Send the report back to the engineer to revise. Their next submission
     * restarts the chain from the PMD Assistant Manager.
     */
    public function returnForRevision(EmsSurveyReport $report, User $user, ?string $remarks = null): bool
    {
        if (! $this->mayAct($report, $user)) {
            return false;
        }

        $step = $report->recordRejection($user, $remarks);
        $label = $step->officeLabel();
        $project = $report->project;

        $report->update([
            'status'         => EmsSurveyReport::STATUS_DRAFT,
            'return_remarks' => $remarks,
        ]);

        AuditTrail::log("Environment Survey Report {$report->control_no} returned to the engineer by the {$label}", $project, ['module' => 'EMS', 'type' => 'update']);

        $reason = $remarks ? " Reason: {$remarks}" : '';

        if ($report->created_by) {
            Notification::notifyAboutEmsReport(
                $report,
                $report->created_by,
                "Environment Survey Report {$report->control_no} was returned to you by the {$label} for revision.{$reason}",
                $this->hubLink($report)
            );
        }

        // The representative already verified it; let them know it is back with the engineer.
        if ($step->role === User::ROLE_MEPEO_MANAGER && $report->mepeo_rep_id) {
            Notification::notifyAboutEmsReport(
                $report,
                $report->mepeo_rep_id,
                "Environment Survey Report {$report->control_no} was returned to the engineer by the {$label} for revision.{$reason}",
                $this->mepeoLink($report)
            );
        }

        return true;
    }

    /**
     * Admins may settle any step — they administer the whole flow — everyone
     * else must hold the step being waited on.
     */
    private function mayAct(EmsSurveyReport $report, User $user): bool
    {
        if ($report->status !== EmsSurveyReport::STATUS_FOR_APPROVAL || $report->currentApproval() === null) {
            return false;
        }

        return $user->hasRole(User::ROLE_ADMIN) || $report->awaitingApprovalFrom($user);
    }

    /**
     * Call on whoever holds the step the chain now waits on, from the page
     * they sign on. The verification goes to the named representative only.
     */
    private function notifyCurrentSigner(EmsSurveyReport $report, string $message, bool $mail = true): void
    {
        $role = $report->currentApprovalRole();

        if ($role === null) {
            return;
        }

        if ($role === User::ROLE_MEPEO_REP) {
            if ($report->mepeo_rep_id) {
                Notification::notifyAboutEmsReport($report, $report->mepeo_rep_id, $message, $this->mepeoLink($report), $mail);
            }

            return;
        }

        Notification::notifyAboutEmsReport(
            $report,
            $this->roleHolders($role),
            $message,
            $role === User::ROLE_MEPEO_MANAGER ? $this->mepeoLink($report) : route('approvals.index', absolute: false),
            $mail
        );
    }

    /** Everyone holding the role, plus whoever covers it as OIC today. */
    private function roleHolders(string $role): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))
            ->pluck('id')
            ->merge(RosterBreak::oicIdsCovering([$role]))
            ->unique()
            ->values();
    }

    private function mepeoLink(EmsSurveyReport $report): string
    {
        return route('mepeo.reports.show', $report->id, absolute: false);
    }

    private function hubLink(EmsSurveyReport $report): string
    {
        return route('projects.hub.ems', $report->project_id, absolute: false);
    }
}
