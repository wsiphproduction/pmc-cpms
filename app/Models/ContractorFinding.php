<?php

namespace App\Models;

use App\Support\ContractorEvaluationScoring as Scoring;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A non-compliance record (NCR): a deficiency found in a contractor's work,
 * the corrective action required, the contractor's response, and its closure.
 *
 * Open → Responded (the contractor answered) → Closed (PMD verified the
 * corrective action). An open or responded record past its target date is
 * overdue. A closed record can be reopened.
 */
class ContractorFinding extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN = 'open';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_CLOSED = 'closed';

    public const STATUS_LABELS = [
        self::STATUS_OPEN      => 'Open',
        self::STATUS_RESPONDED => 'Responded',
        self::STATUS_CLOSED    => 'Closed',
    ];

    /** Attachment `type`s for each stage of the record. */
    public const FILE_EVIDENCE = 'finding_evidence';
    public const FILE_RESPONSE = 'finding_response';
    public const FILE_CLOSURE = 'finding_closure';

    protected $fillable = [
        'project_id',
        'control_no',
        'contractor_evaluation_id',
        'category',
        'contractor_name',
        'supplier_id',
        'deficiency',
        'date_identified',
        'corrective_action',
        'target_date',
        'status',
        'contractor_response',
        'responded_at',
        'response_recorded_by',
        'closure_date',
        'closure_remarks',
        'closed_by',
        'portal_token',
        'notified_email',
        'notified_at',
        'created_by',
    ];

    protected $casts = [
        'date_identified' => 'date',
        'target_date'     => 'date',
        'closure_date'    => 'date',
        'responded_at'    => 'datetime',
        'notified_at'     => 'datetime',
        'supplier_id'     => 'integer',
        'contractor_evaluation_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $finding) {
            $finding->portal_token ??= Str::random(48);
            $finding->control_no ??= self::nextControlNo();
            $finding->supplier_id ??= ContractorEvaluation::supplierIdFor((string) $finding->contractor_name);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ContractorEvaluation::class, 'contractor_evaluation_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function responseRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'response_recorded_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'reference', 'reference_type', 'reference_id')->oldest('id');
    }

    /** The next control number, NCR-<year>-<seq>, counting deleted records too. */
    public static function nextControlNo(): string
    {
        $prefix = 'NCR-' . now()->format('Y') . '-';
        $last = static::withTrashed()->where('control_no', 'like', $prefix . '%')->max('control_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /** Past its target date and not yet closed. */
    public function isOverdue(): bool
    {
        return ! $this->isClosed() && $this->target_date !== null && $this->target_date->lt(today());
    }

    public function statusLabel(): string
    {
        return $this->isOverdue() ? 'Overdue' : (self::STATUS_LABELS[$this->status] ?? ucfirst($this->status));
    }

    public function categoryLabel(): ?string
    {
        $definition = $this->category ? (Scoring::categories()[$this->category] ?? null) : null;

        return $definition ? "{$definition['letter']}. {$definition['title']}" : null;
    }

    /** Days from identification to closure, or to today while still open. */
    public function daysOpen(): int
    {
        return (int) $this->date_identified->diffInDays($this->closure_date ?? today());
    }

    public function portalUrl(): string
    {
        return route('contractor-finding.show', $this->portal_token);
    }
}
