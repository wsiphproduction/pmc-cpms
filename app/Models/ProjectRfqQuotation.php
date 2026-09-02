<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One quotation offered against an RFQ. A vendor may submit several (an
 * original, a revision, a best-and-final); exactly one is flagged `is_final`
 * and that is the offer the project awards, prints and issues the NTP from.
 */
class ProjectRfqQuotation extends Model
{
    /** The supplier is still working on it; invisible to awarding. */
    public const STATUS_DRAFT = 'draft';

    /** Handed to the project team, still editable by the supplier. */
    public const STATUS_SUBMITTED = 'submitted';

    /** Acknowledged by the project team — frozen as far as the supplier goes. */
    public const STATUS_RECEIVED = 'received';

    /** Typed in by the project team. */
    public const ORIGIN_STAFF = 'staff';

    /** Filled in by the supplier through the portal. */
    public const ORIGIN_SUPPLIER = 'supplier';

    protected $fillable = [
        'project_rfq_id',
        'seq',
        'label',
        'status',
        'origin',
        'due_date',
        'scope_of_work',
        'terms_conditions',
        'inclusions',
        'exclusions',
        'duration_days',
        'quotation_file',
        'is_final',
        'submitted_at',
        'received_at',
        'received_by',
        'created_by',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'is_final'     => 'boolean',
        'submitted_at' => 'datetime',
        'received_at'  => 'datetime',
    ];

    /** Whether the supplier may still change this quotation. */
    public function isEditableBySupplier(): bool
    {
        return $this->origin === self::ORIGIN_SUPPLIER
            && $this->status !== self::STATUS_RECEIVED;
    }

    /**
     * Only an offer the project team actually holds can be awarded — a draft
     * the supplier has not sent yet is not on the table.
     */
    public function isSelectable(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_RECEIVED], true);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProjectRfq::class, 'project_rfq_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectRfqItem::class, 'project_rfq_quotation_id')->orderBy('seq');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** Quotation total, from the line items. */
    public function grandTotal(): float
    {
        return (float) $this->items->sum('total_cost');
    }

    /** "Quotation #2" or the label the user gave it. */
    public function displayName(): string
    {
        return trim((string) $this->label) ?: "Quotation #{$this->seq}";
    }
}
