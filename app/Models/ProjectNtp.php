<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalChain;
use Illuminate\Database\Eloquent\Builder;
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
     * The department step belongs to *this project's* department user, and
     * the division step to the manager user of *that department's* division —
     * not to everyone holding those roles. The rest of the chain is held by a
     * single office each, so holding the role is enough.
     */
    public function approvalStepAuthorizes(ApprovalStep $step, User $user): bool
    {
        if (! in_array($step->role, self::REVIEW_ROLES, true)) {
            return $user->hasRole($step->role);
        }

        $project = $this->project;

        if ($project === null) {
            return false;
        }

        if ($step->role === User::ROLE_DIVISION_MANAGER_USER) {
            return $user->hasRole(User::ROLE_DIVISION_MANAGER_USER) && $project->belongsToDivisionOf($user);
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
     * own projects, a division manager user's whole division. Admins see all.
     */
    public function scopeReviewableBy(Builder $query, User $user): Builder
    {
        if ($user->hasRole(User::ROLE_ADMIN)) {
            return $query;
        }

        if ($user->hasRole(User::ROLE_DIVISION_MANAGER_USER)) {
            return $query->whereHas('project', fn (Builder $q) => $q->forDivisionUser($user));
        }

        return $query->whereHas('project', fn (Builder $q) => $q->forDepartmentUser($user));
    }

    /**
     * NTPs now sitting on this user's own review step — what the NTP Reviews
     * badge counts. Admins hold no step, so for them it is everything open.
     */
    public function scopeAwaitingReviewFrom(Builder $query, User $user): Builder
    {
        if ($user->hasRole(User::ROLE_ADMIN)) {
            return $query->approvalPending();
        }

        $role = collect(self::REVIEW_ROLES)->first(fn (string $role) => $user->hasRole($role));

        if ($role === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->awaitingRole($role)->reviewableBy($user);
    }
}
