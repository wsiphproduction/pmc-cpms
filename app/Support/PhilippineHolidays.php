<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The Philippine regular holidays for a year — the Holidays master list's
 * default set. Fixed dates are written out; Holy Week follows Easter, and
 * National Heroes Day is the last Monday of August.
 *
 * Eid'l Fitr and Eid'l Adha are regular holidays too, but their dates are
 * proclaimed each year from the lunar calendar, so they are added by hand once
 * the proclamation is out, along with any special or local holiday.
 */
class PhilippineHolidays
{
    /**
     * @return array<int, array{date: string, name: string}> in date order
     */
    public static function regular(int $year): array
    {
        $easter = self::easter($year);

        $heroesDay = Carbon::create($year, 8, 31);
        while (! $heroesDay->isMonday()) {
            $heroesDay->subDay();
        }

        $days = [
            [Carbon::create($year, 1, 1), "New Year's Day"],
            [$easter->copy()->subDays(3), 'Maundy Thursday'],
            [$easter->copy()->subDays(2), 'Good Friday'],
            [Carbon::create($year, 4, 9), 'Araw ng Kagitingan'],
            [Carbon::create($year, 5, 1), 'Labor Day'],
            [Carbon::create($year, 6, 12), 'Independence Day'],
            [$heroesDay, 'National Heroes Day'],
            [Carbon::create($year, 11, 30), 'Bonifacio Day'],
            [Carbon::create($year, 12, 25), 'Christmas Day'],
            [Carbon::create($year, 12, 30), 'Rizal Day'],
        ];

        return collect($days)
            ->map(fn (array $day) => ['date' => $day[0]->toDateString(), 'name' => $day[1]])
            ->sortBy('date')
            ->values()
            ->all();
    }

    /** Easter Sunday (Gregorian), by the anonymous Meeus/Jones/Butcher method. */
    public static function easter(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $n = $h + $l - 7 * $m + 114;

        return Carbon::create($year, intdiv($n, 31), ($n % 31) + 1)->startOfDay();
    }
}
