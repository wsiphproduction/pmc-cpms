<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProjectRfq extends Model
{
    use SoftDeletes;

    /**
     * Quotation fields mirrored onto this row from whichever quotation is
     * final, so readers that predate quotations (NTP issuance, the printed
     * PMD-PRJ-FRM-03, the hub table, reports) keep working unchanged.
     */
    public const MIRRORED = [
        'due_date', 'scope_of_work', 'terms_conditions',
        'inclusions', 'exclusions', 'duration_days', 'quotation_file',
    ];

    /** An RFQ is only reachable by its supplier if it carries a token. */
    protected static function booted(): void
    {
        static::creating(function (self $rfq) {
            $rfq->portal_token ??= Str::random(48);
        });
    }

    protected $fillable = [
        'project_id',
        'contractor_name',
        'sent_date',
        'due_date',
        'status',
        'scope_of_work',
        'terms_conditions',
        'inclusions',
        'exclusions',
        'duration_days',
        'created_by',
        'quotation_file',
        'recipient_email',
        // Stands in for a login: the supplier reaches their quotation form
        // through the link in the RFQ email and nothing else.
        'portal_token',
    ];

    protected $casts = [
        'sent_date' => 'date',
        'due_date'  => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The line items of whichever quotation is final. Every item row carries
     * this RFQ's id as well as its quotation's, so the final flag is what
     * separates the offer the project runs with from the ones beside it.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProjectRfqItem::class)
            ->whereHas('quotation', fn ($q) => $q->where('is_final', true))
            ->orderBy('seq');
    }

    /**
     * The offers made against this RFQ, in the order they were raised. A
     * vendor may put up several; exactly one carries `is_final`, and that is
     * the one the project awards and issues the NTP from.
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(ProjectRfqQuotation::class)->orderBy('seq');
    }

    /** The offer the project runs with. */
    public function finalQuotation(): HasOne
    {
        return $this->hasOne(ProjectRfqQuotation::class)->where('is_final', true);
    }

    /**
     * Copy the final quotation's terms onto this row. Line items need no
     * copying — `items()` already reads through the final flag.
     */
    public function syncFromFinalQuotation(): void
    {
        $final = $this->finalQuotation()->first();

        if ($final === null) {
            return;
        }

        $this->update(collect(self::MIRRORED)
            ->mapWithKeys(fn (string $field) => [$field => $final->{$field}])
            ->all());
    }

    /** Where the supplier fills in their quotation — the link the RFQ email carries. */
    public function portalUrl(): string
    {
        return route('supplier-quote.show', $this->portal_token);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
