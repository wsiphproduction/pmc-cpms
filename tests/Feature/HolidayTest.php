<?php

use App\Models\Holiday;
use App\Models\Project;
use App\Models\User;
use App\Support\PhilippineHolidays;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function holidayAdmin(string $role = 'assistant_manager'): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('works out the philippine regular holidays for a year', function () {
    $days = collect(PhilippineHolidays::regular(2026))->pluck('name', 'date')->all();

    expect($days)->toBe([
        '2026-01-01' => "New Year's Day",
        '2026-04-02' => 'Maundy Thursday',
        '2026-04-03' => 'Good Friday',
        '2026-04-09' => 'Araw ng Kagitingan',
        '2026-05-01' => 'Labor Day',
        '2026-06-12' => 'Independence Day',
        '2026-08-31' => 'National Heroes Day',
        '2026-11-30' => 'Bonifacio Day',
        '2026-12-25' => 'Christmas Day',
        '2026-12-30' => 'Rizal Day',
    ]);

    // Easter moves; Heroes Day is the last Monday of August.
    expect(PhilippineHolidays::easter(2025)->toDateString())->toBe('2025-04-20')
        ->and(PhilippineHolidays::easter(2027)->toDateString())->toBe('2027-03-28')
        ->and(collect(PhilippineHolidays::regular(2027))->firstWhere('name', 'National Heroes Day')['date'])->toBe('2027-08-30');
});

it('starts the list with the regular holidays from 2025 to 2030', function () {
    expect(Holiday::whereYear('date', 2025)->count())->toBe(10)
        ->and(Holiday::whereYear('date', 2030)->count())->toBe(10)
        ->and(Holiday::whereDate('date', '2026-12-25')->value('name'))->toBe('Christmas Day');
});

it('skips holidays as well as sundays when counting working days', function () {
    $d = fn (string $ymd) => Carbon::parse($ymd);

    // Mon Dec 21 → Thu Dec 31, 2026: 10 days, Sunday the 27th, Christmas (Fri) and Rizal Day (Wed).
    expect(Project::countDays($d('2026-12-21'), $d('2026-12-31'), true))->toBe(7)
        ->and(Project::countDays($d('2026-12-31'), $d('2026-12-21'), true))->toBe(-7)
        ->and(Project::countDays($d('2026-12-21'), $d('2026-12-31'), false))->toBe(10);

    // A local holiday counts from the moment it is added; switched off, it stops.
    $local = Holiday::create(['date' => '2026-12-22', 'name' => 'Town Fiesta', 'type' => Holiday::TYPE_LOCAL]);
    expect(Project::countDays($d('2026-12-21'), $d('2026-12-31'), true))->toBe(6);

    $local->update(['is_active' => false]);
    expect(Project::countDays($d('2026-12-21'), $d('2026-12-31'), true))->toBe(7);

    // One on a Sunday is not taken off twice.
    Holiday::create(['date' => '2026-12-27', 'name' => 'Sunday Feast', 'type' => Holiday::TYPE_LOCAL]);
    expect(Project::countDays($d('2026-12-21'), $d('2026-12-31'), true))->toBe(7);
});

it('lets master data add, change and remove a holiday', function () {
    $user = holidayAdmin();

    $this->actingAs($user)
        ->post(route('master.holidays.store'), ['date' => '2026-07-24', 'name' => 'Town Fiesta', 'type' => 'local'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $holiday = Holiday::whereDate('date', '2026-07-24')->firstOrFail();
    expect($holiday->type)->toBe('local')->and($holiday->is_active)->toBeTrue();

    // One holiday per date.
    $this->actingAs($user)
        ->post(route('master.holidays.store'), ['date' => '2026-12-25', 'name' => 'Another', 'type' => 'local'])
        ->assertSessionHasErrors(['date']);

    $this->actingAs($user)
        ->put(route('master.holidays.update', $holiday), ['date' => '2026-07-25', 'name' => 'Town Fiesta (moved)', 'type' => 'special'])
        ->assertSessionHasNoErrors();
    expect($holiday->fresh()->date->toDateString())->toBe('2026-07-25');

    $this->actingAs($user)->patch(route('master.toggle', ['holidays', $holiday->id]))->assertRedirect();
    expect($holiday->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)->delete(route('master.holidays.destroy', $holiday))->assertRedirect();
    expect(Holiday::find($holiday->id))->toBeNull();

    $this->actingAs($user)
        ->get(route('master.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('holidays', 60)
            ->where('holidayTypes.local', 'Local Holiday'));
});

it('loads a year of regular holidays without touching dates already listed', function () {
    $user = holidayAdmin('admin');

    $this->actingAs($user)->post(route('master.holidays.load-regular'), ['year' => 2031])->assertRedirect();
    expect(Holiday::whereYear('date', 2031)->count())->toBe(10);

    // An edited day keeps its edit when the year is loaded again.
    Holiday::whereDate('date', '2031-12-25')->update(['name' => 'Pasko']);
    Holiday::whereDate('date', '2031-12-30')->delete();

    $this->actingAs($user)->post(route('master.holidays.load-regular'), ['year' => 2031])->assertRedirect();

    expect(Holiday::whereYear('date', 2031)->count())->toBe(10)
        ->and(Holiday::whereDate('date', '2031-12-25')->value('name'))->toBe('Pasko');
});

it('keeps project engineers out of the holidays list', function () {
    $engineer = holidayAdmin('approver');

    $this->actingAs($engineer)
        ->post(route('master.holidays.store'), ['date' => '2026-07-24', 'name' => 'Town Fiesta', 'type' => 'local'])
        ->assertForbidden();
});
