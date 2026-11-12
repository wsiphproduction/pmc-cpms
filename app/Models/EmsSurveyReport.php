<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An EMS Survey Report — the Environmental Impact Assessment, PMD-PRJ-FRM-19.
 *
 * The project engineer prepares the assessment on their project, names the
 * MEPEO representative, and submits it for review. The PMD Assistant Manager
 * reviews it and the PMD Department Manager notes it; it then reaches MEPEO,
 * where the representative fills in MEPEO's Status and Remarks and verifies
 * it, and the MEPEO Manager approves it. Any of them may send it back to the
 * engineer to revise, which restarts the sign-off on resubmission.
 *
 * `submitted_at` is when the engineer submitted it; `sent_at` when it reached
 * MEPEO (the PMD Manager's note).
 */
class EmsSurveyReport extends Model
{
    use SoftDeletes, HasApprovalChain;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FOR_APPROVAL = 'for_approval';
    public const STATUS_APPROVED = 'approved';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT        => 'Draft',
        self::STATUS_FOR_APPROVAL => 'For Approval',
        self::STATUS_APPROVED     => 'Approved',
    ];

    public const APPROVAL_CHAIN = [
        User::ROLE_PMD_ASST_MANAGER,
        User::ROLE_PMD_DEPT_MANAGER,
        User::ROLE_MEPEO_REP,
        User::ROLE_MEPEO_MANAGER,
    ];

    /** The steps signed at MEPEO, after PMD. */
    public const MEPEO_ROLES = [User::ROLE_MEPEO_REP, User::ROLE_MEPEO_MANAGER];

    /** What each signature means on the form, by the role giving it. */
    public const SIGNATURE_VERBS = [
        User::ROLE_PMD_ASST_MANAGER => 'reviewed',
        User::ROLE_PMD_DEPT_MANAGER => 'noted',
        User::ROLE_MEPEO_REP        => 'verified',
        User::ROLE_MEPEO_MANAGER    => 'approved',
    ];

    protected $fillable = [
        'project_id',
        'control_no',
        'status',
        'date_requested',
        'location',
        'contractor',
        'target_start',
        'target_end',
        'major_activities',
        'major_activities_other',
        'other_scope',
        'aspects',
        'aspects_other',
        'impacts',
        'mitigation',
        'mepeo_status',
        'remarks',
        'mepeo_rep_id',
        'sent_at',
        'submitted_at',
        'return_remarks',
        'created_by',
    ];

    protected $casts = [
        'date_requested'   => 'date',
        'target_start'     => 'date',
        'target_end'       => 'date',
        'major_activities' => 'array',
        'aspects'          => 'array',
        'mepeo_rep_id'     => 'integer',
        'created_by'       => 'integer',
        'sent_at'          => 'datetime',
        'submitted_at'     => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mepeoRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mepeo_rep_id');
    }

    /** Supporting documents, oldest first, the way they were added. */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'reference', 'reference_type', 'reference_id')->oldest('id');
    }

    /** The next control number, EMS-<year>-<seq>, counting deleted reports too. */
    public static function nextControlNo(): string
    {
        $prefix = 'EMS-' . now()->format('Y') . '-';
        $last = static::withTrashed()->where('control_no', 'like', $prefix . '%')->max('control_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** In sign-off, says which side has it: "For PMD Review" or "With MEPEO". */
    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_FOR_APPROVAL && $role = $this->currentApprovalRole()) {
            return in_array($role, self::MEPEO_ROLES, true) ? 'With MEPEO' : 'For PMD Review';
        }

        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Still a draft: the engineer may change it, submit it or delete it. */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** True while the sign-off has not yet passed the representative's step. */
    public function awaitsRepresentative(): bool
    {
        if ($this->status !== self::STATUS_FOR_APPROVAL) {
            return false;
        }

        $step = $this->approvalSteps()->firstWhere('role', User::ROLE_MEPEO_REP);

        return $step !== null && $step->status === 'pending';
    }

    /** The representative fills in MEPEO's part while the chain waits on their verification. */
    public function editableBy(User $user): bool
    {
        return $this->status === self::STATUS_FOR_APPROVAL
            && $this->currentApprovalRole() === User::ROLE_MEPEO_REP
            && ((int) $this->mepeo_rep_id === $user->id || $user->hasRole(User::ROLE_ADMIN));
    }

    /**
     * The verification step belongs to the representative the engineer
     * named, not to every holder of the role.
     */
    public function approvalStepAuthorizes(ApprovalStep $step, User $user): bool
    {
        if ($step->role === User::ROLE_MEPEO_REP) {
            return (int) $this->mepeo_rep_id === $user->id;
        }

        return $user->actsAs($step->role);
    }

    /**
     * What a MEPEO user may open, once PMD has passed it on: a representative,
     * the reports naming them; the manager, every report that reached MEPEO.
     */
    public function scopeVisibleToMepeo(Builder $query, User $user): Builder
    {
        $query->whereNotNull('sent_at');

        if ($user->hasRole([User::ROLE_MEPEO_MANAGER, User::ROLE_ADMIN])) {
            return $query;
        }

        return $query->where('mepeo_rep_id', $user->id);
    }

    public function isVisibleToMepeo(User $user): bool
    {
        return static::query()->visibleToMepeo($user)->whereKey($this->id)->exists();
    }
}
