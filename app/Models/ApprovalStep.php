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
        'on_behalf_of_user_id',
        'acted_at',
        'remarks',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'user_id'  => 'integer',
        'on_behalf_of_user_id' => 'integer',
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

    /** The manager an OIC signed this step for; null when signed by the office itself. */
    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'on_behalf_of_user_id');
    }

    public function signedAsOic(): bool
    {
        return $this->on_behalf_of_user_id !== null;
    }

    /**
     * The office this signature speaks for, as the notifications name it:
     * "PMD Department Manager", or "PMD Department Manager (OIC)" when the
     * signature came from whoever was covering the seat.
     */
    public function officeLabel(): string
    {
        return User::roleLabel($this->role) . ($this->signedAsOic() ? ' (OIC)' : '');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
