<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectIocItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'description',
        'cost_code',
        'amount',
        'file_path',
        'filename',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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
