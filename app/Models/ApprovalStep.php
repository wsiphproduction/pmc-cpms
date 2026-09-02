<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One step of a sequential approval chain. Steps are created together when the
 * chain starts and are settled in `sequence` order — see HasApprovalChain.
 */
class ApprovalStep extends Model
{
    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'role',
        'sequence',
        'status',
        'user_id',
        'acted_at',
        'remarks',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'user_id'  => 'integer',
        'acted_at' => 'datetime',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
