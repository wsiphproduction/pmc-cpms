<?php

use App\Models\Division;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeUsersAdmin(): User
{
    Role::firstOrCreate(['name' => User::ROLE_ADMIN]);
    Role::firstOrCreate(['name' => User::ROLE_REQUESTOR]);
    Role::firstOrCreate(['name' => User::ROLE_DIVISION_MANAGER_USER]);

    $admin = User::factory()->create();
    $admin->assignRole(User::ROLE_ADMIN);

    return $admin;
}

function divisionUserPayload(array $overrides = []): array
{
    return array_merge([
        'name'     => 'Dana Division',
        'email'    => 'dana@example.com',
        'password' => 'password123',
        'role'     => User::ROLE_DIVISION_MANAGER_USER,
        'division' => 'Engineering Division',
    ], $overrides);
}

describe('division manager user', function () {

    it('offers the active divisions to the users page', function () {
        Division::create(['name' => 'Engineering Division', 'description' => 'Civil and structural']);
        Division::create(['name' => 'Dormant Division', 'is_active' => false]);

        $this->actingAs(makeUsersAdmin())
            ->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/index')
                ->has('divisions', 1)
                ->where('divisions.0.value', 'Engineering Division')
                ->where('divisions.0.label', 'Engineering Division — Civil and structural')
                ->where('roleLabels.' . User::ROLE_DIVISION_MANAGER_USER, 'Division Manager User')
            );
    });

    it('creates a division manager user attached to a division', function () {
        Division::create(['name' => 'Engineering Division']);

        $this->actingAs(makeUsersAdmin())
            ->post(route('users.store'), divisionUserPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'dana@example.com')->firstOrFail();
        expect($user->primaryRole())->toBe(User::ROLE_DIVISION_MANAGER_USER)
            ->and($user->division)->toBe('Engineering Division')
            ->and($user->department)->toBeNull();
    });

    it('requires a division for the role', function () {
        $this->actingAs(makeUsersAdmin())
            ->post(route('users.store'), divisionUserPayload(['division' => null]))
            ->assertSessionHasErrors(['division']);
    });

    it('rejects a division that is not on the master list', function () {
        $this->actingAs(makeUsersAdmin())
            ->post(route('users.store'), divisionUserPayload(['division' => 'No Such Division']))
            ->assertSessionHasErrors(['division']);
    });

    it('drops the division when the role changes to something else', function () {
        Division::create(['name' => 'Engineering Division']);
        $admin = makeUsersAdmin();

        $this->actingAs($admin)->post(route('users.store'), divisionUserPayload());
        $user = User::where('email', 'dana@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => User::ROLE_REQUESTOR,
                'department' => 'Civil Works',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        expect($user->division)->toBeNull()
            ->and($user->department)->toBe('Civil Works');
    });

    it('ignores a division sent for a role that does not use one', function () {
        Division::create(['name' => 'Engineering Division']);

        $this->actingAs(makeUsersAdmin())
            ->post(route('users.store'), divisionUserPayload(['role' => User::ROLE_REQUESTOR, 'department' => 'Civil Works']))
            ->assertSessionHasNoErrors();

        expect(User::where('email', 'dana@example.com')->value('division'))->toBeNull();
    });

});
