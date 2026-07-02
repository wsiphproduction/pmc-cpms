<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditTrail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'action',
        'reference_id',
        'reference_type',
        'changes',
        'ip_address',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    // ── Static helper ──────────────────────────────────────────────────────

    public static function log(string $action, Model $model, array $context = []): self
    {
        $entry = self::create([
            'user_id'        => auth()->id(),
            'action'         => $action,
            'reference_id'   => $model->getKey(),
            'reference_type' => get_class($model),
            'changes'        => array_merge(['ip' => request()->ip()], $context) ?: null,
            'ip_address'     => request()->ip(),
        ]);

        if ($model instanceof Project) {
            $model->notifyRequester($action);
        }

        return $entry;
    }
}