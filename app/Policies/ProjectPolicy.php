<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->hasRole(User::INTERNAL_ROLES)) {
            return true;
        }

        // A sub-project carries no request of its own, so ownership resolves
        // against the tree it hangs from: the requester who asked for the root
        // owns everything under it, however deep it is nested.
        $owningRequest = $project->projectRequest ?? $project->rootAncestor()->projectRequest;

        if ($owningRequest?->requester_id === $user->id) {
            return true;
        }

        // A project an engineer registered directly has no request, and so no
        // requester — the owning department sees it by department instead.
        return $project->belongsToDepartmentOf($user);
    }

    public function create(User $user): bool
    {
        // The PMD/division approval roles have a read-only view of projects.
        return $user->hasRole(User::DELIVERY_ROLES);
    }

    public function update(User $user, Project $project): bool
    {
        // Admins manage everything. Assistant managers cover for project
        // engineers — they can take over any project (e.g. when the assigned
        // engineer is absent). A project engineer manages only their own.
        if ($user->hasRole(['admin', 'assistant_manager'])) {
            return true;
        }

        return $user->hasRole('approver') && $project->created_by === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /**
     * Billing status may be changed by the assigned project manager or the
     * project engineer who created the project. Admins and assistant managers
     * administer / cover for engineers, so they retain the ability too.
     */
    public function manageBillingStatus(User $user, Project $project): bool
    {
        if ($user->hasRole(['admin', 'assistant_manager'])) {
            return true;
        }

        return (int) $project->created_by === (int) $user->id
            || ((int) $project->project_manager_id === (int) $user->id && $project->project_manager_id !== null);
    }
}
