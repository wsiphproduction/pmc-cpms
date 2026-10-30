<?php

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\EmsSurveyReport;
use App\Models\Notification;
use App\Models\RosterBreak;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Moves an EMS Survey Report from the engineer to MEPEO and up its sign-off
 * chain, and tells each person along the way. The MEPEO Manager signs from the
 * MEPEO page and the PMD managers from For Approval, so the rules live here
 * rather than in either controller.
 */
class EmsReportFlow
{
    /** The engineer hands the report to a MEPEO representative to fill in. */
    public function send(EmsSurveyReport $report, User $rep, bool $mail = true): void
    {
        $report->update([
            'status'       => EmsSurveyReport::STATUS_WITH_MEPEO,
            'mepeo_rep_id' => $rep->id,
            'sent_at'      => now(),
        ]);

        $project = $report->project;

        AuditTrail::log("EMS Survey Report {$report->control_no} sent to {$rep->name} (MEPEO)", $project, ['module' => 'EMS', 'type' => 'update']);

        Notification::notifyAboutEmsReport(
            $report,
            $rep->id,
            "EMS Survey Report {$report->control_no} for project {$project->project_no} was sent to you to fill out.",
            $this->mepeoLink($report),
            $mail
        );
    }

    /**
     * The representative's "Inspected/Verified by": the report goes up the
     * chain from its first step, whatever an earlier round reached.
     */
    public function submit(EmsSurveyReport $report, User $rep): void
    {
        // A report sent back to be revised starts the chain afresh.
        $report->approvals()->delete();
        $report->unsetRelation('approvals');

        $report->update([
            'status'         => EmsSurveyReport::STATUS_FOR_APPROVAL,
            'submitted_at'   => now(),
            'return_remarks' => null,
        ]);
        $report->startApprovalChain();

        $project = $report->project;

        AuditTrail::log("EMS Survey Report {$report->control_no} filled out and submitted by {$rep->name} (MEPEO)", $project, ['module' => 'EMS', 'type' => 'update']);

        $this->notifyCurrentSigner($report, "EMS Survey Report {$report->control_no} for project {$project->project_no} is awaiting your approval.");

        if ($report->created_by) {
            Notification::notifyAboutEmsReport(
                $report,
                $report->created_by,
                "EMS Survey Report {$report->control_no} was filled out by {$rep->name} (MEPEO) and is now with the MEPEO Manager.",
                $this->hubLink($report)
            );
        }
    }

    /** Sign the current step. Returns false when the user was not the one being waited on. */
    public function approve(EmsSurveyReport $report, User $user, ?string $remarks = null): bool
    {
        if (! $this->mayAct($report, $user)) {
            return false;
        }

        $step = $report->recordApproval($user, $remarks);
        $verb = EmsSurveyReport::SIGNATURE_VERBS[$step->role] ?? 'approved';
        $label = $step->officeLabel();
        $project = $report->project;

        AuditTrail::log("EMS Survey Report {$report->control_no} {$verb} by the {$label}", $project, ['module' => 'EMS', 'type' => 'update']);

        if ($report->approvalChainComplete()) {
            $report->update(['status' => EmsSurveyReport::STATUS_APPROVED]);

            $message = "EMS Survey Report {$report->control_no} for project {$project->project_no} completed its sign-off.";

            if ($report->created_by) {
                Notification::notifyAboutEmsReport($report, $report->created_by, $message, $this->hubLink($report));
            }

            if ($report->mepeo_rep_id) {
                Notification::notifyAboutEmsReport($report, $report->mepeo_rep_id, $message, $this->mepeoLink($report));
            }

            return true;
        }

        $this->notifyCurrentSigner($report, "EMS Survey Report {$report->control_no} for project {$project->project_no} is awaiting your signature.");

        if ($report->created_by) {
            Notification::notifyAboutEmsReport(
                $report,
                $report->created_by,
                "EMS Survey Report {$report->control_no} was {$verb} by the {$label} and is now with the "
                    . User::roleLabel($report->currentApprovalRole()) . '.',
                $this->hubLink($report)
            );
        }

        return true;
    }

    /**
     * Send the report back to the representative to revise. Their next
     * submission restarts the chain.
     */
    public function returnToRep(EmsSurveyReport $report, User $user, ?string $remarks = null): bool
    {
        if (! $this->mayAct($report, $user)) {
            return false;
        }

        $step = $report->recordRejection($user, $remarks);
        $label = $step->officeLabel();
        $project = $report->project;

        $report->update([
            'status'         => EmsSurveyReport::STATUS_WITH_MEPEO,
            'return_remarks' => $remarks,
        ]);

        AuditTrail::log("EMS Survey Report {$report->control_no} returned to MEPEO by the {$label}", $project, ['module' => 'EMS', 'type' => 'update']);

        $reason = $remarks ? " Reason: {$remarks}" : '';

        if ($report->mepeo_rep_id) {
            Notification::notifyAboutEmsReport(
                $report,
                $report->mepeo_rep_id,
                "EMS Survey Report {$report->control_no} was returned to you by the {$label} for revision.{$reason}",
                $this->mepeoLink($report)
            );
        }

        if ($report->created_by) {
            Notification::notifyAboutEmsReport(
                $report,
                $report->created_by,
                "EMS Survey Report {$report->control_no} was returned to MEPEO by the {$label} for revision.{$reason}",
                $this->hubLink($report)
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

    /** Call on whoever holds the step the chain now waits on, from the page they sign on. */
    private function notifyCurrentSigner(EmsSurveyReport $report, string $message): void
    {
        $role = $report->currentApprovalRole();

        if ($role === null) {
            return;
        }

        Notification::notifyAboutEmsReport(
            $report,
            $this->roleHolders($role),
            $message,
            $role === User::ROLE_MEPEO_MANAGER ? $this->mepeoLink($report) : route('approvals.index', absolute: false)
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
