<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCompletion extends Model
{
    protected $fillable = [
        'project_id',
        'reference_no',
        'sub_project_title',
        'classification',
        'plan_baseline_start',
        'plan_baseline_end',
        'plan_actual_start',
        'plan_actual_end',
        'con_baseline_start',
        'con_baseline_end',
        'con_actual_start',
        'con_actual_end',
        'contractor',
        'baseline_amount',
        'actual_amount',
        'payment_status',
        'completion_status',
        'request_date',
        'date_prepared',
        'issued_on',
        'received_by',
        'accepted_by',
        'acknowledged_by',
        'photos',
        'saved_by',
    ];

    protected $casts = [
        'plan_baseline_start' => 'date',
        'plan_baseline_end'   => 'date',
        'plan_actual_start'   => 'date',
        'plan_actual_end'     => 'date',
        'con_baseline_start'  => 'date',
        'con_baseline_end'    => 'date',
        'con_actual_start'    => 'date',
        'con_actual_end'      => 'date',
        'request_date'        => 'date',
        'date_prepared'       => 'date',
        'issued_on'           => 'date',
        'baseline_amount'     => 'decimal:2',
        'actual_amount'       => 'decimal:2',
        'photos'              => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
