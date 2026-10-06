<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function adminRightsUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

describe('division manager admin rights', function () {

    it('passes admin checks while keeping its own role', function () {
        $division = adminRightsUser(User::ROLE_DIVISION_MANAGER);

        expect($division->hasRole(User::ROLE_ADMIN))->toBeTrue()
            ->and($division->hasRole('admin|approver'))->toBeTrue()
            ->and($division->hasAnyRole([User::ROLE_ENGINEER, User::ROLE_ADMIN]))->toBeTrue()
            ->and($division->hasRole(User::DELIVERY_ROLES))->toBeTrue()
            ->and($division->isAdmin())->toBeTrue()
            ->and($division->hasRole(User::ROLE_DIVISION_MANAGER))->toBeTrue()
            ->and($division->primaryRole())->toBe(User::ROLE_DIVISION_MANAGER);
    });

    it('does not hand admin rights to other roles', function () {
        foreach ([User::ROLE_PMD_DEPT_MANAGER, User::ROLE_DIVISION_MANAGER_USER, User::ROLE_REQUESTOR] as $role) {
            expect(adminRightsUser($role)->hasRole(User::ROLE_ADMIN))->toBeFalse();
        }
    });

    it('opens the admin-only pages to the division manager', function () {
        $division = adminRightsUser(User::ROLE_DIVISION_MANAGER);

        $this->actingAs($division)->get(route('users.index'))->assertOk();
        $this->actingAs($division)->get(route('system-settings.index'))->assertOk();
    });

    it('tells the UI the division manager is an admin', function () {
        $this->actingAs(adminRightsUser(User::ROLE_DIVISION_MANAGER))
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.role', User::ROLE_DIVISION_MANAGER)
                ->where('auth.user.is_admin', true));

        $this->actingAs(adminRightsUser(User::ROLE_PMD_DEPT_MANAGER))
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_admin', false));
    });
});
