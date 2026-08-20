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
        if ($user->hasRole(['approver', 'assistant_manager', 'admin'])) {
            return true;
        }

        // A sub-project carries no request of its own — it is spawned from an NTP
        // on its parent — so ownership resolves against the parent. The requester
        // who asked for the parent owns everything under it. Nesting is one level
        // deep by design: sub-projects have no RFQ/NTP sections to spawn from.
        $owningRequest = $project->projectRequest ?? $project->parent?->projectRequest;

        return $owningRequest?->requester_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['approver', 'assistant_manager', 'admin']);
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
