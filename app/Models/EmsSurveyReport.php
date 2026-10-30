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
 * The project engineer raises it blank on their project and sends it to a
 * MEPEO representative, who fills in the assessment and submits it. The
 * MEPEO Manager then approves it, the PMD Assistant Manager reviews it and
 * the PMD Department Manager notes it, as the form's signature blocks read.
 * Any of them may send it back to the representative to revise.
 */
class EmsSurveyReport extends Model
{
    use SoftDeletes, HasApprovalChain;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_WITH_MEPEO = 'with_mepeo';
    public const STATUS_FOR_APPROVAL = 'for_approval';
    public const STATUS_APPROVED = 'approved';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT        => 'Draft',
        self::STATUS_WITH_MEPEO   => 'With MEPEO',
        self::STATUS_FOR_APPROVAL => 'For Approval',
        self::STATUS_APPROVED     => 'Approved',
    ];

    public const APPROVAL_CHAIN = [
        User::ROLE_MEPEO_MANAGER,
        User::ROLE_PMD_ASST_MANAGER,
        User::ROLE_PMD_DEPT_MANAGER,
    ];

    /** What each signature means on the form, by the role giving it. */
    public const SIGNATURE_VERBS = [
        User::ROLE_MEPEO_MANAGER    => 'approved',
        User::ROLE_PMD_ASST_MANAGER => 'reviewed',
        User::ROLE_PMD_DEPT_MANAGER => 'noted',
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

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Still before MEPEO's submission: the engineer may change it, resend or delete it. */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_WITH_MEPEO], true);
    }

    /** The representative may fill it in while it sits with them. */
    public function editableBy(User $user): bool
    {
        return $this->status === self::STATUS_WITH_MEPEO
            && ((int) $this->mepeo_rep_id === $user->id || $user->hasRole(User::ROLE_ADMIN));
    }

    /**
     * What a MEPEO user may open: a representative, the reports sent to them;
     * the manager, every report that has reached MEPEO.
     */
    public function scopeVisibleToMepeo(Builder $query, User $user): Builder
    {
        if ($user->hasRole([User::ROLE_MEPEO_MANAGER, User::ROLE_ADMIN])) {
            return $query->where('status', '!=', self::STATUS_DRAFT);
        }

        return $query->where('mepeo_rep_id', $user->id);
    }

    public function isVisibleToMepeo(User $user): bool
    {
        return static::query()->visibleToMepeo($user)->whereKey($this->id)->exists();
    }
}
