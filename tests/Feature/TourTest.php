<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function tourUser(string $role = User::ROLE_REQUESTOR): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('turns visitors away', function () {
    $this->patch(route('tour.seen'))->assertRedirect(route('login'));
});

it('tells the page whether the first-login tour offer is still owed', function () {
    $this->actingAs(tourUser())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.tour_seen_at', null));
});

it('records that the tour offer has been answered', function () {
    $user = tourUser();

    $this->actingAs($user)->patch(route('tour.seen'))->assertNoContent();

    expect($user->fresh()->tour_seen_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->whereNot('auth.user.tour_seen_at', null));
});

it('keeps the first answer rather than moving the timestamp', function () {
    $user = tourUser(User::ROLE_DIVISION_MANAGER_USER);
    $user->forceFill(['tour_seen_at' => now()->subWeek()])->save();
    $first = $user->fresh()->tour_seen_at;

    $this->actingAs($user)->patch(route('tour.seen'))->assertNoContent();

    expect($user->fresh()->tour_seen_at->equalTo($first))->toBeTrue();
});
