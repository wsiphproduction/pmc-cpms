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
        if ($user->hasRole(['approver', 'assistant_manager', 'admin'])) {
            return true;
        }

        return $projectRequest->requester_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
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
        // Approvers/admins can approve or reject while the request is still open
        // for a decision — either awaiting approval or on hold.
        return $user->hasRole(['approver', 'assistant_manager', 'admin'])
            && in_array($projectRequest->status, ['pending', 'hold'], true);
    }
}
