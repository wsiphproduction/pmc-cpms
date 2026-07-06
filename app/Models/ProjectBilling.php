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
        'progress_pct' => 'decimal:2',
        'attachments'  => 'array',
    ];

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
