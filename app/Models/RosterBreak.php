<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A manager's roster break and the officer-in-charge who covers for them.
 *
 * While a break is running, the OIC acts as the covered role wherever the
 * approval chains ask for it — their queue grows to include the manager's,
 * and a step they settle records that it was signed on the manager's behalf.
 * The manager keeps their own access throughout; the break adds cover rather
 * than taking anything away.
 */
class RosterBreak extends Model
{
    protected $fillable = [
        'user_id',
        'oic_user_id',
        'role',
        'starts_on',
        'ends_on',
        'notes',
    ];

    protected $casts = [
        'user_id'     => 'integer',
        'oic_user_id' => 'integer',
        'starts_on'   => 'date',
        'ends_on'     => 'date',
    ];

    /** The manager who is away. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The officer-in-charge covering for them. */
    public function oic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oic_user_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    /** Breaks running on the given day (today by default), inclusive of both ends. */
    public function scopeActiveOn(Builder $query, ?Carbon $day = null): Builder
    {
        $day = ($day ?? now())->toDateString();

        return $query->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day);
    }

    /** Breaks that overlap the given range — for refusing a double booking. */
    public function scopeOverlapping(Builder $query, Carbon|string $from, Carbon|string $to): Builder
    {
        return $query
            ->whereDate('starts_on', '<=', Carbon::parse($to)->toDateString())
            ->whereDate('ends_on', '>=', Carbon::parse($from)->toDateString());
    }

    // ── State ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->starts_on->lte(today()) && $this->ends_on->gte(today());
    }

    public function isUpcoming(): bool
    {
        return $this->starts_on->gt(today());
    }

    public function isOver(): bool
    {
        return $this->ends_on->lt(today());
    }

    /** active | upcoming | ended — what the list badges. */
    public function state(): string
    {
        return match (true) {
            $this->isActive() => 'active',
            $this->isUpcoming() => 'upcoming',
            default => 'ended',
        };
    }

    /**
     * The running break under which this user covers the given role, or null
     * when they are not anybody's OIC for it today.
     */
    public static function coverFor(User $user, string $role): ?self
    {
        return static::activeOn()
            ->where('oic_user_id', $user->id)
            ->where('role', $role)
            ->first();
    }

    /** Whoever is covering any of the given roles today. */
    public static function oicIdsCovering(array $roles): \Illuminate\Support\Collection
    {
        return static::activeOn()->whereIn('role', $roles)->pluck('oic_user_id')->unique();
    }
}
