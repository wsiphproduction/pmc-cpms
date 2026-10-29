<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who signs each step of a project's NTPs: the default holder of every step,
 * who the project may name instead, and who it has named. Backs the hub's NTP
 * Signatories menu; the chain itself asks `Project::ntpSignatoryFor`.
 */
class NtpSignatories
{
    /**
     * The roles a step may be handed to. Each office stays with its own
     * holders, except that the two division steps are open to either kind of
     * division manager, so one can stand in for the other on a project.
     */
    public const CANDIDATE_ROLES = [
        User::ROLE_PMD_ASST_MANAGER      => [User::ROLE_PMD_ASST_MANAGER],
        User::ROLE_PMD_DEPT_MANAGER      => [User::ROLE_PMD_DEPT_MANAGER],
        User::ROLE_DIVISION_MANAGER      => [User::ROLE_DIVISION_MANAGER, User::ROLE_DIVISION_MANAGER_USER],
        User::ROLE_REQUESTOR             => [User::ROLE_REQUESTOR],
        User::ROLE_DIVISION_MANAGER_USER => [User::ROLE_DIVISION_MANAGER_USER, User::ROLE_DIVISION_MANAGER],
    ];

    /** One row per step, in signing order, for the hub. */
    public static function forProject(Project $project): array
    {
        $project->loadMissing('ntpSignatories.user');

        return collect(ProjectNtp::APPROVAL_CHAIN)->values()->map(function (string $role, int $index) use ($project) {
            $named = $project->ntpSignatoryFor($role);

            return [
                'sequence'      => $index + 1,
                'role'          => $role,
                'role_label'    => User::roleLabel($role),
                'default_names' => self::defaultNames($project, $role),
                'user_id'       => $named?->id,
                'candidates'    => self::candidates($role)->map(fn (User $user) => [
                    'id'    => $user->id,
                    'label' => self::label($user),
                ])->values()->all(),
            ];
        })->all();
    }

    /**
     * Who holds the step when the project names nobody — the same people
     * `ProjectNtp::approvalStepAuthorizes` would let sign it.
     *
     * @return array<int, string>
     */
    public static function defaultNames(Project $project, string $role): array
    {
        $onSite = $role === User::ROLE_DIVISION_MANAGER ? $project->siteManagers() : collect();

        $ids = match (true) {
            $onSite->isNotEmpty() => $onSite->pluck('id'),
            $role === User::ROLE_DIVISION_MANAGER_USER => $project->divisionAudience(),
            $role === User::ROLE_REQUESTOR => collect([$project->projectRequest?->requester_id])
                ->filter()
                ->reject(fn ($id) => $project->projectRequest?->requester?->hasRole(User::ROLE_DEPT_SUPERVISOR))
                ->merge(User::role(User::ROLE_REQUESTOR)->whereIn('id', $project->departmentAudience())->pluck('id')),
            default => User::role($role)->pluck('id'),
        };

        return User::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }

    /** Everyone the project may name for the step. */
    public static function candidates(string $role): Collection
    {
        return User::role(self::CANDIDATE_ROLES[$role] ?? [$role])
            ->with('roles')
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'division']);
    }

    /** A name plus where the person sits, so namesakes can be told apart. */
    private static function label(User $user): string
    {
        $where = $user->department ?: $user->division;
        $role  = $user->primaryRole();

        return $user->name . ' — ' . User::roleLabel($role) . ($where ? " ({$where})" : '');
    }
}
