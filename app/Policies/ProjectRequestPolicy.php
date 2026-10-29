<?php

namespace App\Policies;

use App\Models\ProjectRequest;
use App\Models\User;

class ProjectRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProjectRequest $projectRequest): bool
    {
        return $projectRequest->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        // The PMD/division approval roles review requests; they do not raise them.
        return ! $user->isApprovalRole();
    }

    public function update(User $user, ProjectRequest $projectRequest): bool
    {
        // Editing a request is the requester's responsibility,
        // while it is still awaiting a decision — that is, "pending" (For Approval)
        // or "hold" (an engineer commented and the requester must respond).
        // Approvers/admins review, give technical feedback and decide — they do
        // not edit the request content.
        return $projectRequest->requester_id === $user->id
            && in_array($projectRequest->status, ['pending', 'hold'], true);
    }

    public function delete(User $user, ProjectRequest $projectRequest): bool
    {
        return $this->update($user, $projectRequest);
    }

    /**
     * The PMD Department Manager names the engineer who will run the work.
     * They need not wait for the request to reach their signature: any time
     * after it enters PMD and before it is registered as a project will do.
     */
    public function assignEngineer(User $user, ProjectRequest $projectRequest): bool
    {
        if (! $user->isAdmin() && ! $user->actsAs(User::ROLE_PMD_DEPT_MANAGER)) {
            return false;
        }

        return in_array($projectRequest->status, ['pending', 'hold', 'in_approval', 'approved'], true)
            && $projectRequest->clearedDepartmentReview()
            && ! $projectRequest->project()->exists();
    }

    public function decide(User $user, ProjectRequest $projectRequest): bool
    {
        if (! in_array($projectRequest->status, ['pending', 'hold'], true)) {
            return false;
        }

        // A request a supervisor raised waits on their department user first;
        // nobody in PMD settles it until that review is in.
        if ($projectRequest->awaitingDepartmentReview()) {
            return $user->isAdmin() || $projectRequest->awaitingApprovalFrom($user);
        }

        if (! $user->hasRole(User::DELIVERY_ROLES)) {
            return false;
        }

        // The engineer holds the first signature; once it is given, the request
        // belongs to PMD and is settled from the approvals portal instead. A
        // request predating the chain has no steps and stays decidable here.
        return ! $projectRequest->approvalChainStarted()
            || $projectRequest->currentApprovalRole() === User::ROLE_ENGINEER;
    }
}
