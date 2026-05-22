<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectBilling extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'stmt_no',
        'billing_type',
        'period_from',
        'period_to',
        'amount',
        'progress_pct',
        'summary',
        'remarks',
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
        'progress_pct' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
