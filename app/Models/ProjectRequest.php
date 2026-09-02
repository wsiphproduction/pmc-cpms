<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalChain;
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
        'opex'         => 'boolean',
        'capex'        => 'boolean',
        'for_budgeting'=> 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
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
            : $user->hasRole($step->role);
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
