<?php

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRequest;
use App\Models\ProjectRfq;
use App\Models\User;

/**
 * Drives the sequential sign-off chains.
 *
 * Both chains are entered from two places — the department/engineer step from
 * their own screens, the PMD steps from the approvals portal — so the rules for
 * advancing a chain, and what the final signature actually does, live here
 * rather than in either controller.
 */
class ApprovalFlow
{
    /**
     * Record a signature on a project request and move it along. Returns false
     * when the user was not the one being waited on.
     */
    public function approveRequest(ProjectRequest $projectRequest, User $user, ?string $remarks = null): bool
    {
        if (! $this->mayAct($projectRequest, $user)) {
            return false;
        }

        $step = $projectRequest->recordApproval($user, $remarks);
        $link = route('requests.show', $projectRequest->id, absolute: false);
        $label = User::roleLabel($step->role);

        if ($projectRequest->approvalChainComplete()) {
            $projectRequest->update(['status' => 'approved', 'status_before_hold' => null]);

            Notification::notify(
                $projectRequest->requester_id,
                "Project Request #{$projectRequest->request_no} has been fully approved.",
                $link
            );

            // The engineers are the ones who turn an approved request into a project.
            $this->notifyRoles(
                [User::ROLE_ENGINEER, User::ROLE_ASSISTANT_MANAGER],
                "Project Request #{$projectRequest->request_no} is fully approved and ready to be registered as a project.",
                $link,
                $user
            );

            return true;
        }

        // Still climbing: park it in "in approval" and call on the next office.
        $projectRequest->update(['status' => 'in_approval', 'status_before_hold' => null]);

        Notification::notify(
            $projectRequest->requester_id,
            "Project Request #{$projectRequest->request_no} was approved by the {$label} and is now with the "
                . User::roleLabel($projectRequest->currentApprovalRole()) . '.',
            $link
        );

        $this->notifyNextApprover($projectRequest, "Project Request #{$projectRequest->request_no} is awaiting your approval.", route('approvals.index', absolute: false));

        return true;
    }

    public function rejectRequest(ProjectRequest $projectRequest, User $user, ?string $remarks = null): bool
    {
        if (! $this->mayAct($projectRequest, $user)) {
            return false;
        }

        $step = $projectRequest->recordRejection($user, $remarks);
        $projectRequest->update(['status' => 'rejected', 'status_before_hold' => null]);

        $reason = $remarks ? " Reason: {$remarks}" : '';
        $link = route('requests.show', $projectRequest->id, absolute: false);

        Notification::notify(
            $projectRequest->requester_id,
            "Project Request #{$projectRequest->request_no} was rejected by the " . User::roleLabel($step->role) . ".{$reason}",
            $link
        );

        // Tell everyone who already signed that the chain died downstream.
        $signatories = $projectRequest->approvals()
            ->where('status', 'approved')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->reject(fn ($id) => (int) $id === $user->id);

        Notification::notify(
            $signatories,
            "Project Request #{$projectRequest->request_no}, which you approved, was rejected by the " . User::roleLabel($step->role) . ".{$reason}",
            $link
        );

        return true;
    }

    /**
     * Record a signature on an NTP. The last one issues it — awarding the RFQ
     * and stamping the issue date.
     */
    public function approveNtp(ProjectNtp $ntp, User $user, ?string $remarks = null): bool
    {
        if (! $this->mayAct($ntp, $user)) {
            return false;
        }

        $step = $ntp->recordApproval($user, $remarks);
        $project = $ntp->project;
        $label = User::roleLabel($step->role);

        $ntp->update([
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        if (! $ntp->approvalChainComplete()) {
            AuditTrail::log(
                "NTP {$ntp->ntp_no} approved by the {$label}",
                $project,
                array_filter(['module' => 'NTP', 'type' => 'update', 'rfq_id' => $ntp->project_rfq_id])
            );

            $this->notifyNextApprover(
                $ntp,
                "NTP {$ntp->ntp_no} for {$ntp->contractor_name} is awaiting your approval on project {$project->project_no}.",
                route('approvals.index', absolute: false)
            );

            if ($ntp->created_by) {
                Notification::notify(
                    $ntp->created_by,
                    "NTP {$ntp->ntp_no} was approved by the {$label} and is now with the "
                        . User::roleLabel($ntp->currentApprovalRole()) . '.',
                    route('projects.hub.ntp', $project->id, absolute: false)
                );
            }

            return true;
        }

        $this->issueNtp($ntp, $project);

        return true;
    }

    public function rejectNtp(ProjectNtp $ntp, User $user, ?string $remarks = null): bool
    {
        if (! $this->mayAct($ntp, $user)) {
            return false;
        }

        $step = $ntp->recordRejection($user, $remarks);
        $label = User::roleLabel($step->role);

        $ntp->update([
            'status'         => 'rejected',
            'reviewed_by'    => $user->id,
            'reviewed_at'    => now(),
            'review_remarks' => $remarks,
        ]);

        AuditTrail::log(
            "NTP {$ntp->ntp_no} rejected by the {$label}",
            $ntp->project,
            array_filter(['module' => 'NTP', 'type' => 'update', 'rfq_id' => $ntp->project_rfq_id])
        );

        if ($ntp->created_by) {
            $reason = $remarks ? " Reason: {$remarks}" : '';
            Notification::notify(
                $ntp->created_by,
                "NTP {$ntp->ntp_no} was rejected by the {$label}.{$reason}",
                route('projects.hub.ntp', $ntp->project->id, absolute: false)
            );
        }

        return true;
    }

    /** The final signature: the NTP takes effect. */
    private function issueNtp(ProjectNtp $ntp, Project $project): void
    {
        $ntp->update([
            'status'      => 'issued',
            'issued_date' => now()->toDateString(),
            'issued_by'   => $ntp->created_by, // the engineer who prepared it
        ]);

        // Award the linked RFQ. The project budget is set manually via the
        // Project Cost field, so issuing an NTP no longer changes it.
        if ($ntp->project_rfq_id) {
            ProjectRfq::where('id', $ntp->project_rfq_id)->update(['status' => 'awarded']);
        }

        AuditTrail::log(
            "NTP {$ntp->ntp_no} fully approved and issued",
            $project,
            array_filter(['module' => 'NTP', 'type' => 'update', 'rfq_id' => $ntp->project_rfq_id])
        );

        if ($ntp->created_by) {
            Notification::notify(
                $ntp->created_by,
                "NTP {$ntp->ntp_no} completed its approval chain and has been issued.",
                route('projects.hub.ntp', $project->id, absolute: false)
            );
        }

        Notification::notify(
            $project->departmentAudience(),
            "NTP {$ntp->ntp_no} for {$ntp->contractor_name} has been issued on project {$project->project_no}.",
            route('projects.show', $project->id, absolute: false)
        );
    }

    /**
     * Admins may settle any step — they administer the whole flow — everyone
     * else must be the holder of the step being waited on.
     */
    private function mayAct(ProjectRequest|ProjectNtp $record, User $user): bool
    {
        // Nothing moves a record that is already settled. This guard comes
        // first because the backfill below would otherwise hand a legacy row —
        // an NTP issued before the chain existed, and so holding no steps — a
        // fresh chain, making an issued NTP rejectable and re-issuable.
        if ($this->isSettled($record)) {
            return false;
        }

        // A record raised before the chain existed has no steps; give it one on
        // first contact rather than refusing to let anybody act on it.
        $record->startApprovalChain();

        if ($record->currentApproval() === null) {
            return false;
        }

        return $user->hasRole(User::ROLE_ADMIN) || $record->awaitingApprovalFrom($user);
    }

    /** Whether the record has already reached a terminal state. */
    private function isSettled(ProjectRequest|ProjectNtp $record): bool
    {
        return $record instanceof ProjectNtp
            ? in_array($record->status, ['issued', 'rejected'], true)
            : in_array($record->status, ['approved', 'rejected'], true);
    }

    private function notifyNextApprover(ProjectRequest|ProjectNtp $record, string $message, string $link): void
    {
        if ($role = $record->currentApprovalRole()) {
            $this->notifyRoles([$role], $message, $link);
        }
    }

    /** @param  array<int, string>  $roles */
    private function notifyRoles(array $roles, string $message, string $link, ?User $except = null): void
    {
        $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->pluck('id')
            ->reject(fn ($id) => $except && (int) $id === $except->id);

        Notification::notify($recipients, $message, $link);
    }
}
