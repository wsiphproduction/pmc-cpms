<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectBilling extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'project_ntp_id',
        'stmt_no',
        'billing_type',
        'period_from',
        'period_to',
        'amount',
        'retention_pct',
        'retention_amount',
        'progress_pct',
        'summary',
        'remarks',
        'attachments',
        'recommendation',
        'status',
        'file_path',
        'filename',
        'created_by',
    ];

    protected $casts = [
        'period_from'  => 'date',
        'period_to'    => 'date',
        'amount'       => 'decimal:2',
        'retention_pct'    => 'decimal:2',
        'retention_amount' => 'decimal:2',
        'progress_pct' => 'decimal:2',
        'attachments'  => 'array',
    ];

    /**
     * What actually gets released to the contractor: the billed amount is
     * treated as retention-inclusive, so the retention is carved out of it
     * rather than added on top.
     */
    public function getNetAmountAttribute(): float
    {
        return (float) $this->amount - (float) $this->retention_amount;
    }

    /**
     * Split a billed amount into what is released now and what is held back.
     * A null or zero rate holds nothing.
     */
    public static function splitRetention(float $amount, ?float $pct): float
    {
        if ($pct === null || $pct <= 0) {
            return 0.0;
        }

        return round($amount - ($amount / (1 + ($pct / 100))), 2);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function ntp(): BelongsTo
    {
        return $this->belongsTo(ProjectNtp::class, 'project_ntp_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(BillingStatusLog::class)->latest();
    }
}
