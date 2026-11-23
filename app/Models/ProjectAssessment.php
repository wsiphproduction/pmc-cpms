<?php

namespace App\Models;

use App\Models\Concerns\HasApprovalChain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One Operations Hub document with an electronic approver process: a Safety
 * Risk Assessment, Geological Assessment or Billing Validation Report, or the
 * sign-off of a completion certificate. `type` picks its definition — form
 * and signing chain — from config/project_assessments.php.
 *
 * The engineer prepares and submits it; the chain signs in order; any signer
 * may return it, and resubmission restarts the chain.
 */
class ProjectAssessment extends Model
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

    /** The chain varies by type — see approvalChainRoles(). */
    public const APPROVAL_CHAIN = [];

    protected $fillable = [
        'project_id',
        'type',
        'control_no',
        'title',
        'data',
        'status',
        'submitted_at',
        'approved_at',
        'return_remarks',
        'created_by',
    ];

    protected $casts = [
        'data'         => 'array',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'created_by'   => 'integer',
    ];

    public static function types(): array
    {
        return config('project_assessments.types');
    }

    public static function isType(string $type): bool
    {
        return isset(self::types()[$type]);
    }

    public function definition(): array
    {
        return self::types()[$this->type] ?? [];
    }

    public function approvalChainRoles(): array
    {
        return $this->definition()['chain'] ?? [];
    }

    /** Signs off the project's completion details rather than a form of its own. */
    public function isCompletionSignoff(): bool
    {
        return ($this->definition()['source'] ?? null) === 'completion';
    }

    public function label(): string
    {
        return $this->definition()['label'] ?? $this->type;
    }

    public function signatureVerb(string $role): string
    {
        return $this->definition()['verbs'][$role] ?? 'approved';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'reference', 'reference_type', 'reference_id')->oldest('id');
    }

    /** The next control number for a type, <prefix>-<year>-<seq>, counting deleted ones too. */
    public static function nextControlNo(string $type): string
    {
        $prefix = (self::types()[$type]['prefix'] ?? 'DOC') . '-' . now()->format('Y') . '-';
        $last = static::withTrashed()->where('control_no', 'like', $prefix . '%')->max('control_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_FOR_APPROVAL && $role = $this->currentApprovalRole()) {
            return 'With ' . User::roleLabel($role);
        }

        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }
}
