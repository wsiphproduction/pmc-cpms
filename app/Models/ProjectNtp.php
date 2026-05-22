<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectNtp extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'ntp_no',
        'contractor_name',
        'project_rfq_id',
        'baseline_start',
        'baseline_end',
        'approved_cost',
        'issued_date',
        'issued_by',
        'created_by',
    ];

    protected $casts = [
        'baseline_start' => 'date',
        'baseline_end'   => 'date',
        'issued_date'    => 'date',
        'approved_cost'  => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(ProjectRfq::class, 'project_rfq_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
