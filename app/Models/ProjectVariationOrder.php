<?php

namespace App\Models;

use App\Models\Concerns\HasFileVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectVariationOrder extends Model
{
    use SoftDeletes, HasFileVersions;

    protected $fillable = [
        'project_id',
        'vo_no',
        'title',
        'description',
        'amount',
        'duration_days',
        'status',
        'submitted_date',
        'approved_date',
        'created_by',
        'requestor',
        'date_of_request',
        'priority',
        'attachment',
        'scope_original',
        'scope_proposed',
        'scope_remark',
        'schedule_original',
        'schedule_proposed',
        'schedule_remark',
        'cost_original',
        'cost_proposed',
        'cost_remark',
    ];

    protected $casts = [
        'submitted_date'  => 'date',
        'approved_date'   => 'date',
        'date_of_request' => 'date',
        'amount'          => 'decimal:2',
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
