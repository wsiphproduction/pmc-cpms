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
        if ($user->hasRole(['approver', 'admin'])) {
            return true;
        }

        return $project->projectRequest?->requester_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['approver', 'admin']);
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('approver') && $project->created_by === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
