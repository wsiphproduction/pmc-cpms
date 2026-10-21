<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectNtp extends Model
{
    use SoftDeletes, HasApprovalChain;

    /**
     * Sign-off order. PMD signs first, up through the Division Manager; the
     * work then goes to the side it is for — the owning department's user and
     * lastly the manager of that department's division. The NTP is only
     * issued (and its RFQ awarded) once that final signature is in.
     */
    public const APPROVAL_CHAIN = [
        User::ROLE_PMD_ASST_MANAGER,
        User::ROLE_PMD_DEPT_MANAGER,
        User::ROLE_DIVISION_MANAGER,
        User::ROLE_REQUESTOR,
        User::ROLE_DIVISION_MANAGER_USER,
    ];

    /**
     * The steps held per project rather than by a single office: each is
     * settled from the NTP Reviews page by whoever is tied to the project's
     * department (or its division), not from the PMD approvals portal.
     */
    public const REVIEW_ROLES = [
        User::ROLE_REQUESTOR,
        User::ROLE_DIVISION_MANAGER_USER,
    ];

    protected $fillable = [
        'project_id',
        'ntp_no',
        'contractor_name',
        'project_rfq_id',
        'baseline_start',
        'baseline_end',
        'approved_cost',
        'status',
        'issued_date',
        'issued_by',
        'vendor_notified_at',
        'reviewed_by',
        'reviewed_at',
        'review_remarks',
        'created_by',
    ];

    protected $casts = [
        'baseline_start' => 'date',
        'baseline_end'   => 'date',
        'issued_date'    => 'date',
        'reviewed_at'    => 'datetime',
        'vendor_notified_at' => 'datetime',
        'approved_cost'  => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProjectRfq::class, 'project_rfq_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A step the project names somebody for is theirs alone — or their OIC's
     * while they are away. On a site a division manager user has taken, the
     * Division Manager's step is theirs too. Otherwise the department step belongs to *this
     * project's* department user, and the division step to the manager user
     * of *that department's* division — not to everyone holding those roles.
     * The rest of the chain is held by a single office each, so holding the
     * role is enough.
     */
    public function approvalStepAuthorizes(ApprovalStep $step, User $user): bool
    {
        $project = $this->project;

        if ($named = $project?->ntpSignatoryFor($step->role)) {
            return $user->id === $named->id
                || RosterBreak::coverFor($user, $step->role)?->user_id === $named->id;
        }

        // On a site a division manager user has taken, the Division Manager's
        // step is theirs rather than the office holder's.
        if ($step->role === User::ROLE_DIVISION_MANAGER && $project
            && ($onSite = $project->siteManagers())->isNotEmpty()) {
            return $onSite->contains('id', $user->id);
        }

        if (! in_array($step->role, self::REVIEW_ROLES, true)) {
            return $user->actsAs($step->role);
        }

        if ($project === null) {
            return false;
        }

        // The manager who has taken the project's site, else the owning division's.
        if ($step->role === User::ROLE_DIVISION_MANAGER_USER) {
            return $user->hasRole(User::ROLE_DIVISION_MANAGER_USER)
                && $project->divisionAudience()->contains($user->id);
        }

        // The person who raised the request signs for it, whatever else they
        // hold. Otherwise the step falls to the owning department's users —
        // and only its department users: matching on department name alone let
        // any account carrying that department value sign for it.
        return $project->projectRequest?->requester_id === $user->id
            || ($user->hasRole(User::ROLE_REQUESTOR) && $project->belongsToDepartmentOf($user));
    }

    /**
     * NTPs this user may follow on the NTP Reviews page: a department user's
     * own projects, a division manager user's whole division plus the sites
     * they have taken, and any project that names them to sign a step.
     * Admins see all.
     */
    public function scopeReviewableBy(Builder $query, User $user): Builder
    {
        if ($user->hasRole(User::ROLE_ADMIN)) {
            return $query;
        }

        $own = $user->hasRole(User::ROLE_DIVISION_MANAGER_USER)
            ? fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->forDivisionUser($user)
                ->when(! empty($user->sites), fn (Builder $onSite) => $onSite->orWhereIn('site', $user->sites)))
            : fn (Builder $q) => $q->forDepartmentUser($user);

        return $query->where(fn (Builder $q) => $q
            ->whereHas('project', $own)
            ->orWhereHas('project.ntpSignatories', fn (Builder $s) => $s->where('user_id', $user->id)));
    }

    /**
     * Narrows to NTPs that may be waiting on this user: the current step is
     * one of their roles, one their project names them for, or the Division
     * Manager's step on a site they have taken. A prefilter only —
     * `awaitingApprovalFrom` settles each record.
     *
     * @param  array<int, string>  $roles
     */
    public function scopeSignableBy(Builder $query, User $user, array $roles): Builder
    {
        $takesSites = $user->hasRole(User::ROLE_DIVISION_MANAGER_USER) && ! empty($user->sites);

        return $query
            ->approvalPending()
            ->whereHas('project')
            ->where(fn (Builder $q) => $q
                ->awaitingAnyRole($roles)
                ->orWhereHas('project.ntpSignatories', fn (Builder $s) => $s->where('user_id', $user->id))
                ->when($takesSites, fn (Builder $onSite) => $onSite->orWhere(fn (Builder $inner) => $inner
                    ->awaitingRole(User::ROLE_DIVISION_MANAGER)
                    ->whereHas('project', fn (Builder $p) => $p->whereIn('site', $user->sites)))))
            ->with(['project.ntpSignatories.user', 'project.projectRequest', 'approvals']);
    }

    /**
     * NTPs now sitting on this user's own review step — what the NTP Reviews
     * badge counts. Admins hold no step, so for them it is everything open.
     */
    public static function awaitingReviewFrom(User $user): Collection
    {
        if ($user->hasRole(User::ROLE_ADMIN)) {
            return static::approvalPending()->get();
        }

        $roles = array_values(array_filter(self::REVIEW_ROLES, fn (string $role) => $user->hasRole($role)));

        return static::signableBy($user, $roles)
            ->reviewableBy($user)
            ->get()
            ->filter(fn (self $ntp) => $ntp->awaitingApprovalFrom($user))
            ->values();
    }
}
