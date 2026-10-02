<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function impersonationAdmin(string $email): User
{
    Role::firstOrCreate(['name' => User::ROLE_ADMIN]);

    $admin = User::factory()->create(['email' => $email]);
    $admin->assignRole(User::ROLE_ADMIN);

    return $admin;
}

function impersonationTarget(): User
{
    Role::firstOrCreate(['name' => User::ROLE_REQUESTOR]);

    $user = User::factory()->create(['name' => 'Rita Requestor']);
    $user->assignRole(User::ROLE_REQUESTOR);

    return $user;
}

describe('impersonation', function () {

    it('offers impersonation on the users page only to the IT account', function () {
        $this->actingAs(impersonationAdmin(User::IMPERSONATOR_EMAIL))
            ->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page->where('canImpersonate', true));

        $this->actingAs(impersonationAdmin('someone@philsagamining.com'))
            ->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page->where('canImpersonate', false));
    });

    it('signs the IT account in as the chosen user', function () {
        $it = impersonationAdmin(User::IMPERSONATOR_EMAIL);
        $target = impersonationTarget();

        $this->actingAs($it)
            ->post(route('users.impersonate', $target))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(User::IMPERSONATOR_SESSION_KEY, $it->id);

        $this->assertAuthenticatedAs($target);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $target->id)
                ->where('auth.impersonator.id', $it->id)
            );
    });

    it('refuses other admins', function () {
        $this->actingAs(impersonationAdmin('someone@philsagamining.com'))
            ->post(route('users.impersonate', impersonationTarget()))
            ->assertForbidden();
    });

    it('returns to the IT account and the users page on exit', function () {
        $it = impersonationAdmin(User::IMPERSONATOR_EMAIL);
        $target = impersonationTarget();

        $this->actingAs($it)->post(route('users.impersonate', $target));

        $this->post(route('impersonate.leave'))
            ->assertRedirect(route('users.index'))
            ->assertSessionMissing(User::IMPERSONATOR_SESSION_KEY);

        $this->assertAuthenticatedAs($it);
    });

    it('ignores an exit when nobody is impersonating', function () {
        $target = impersonationTarget();

        $this->actingAs($target)
            ->post(route('impersonate.leave'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($target);
    });
});
