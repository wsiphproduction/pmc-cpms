<?php

namespace App\Support;

use App\Mail\NtpAwaitingReview;
use App\Models\AuditTrail;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRequest;
use App\Models\ProjectRfq;
use App\Models\RosterBreak;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     *
     * `$engineerId` is the project engineer the PMD Department Manager names
     * to run the work; it is only taken from that office's signature.
     */
    public function approveRequest(ProjectRequest $projectRequest, User $user, ?string $remarks = null, ?int $engineerId = null): bool
    {
        if (! $this->mayAct($projectRequest, $user)) {
            return false;
        }

        $step = $projectRequest->recordApproval($user, $remarks);
        $link = route('requests.show', $projectRequest->id, absolute: false);
        $label = $step->officeLabel();

        if ($step->role === User::ROLE_PMD_DEPT_MANAGER) {
            $projectRequest->update(['assigned_engineer_id' => $engineerId]);
        }

        if ($projectRequest->approvalChainComplete()) {
            $projectRequest->update(['status' => 'approved', 'status_before_hold' => null]);

            Notification::notify(
                $projectRequest->requester_id,
                "Project Request #{$projectRequest->request_no} has been fully approved.",
                $link
            );

            // The engineers are the ones who turn an approved request into a
            // project — all of them, unless one was assigned, in which case the
            // others can no longer see it and are left out.
            $this->notifyRoles(
                $projectRequest->assigned_engineer_id
                    ? [User::ROLE_ASSISTANT_MANAGER]
                    : [User::ROLE_ENGINEER, User::ROLE_ASSISTANT_MANAGER],
                "Project Request #{$projectRequest->request_no} is fully approved and ready to be registered as a project.",
                $link,
                $user
            );

            if ($projectRequest->assigned_engineer_id && $projectRequest->assigned_engineer_id !== $user->id) {
                Notification::notify(
                    $projectRequest->assigned_engineer_id,
                    "You were assigned as project engineer for Project Request #{$projectRequest->request_no}.",
                    $link
                );
            }

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
            "Project Request #{$projectRequest->request_no} was rejected by the " . $step->officeLabel() . ".{$reason}",
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
            "Project Request #{$projectRequest->request_no}, which you approved, was rejected by the " . $step->officeLabel() . ".{$reason}",
            $link
        );

        return true;
    }

    /**
     * Record a signature on an NTP. The last one — the division manager user
     * over the project's department — issues it, awarding the RFQ and
     * stamping the issue date.
     */
    public function approveNtp(ProjectNtp $ntp, User $user, ?string $remarks = null): bool
    {
        if (! $this->mayAct($ntp, $user)) {
            return false;
        }

        $step = $ntp->recordApproval($user, $remarks);
        $project = $ntp->project;
        $label = $step->officeLabel();

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
        $label = $step->officeLabel();

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

    /**
     * Call on whoever an open NTP is now waiting on — after its project names
     * a different signatory for the step it sits at.
     */
    public function notifyCurrentSigner(ProjectNtp $ntp): void
    {
        $this->notifyNextApprover(
            $ntp,
            "NTP {$ntp->ntp_no} for {$ntp->contractor_name} is awaiting your approval on project {$ntp->project->project_no}.",
            route('approvals.index', absolute: false)
        );
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
        $role = $record->currentApprovalRole();

        if ($role === null) {
            return;
        }

        // A step the project names somebody for goes to them alone (and to
        // their OIC while they are away), on whichever page they sign from.
        if ($record instanceof ProjectNtp && ($named = $record->project?->ntpSignatoryFor($role))) {
            $recipients = RosterBreak::activeOn()->where('user_id', $named->id)->where('role', $role)
                ->pluck('oic_user_id')
                ->prepend($named->id)
                ->unique();

            if ($named->signsFromApprovalsPortal()) {
                Notification::notify($recipients, $message, route('approvals.index', absolute: false));
            } else {
                Notification::notify($recipients, $message, route('ntp-reviews.index', absolute: false));
                $this->mailNtpReviewers($record, $recipients);
            }

            return;
        }

        // On a site a division manager user has taken, the Division Manager's
        // step goes to them, and they sign it from NTP Reviews.
        if ($record instanceof ProjectNtp && $role === User::ROLE_DIVISION_MANAGER
            && ($onSite = $record->project?->siteManagers() ?? collect())->isNotEmpty()) {
            Notification::notify($onSite->pluck('id'), $message, route('ntp-reviews.index', absolute: false));
            $this->mailNtpReviewers($record, $onSite->pluck('id'));

            return;
        }

        // An NTP's review steps are held per project, so only the people
        // behind that project's department (or its division) are called on —
        // and from their own review page, not the PMD portal.
        if ($record instanceof ProjectNtp && in_array($role, ProjectNtp::REVIEW_ROLES, true)) {
            $project = $record->project;
            $recipients = $role === User::ROLE_DIVISION_MANAGER_USER
                ? $project->divisionAudience()
                : $project->departmentAudience();

            Notification::notify($recipients, $message, route('ntp-reviews.index', absolute: false));
            $this->mailNtpReviewers($record, $recipients);

            return;
        }

        $this->notifyRoles([$role], $message, $link);
    }

    /**
     * Email the project-side reviewers an NTP now waits on, so they hear of it
     * without signing in. The bell reaches the whole department; the mail only
     * goes to those who can actually sign the step. A queue or mail outage
     * must not undo the signature already recorded.
     */
    private function mailNtpReviewers(ProjectNtp $ntp, Collection $recipients): void
    {
        User::whereIn('id', $recipients)
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $reviewer) => $ntp->awaitingApprovalFrom($reviewer))
            ->each(function (User $reviewer) use ($ntp) {
                try {
                    Mail::to($reviewer->email)->send(new NtpAwaitingReview($ntp, $reviewer));
                } catch (\Throwable $e) {
                    Log::error("NTP review email to {$reviewer->email} could not be queued for NTP #{$ntp->id}: " . $e->getMessage());
                }
            });
    }

    /**
     * Everyone holding any of the roles — plus whoever is covering one of
     * them as OIC today, since the item lands in their queue too.
     *
     * @param  array<int, string>  $roles
     */
    private function notifyRoles(array $roles, string $message, string $link, ?User $except = null): void
    {
        $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->pluck('id')
            ->merge(RosterBreak::oicIdsCovering($roles))
            ->unique()
            ->reject(fn ($id) => $except && (int) $id === $except->id);

        Notification::notify($recipients, $message, $link);
    }
}
