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
     * Requests a user may see. Department users see their own. Inside PMD
     * everyone sees everything, except that once the PMD Department Manager
     * assigns a request to an engineer, the other engineers no longer see it.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentUser()) {
            return $query->where('requester_id', $user->id);
        }

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
            return $this->requester_id === $user->id;
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
        return $step->role === User::ROLE_ENGINEER
            ? $user->hasRole(User::DELIVERY_ROLES)
            // Holding the role, or covering it as OIC during a roster break.
            : $user->actsAs($step->role);
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
