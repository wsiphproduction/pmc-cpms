<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectVariationOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'vo_no',
        'title',
        'description',
        'amount',
        'status',
        'submitted_date',
        'approved_date',
        'created_by',
    ];

    protected $casts = [
        'submitted_date' => 'date',
        'approved_date'  => 'date',
        'amount'         => 'decimal:2',
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
