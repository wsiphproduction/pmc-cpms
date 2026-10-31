<?php

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function linkedAccount(string $role, array $attributes = []): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

/** Sheena: a department supervisor who is also the MEPEO Representative. */
function sheenasAccounts(): array
{
    $supervisor = linkedAccount(User::ROLE_DEPT_SUPERVISOR, [
        'name' => 'Sheena Calao', 'email' => 'sncallao@philsagamining.com', 'department' => 'Mill',
    ]);
    $mepeo = linkedAccount(User::ROLE_MEPEO_REP, [
        'name' => 'Sheena Calao', 'email' => 'sncallao@philsagamining.com',
    ]);

    return [$supervisor, $mepeo];
}

describe('creating a second account under the same email', function () {

    it('lets an admin add an account for another role', function () {
        $admin = linkedAccount(User::ROLE_ADMIN);
        linkedAccount(User::ROLE_DEPT_SUPERVISOR, ['email' => 'sncallao@philsagamining.com', 'department' => 'Mill']);
        Role::firstOrCreate(['name' => User::ROLE_MEPEO_REP]);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name'     => 'Sheena Calao',
                'email'    => 'sncallao@philsagamining.com',
                'password' => 'password123',
                'role'     => User::ROLE_MEPEO_REP,
            ])
            ->assertSessionHasNoErrors();

        expect(User::where('email', 'sncallao@philsagamining.com')->count())->toBe(2);
    });

    it('refuses a second account for the same role', function () {
        $admin = linkedAccount(User::ROLE_ADMIN);
        linkedAccount(User::ROLE_MEPEO_REP, ['email' => 'sncallao@philsagamining.com']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name'     => 'Sheena Calao',
                'email'    => 'SNCallao@philsagamining.com',
                'password' => 'password123',
                'role'     => User::ROLE_MEPEO_REP,
            ])
            ->assertSessionHasErrors(['email' => 'This email already has a MEPEO Representative account.']);
    });

    it('refuses moving an account onto a role its email already has', function () {
        $admin = linkedAccount(User::ROLE_ADMIN);
        [$supervisor, $mepeo] = sheenasAccounts();

        $this->actingAs($admin)
            ->put(route('users.update', $mepeo), [
                'name'       => $mepeo->name,
                'email'      => $mepeo->email,
                'role'       => User::ROLE_DEPT_SUPERVISOR,
                'department' => 'Mill',
            ])
            ->assertSessionHasErrors('email');

        // Saving it unchanged is fine.
        $this->actingAs($admin)
            ->put(route('users.update', $mepeo), [
                'name'  => $mepeo->name,
                'email' => $mepeo->email,
                'role'  => User::ROLE_MEPEO_REP,
            ])
            ->assertSessionHasNoErrors();
    });
});

it('refuses restoring a second account for the same email and role', function () {
    $admin = linkedAccount(User::ROLE_ADMIN);
    $old = linkedAccount(User::ROLE_MEPEO_REP, ['email' => 'sncallao@philsagamining.com']);
    $old->delete();
    linkedAccount(User::ROLE_MEPEO_REP, ['email' => 'sncallao@philsagamining.com']);

    $this->actingAs($admin)
        ->patch(route('users.restore', $old->id))
        ->assertSessionHasErrors('error');

    expect($old->fresh()->trashed())->toBeTrue();
});

describe('signing in', function () {

    it('signs in to whichever account the password fits', function () {
        [$supervisor, $mepeo] = sheenasAccounts();
        $mepeo->update(['password' => Hash::make('mepeo-secret')]);

        $this->post(route('login'), ['email' => $mepeo->email, 'password' => 'mepeo-secret']);
        $this->assertAuthenticatedAs($mepeo);
    });

    it('signs in to the oldest account when the password fits both', function () {
        [$supervisor] = sheenasAccounts();

        $this->post(route('login'), ['email' => $supervisor->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($supervisor);
    });

    it('still rejects a wrong password', function () {
        [$supervisor] = sheenasAccounts();

        $this->post(route('login'), ['email' => $supervisor->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    });
});

describe('switching accounts', function () {

    it('lists the other accounts in the account menu', function () {
        [$supervisor, $mepeo] = sheenasAccounts();
        Notification::create(['recipient' => $mepeo->id, 'message' => 'EMS report to fill', 'is_read' => false]);

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.role_label', 'Dept Asst Manager/Supervisor')
                ->has('auth.linked_accounts', 1)
                ->where('auth.linked_accounts.0.id', $mepeo->id)
                ->where('auth.linked_accounts.0.role', 'MEPEO Representative')
                ->where('auth.linked_accounts.0.unread', 1)
            );
    });

    it('lists nothing for a person with one account', function () {
        $this->actingAs(linkedAccount(User::ROLE_ADMIN))
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('auth.linked_accounts', 0));
    });

    it('switches to the other account, MEPEO page included', function () {
        [$supervisor, $mepeo] = sheenasAccounts();

        $this->actingAs($supervisor)
            ->post(route('account.switch', $mepeo))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($mepeo);

        // And back again: the MEPEO restriction lets the switch through.
        $this->post(route('account.switch', $supervisor))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($supervisor);
    });

    it('refuses switching into somebody else\'s account', function () {
        [$supervisor] = sheenasAccounts();
        $stranger = linkedAccount(User::ROLE_ADMIN);

        $this->actingAs($supervisor)
            ->post(route('account.switch', $stranger))
            ->assertForbidden();
        $this->assertAuthenticatedAs($supervisor);
    });
});

describe('the person\'s own account settings', function () {

    it('changes the password on every one of their accounts', function () {
        [$supervisor, $mepeo] = sheenasAccounts();

        $this->actingAs($supervisor)
            ->put(route('account.update-password'), [
                'current_password'      => 'password',
                'password'              => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasNoErrors();

        expect(Hash::check('new-password-123', $mepeo->fresh()->password))->toBeTrue();
    });

    it('moves every one of their accounts to a new email', function () {
        [$supervisor, $mepeo] = sheenasAccounts();

        $this->actingAs($supervisor)
            ->patch(route('account.update-profile'), ['name' => 'Sheena Calao', 'email' => 'sheena@philsagamining.com'])
            ->assertSessionHasNoErrors();

        expect($mepeo->fresh()->email)->toBe('sheena@philsagamining.com');
    });

    it('keeps their shared email when saving only a name', function () {
        [$supervisor] = sheenasAccounts();

        $this->actingAs($supervisor)
            ->patch(route('account.update-profile'), ['name' => 'Sheena N. Calao', 'email' => $supervisor->email])
            ->assertSessionHasNoErrors();
    });

    it('refuses taking an email that belongs to somebody else', function () {
        [$supervisor] = sheenasAccounts();
        $other = linkedAccount(User::ROLE_ADMIN, ['email' => 'boss@philsagamining.com']);

        $this->actingAs($supervisor)
            ->patch(route('account.update-profile'), ['name' => 'Sheena Calao', 'email' => $other->email])
            ->assertSessionHasErrors('email');
    });
});
