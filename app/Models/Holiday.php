<?php

namespace App\Models;

use App\Support\PhilippineHolidays;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Once;

/**
 * A day off on the Holidays master list. A project counted in working days
 * skips every active holiday as it skips Sundays — in its durations, its NTP
 * baseline end and its timeline. Calendar-day projects ignore the list.
 */
class Holiday extends Model
{
    public const TYPE_REGULAR = 'regular';
    public const TYPE_SPECIAL = 'special';
    public const TYPE_LOCAL = 'local';

    public const TYPES = [
        self::TYPE_REGULAR => 'Regular Holiday',
        self::TYPE_SPECIAL => 'Special Non-Working Day',
        self::TYPE_LOCAL   => 'Local Holiday',
    ];

    protected $fillable = [
        'date',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'date'      => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // The day counts read the list once per request; a change drops that copy.
        static::saved(fn () => Once::flush());
        static::deleted(fn () => Once::flush());
    }

    /**
     * Every active holiday as Y-m-d, in date order. Read once per request:
     * a list of projects counts days for each row.
     *
     * @return array<int, string>
     */
    public static function activeDates(): array
    {
        return once(fn () => static::where('is_active', true)
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all());
    }

    /**
     * Active holidays from $from to $to, both inclusive, as Y-m-d — what the
     * pages need to count working days the way the server does.
     *
     * @return array<int, string>
     */
    public static function datesBetween(Carbon|string $from, Carbon|string $to): array
    {
        $from = Carbon::parse($from)->toDateString();
        $to = Carbon::parse($to)->toDateString();

        return array_values(array_filter(self::activeDates(), fn (string $d) => $d >= $from && $d <= $to));
    }

    /**
     * Working days lost to holidays in ($from, $to] — the holidays after
     * $from up to and including $to. One on a Sunday is not counted again.
     */
    public static function workingDaysOffBetween(Carbon $from, Carbon $to): int
    {
        $after = $from->toDateString();
        $until = $to->toDateString();

        return count(array_filter(
            self::activeDates(),
            fn (string $d) => $d > $after && $d <= $until && ! Carbon::parse($d)->isSunday()
        ));
    }

    /**
     * Put a year's Philippine regular holidays on the list. A date already
     * on it is left as it stands, so an edited or deactivated day keeps the
     * change. Returns how many were added.
     */
    public static function loadRegular(int $year): int
    {
        $taken = static::whereYear('date', $year)
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $added = 0;

        foreach (PhilippineHolidays::regular($year) as $day) {
            if (in_array($day['date'], $taken, true)) {
                continue;
            }

            static::create($day + ['type' => self::TYPE_REGULAR, 'is_active' => true]);
            $added++;
        }

        return $added;
    }
}
