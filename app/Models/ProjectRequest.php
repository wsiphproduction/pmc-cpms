<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class ProjectRequest extends Model
{
    use SoftDeletes, HasFactory, HasApprovalChain;

    /**
     * Sign-off order. The project engineer reviews and endorses first, then it
     * rises through PMD. A request only reaches "approved" — and so becomes
     * convertible to a project — once the last signature is in.
     */
    public const APPROVAL_CHAIN = [
        User::ROLE_ENGINEER,
        User::ROLE_PMD_ASST_MANAGER,
        User::ROLE_PMD_DEPT_MANAGER,
    ];

    /**
     * A request a Dept Asst Manager/Supervisor raises is reviewed by their
     * department user (the department manager) before the engineer sees it.
     * That step is numbered 0, ahead of the chain, so the chain's own steps
     * keep the sequence `awaitingRole` finds them by — and a request raised
     * without it starts at the engineer as it always has.
     */
    public const DEPARTMENT_REVIEW_SEQUENCE = 0;

    protected $fillable = [
        'request_no',
        'title',
        'job_type',
        'description',
        'requester_id',
        'assigned_engineer_id',
        'job_location',
        'costcode',
        'opex',
        'capex',
        'for_budgeting',
        'status',
        'status_before_hold',
    ];

    protected $casts = [
        'requester_id' => 'integer',
        'assigned_engineer_id' => 'integer',
        'opex'        => 'boolean',
        'capex'        => 'boolean',
        'for_budgeting'=> 'boolean',
    ];

    /**
     * Titles are kept in capitals. Requesters type them in any case, and PMD
     * was retyping each one by hand; the project raised from the request
     * copies this title too.
     */
    protected function title(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtoupper(trim($value)));
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** The engineer the PMD Department Manager named to run it, if any. */
    public function assignedEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_engineer_id');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'reference', 'reference_type', 'reference_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'reference', 'reference_type', 'reference_id');
    }

    public function technicalFeedback(): HasMany
    {
        return $this->hasMany(TechnicalFeedback::class);
    }

    public function auditTrails(): MorphMany
    {
        return $this->morphMany(AuditTrail::class, 'reference', 'reference_type', 'reference_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /**
     * Requests a user may see. Department users see their own, and a
     * department user also sees those their department's supervisors raise.
     * Inside PMD everyone sees everything once it is past the department's
     * review — admins even before — except that once the PMD Department
     * Manager assigns a request to an engineer, the other engineers no longer
     * see it.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentUser()) {
            return $query->where(fn (Builder $q) => $q
                ->where('requester_id', $user->id)
                ->when($user->hasRole(User::ROLE_REQUESTOR) && filled($user->department), fn (Builder $q) => $q
                    ->orWhereHas('requester', fn (Builder $r) => $r
                        ->where('department', $user->department)
                        ->whereHas('roles', fn (Builder $role) => $role->where('name', User::ROLE_DEPT_SUPERVISOR)))));
        }

        if ($user->isAdmin()) {
            return $query;
        }

        $query->clearedDepartmentReview();

        if ($user->hasRole(User::ROLE_ENGINEER)) {
            return $query->where(fn (Builder $q) => $q
                ->whereNull('assigned_engineer_id')
                ->orWhere('assigned_engineer_id', $user->id)
                ->orWhere('requester_id', $user->id));
        }

        return $query;
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isDepartmentUser()) {
            return $this->requester_id === $user->id || $this->raisedBySupervisorOf($user);
        }

        if (! $user->isAdmin() && ! $this->clearedDepartmentReview()) {
            return false;
        }

        if ($user->hasRole(User::ROLE_ENGINEER)) {
            return $this->assigned_engineer_id === null
                || $this->assigned_engineer_id === $user->id
                || $this->requester_id === $user->id;
        }

        return true;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /** Endorsed by the engineer and still climbing the PMD chain. */
    /**
     * The first step belongs to project delivery, not to the "approver" role
     * alone: assistant managers have always been able to settle a request, and
     * ProjectRequestPolicy::decide still shows them the buttons. Without this
     * they saw Approve/Reject and got a 403 on click.
     */
    public function approvalStepAuthorizes(ApprovalStep $step, User $user): bool
    {
        // The department's review is held by the supervisor's own department
        // users, not by everyone carrying the role.
        if ($step->role === User::ROLE_REQUESTOR) {
            return $this->raisedBySupervisorOf($user);
        }

        // Once an engineer is assigned, the other engineers can no longer see
        // the request, and so cannot endorse it either.
        return $step->role === User::ROLE_ENGINEER
            ? $user->hasRole(User::DELIVERY_ROLES) && $this->isVisibleTo($user)
            // Holding the role, or covering it as OIC during a roster break.
            : $user->actsAs($step->role);
    }

    // ── Department review ─────────────────────────────────────────────────

    /** Put the department's review in front of the chain. Call after `startApprovalChain`. */
    public function startDepartmentReview(): void
    {
        if ($this->approvals()->where('sequence', self::DEPARTMENT_REVIEW_SEQUENCE)->exists()) {
            return;
        }

        $this->approvals()->create([
            'role'     => User::ROLE_REQUESTOR,
            'sequence' => self::DEPARTMENT_REVIEW_SEQUENCE,
            'status'   => 'pending',
        ]);

        $this->unsetRelation('approvals');
    }

    /** Still with the department, waiting on its review. */
    public function awaitingDepartmentReview(): bool
    {
        return $this->currentApproval()?->sequence === self::DEPARTMENT_REVIEW_SEQUENCE;
    }

    /** Raised without a department review, or the department has approved it. */
    public function clearedDepartmentReview(): bool
    {
        return ! $this->approvalSteps()->contains(fn (ApprovalStep $step) => $step->sequence === self::DEPARTMENT_REVIEW_SEQUENCE
            && $step->status !== 'approved');
    }

    public function scopeClearedDepartmentReview(Builder $query): Builder
    {
        return $query->whereDoesntHave('approvals', fn (Builder $q) => $q
            ->where('sequence', self::DEPARTMENT_REVIEW_SEQUENCE)
            ->where('status', '!=', 'approved'));
    }

    /**
     * Whether the user is a department user over the supervisor who raised
     * this request — the ones who review it, and who see it on their list.
     */
    public function raisedBySupervisorOf(User $user): bool
    {
        $requester = $this->requester;

        return $user->hasRole(User::ROLE_REQUESTOR)
            && filled($user->department)
            && $requester !== null
            && $requester->id !== $user->id
            && $requester->hasRole(User::ROLE_DEPT_SUPERVISOR)
            && strcasecmp((string) $requester->department, (string) $user->department) === 0;
    }

    /** Supervisors' requests waiting on this department user's review — the Requests badge. */
    public function scopeAwaitingDepartmentReviewBy(Builder $query, User $user): Builder
    {
        if (! $user->hasRole(User::ROLE_REQUESTOR)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->visibleTo($user)
            ->where('requester_id', '!=', $user->id)
            ->where('status', 'pending')
            ->whereHas('approvals', fn (Builder $q) => $q
                ->where('sequence', self::DEPARTMENT_REVIEW_SEQUENCE)
                ->where('status', 'pending'));
    }

    /** The department users who review this request. */
    public function departmentReviewers(): Collection
    {
        $department = $this->requester?->department;

        if (blank($department)) {
            return collect();
        }

        return User::where('department', $department)
            ->where('id', '!=', $this->requester_id)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', User::ROLE_REQUESTOR))
            ->pluck('id');
    }

    /**
     * Who hears of the requester's changes: the department users while the
     * request is with them, the engineers once it has reached PMD.
     */
    public function reviewAudience(): Collection
    {
        if ($this->awaitingDepartmentReview()) {
            return $this->departmentReviewers();
        }

        return User::whereHas('roles', fn (Builder $q) => $q->whereIn('name', [User::ROLE_ENGINEER, User::ROLE_ASSISTANT_MANAGER]))
            ->pluck('id');
    }

    public function scopeInApproval($query)
    {
        return $query->where('status', 'in_approval');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }
}
