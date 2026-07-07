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
        if ($user->hasRole(['approver', 'admin'])) {
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
        // and only while it is still pending. Approvers/admins review, give
        // technical feedback and decide — they do not edit the request content.
        return $projectRequest->requester_id === $user->id && $projectRequest->status === 'pending';
    }

    public function delete(User $user, ProjectRequest $projectRequest): bool
    {
        return $this->update($user, $projectRequest);
    }

    public function decide(User $user, ProjectRequest $projectRequest): bool
    {
        return $user->hasRole(['approver', 'admin']) && $projectRequest->status === 'pending';
    }
}
