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
        if ($user->hasRole(User::INTERNAL_ROLES)) {
            return true;
        }

        return $projectRequest->requester_id === $user->id;
    }

    public function create(User $user): bool
    {
        // The PMD/division approval roles review requests; they do not raise them.
        return ! $user->isApprovalRole();
    }

    public function update(User $user, ProjectRequest $projectRequest): bool
    {
        // Editing a request is the department user's (requester's) responsibility,
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

    public function decide(User $user, ProjectRequest $projectRequest): bool
    {
        if (! $user->hasRole(User::DELIVERY_ROLES)) {
            return false;
        }

        if (! in_array($projectRequest->status, ['pending', 'hold'], true)) {
            return false;
        }

        // The engineer holds the first signature; once it is given, the request
        // belongs to PMD and is settled from the approvals portal instead. A
        // request predating the chain has no steps and stays decidable here.
        return ! $projectRequest->approvalChainStarted()
            || $projectRequest->currentApprovalRole() === User::ROLE_ENGINEER;
    }
}
