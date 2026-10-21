<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function fontScaleUser(array $attributes = []): User
{
    Role::firstOrCreate(['name' => User::ROLE_ENGINEER]);

    $user = User::factory()->create($attributes);
    $user->assignRole(User::ROLE_ENGINEER);

    return $user;
}

it('starts every account at the normal text size', function () {
    $this->actingAs(fontScaleUser())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.font_scale', 100));
});

it('saves the text size picked from the top bar', function () {
    $user = fontScaleUser();

    $this->actingAs($user)
        ->patch(route('account.font-scale'), ['font_scale' => 125])
        ->assertNoContent();

    expect($user->fresh()->font_scale)->toBe(125);

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.font_scale', 125));
});

it('only accepts the sizes the control offers', function () {
    $user = fontScaleUser();

    foreach ([0, 95, 400, 'big'] as $size) {
        $this->actingAs($user)
            ->patchJson(route('account.font-scale'), ['font_scale' => $size])
            ->assertUnprocessable();
    }

    expect($user->fresh()->font_scale)->toBe(100);
});

it('applies the saved size from the very first paint', function () {
    $this->actingAs(fontScaleUser(['font_scale' => 125]))
        ->get(route('dashboard'))
        ->assertSee('style="zoom: 1.25"', false);

    $this->actingAs(fontScaleUser(['email' => 'normal@example.com']))
        ->get(route('dashboard'))
        ->assertDontSee('zoom:', false);
});

it('needs a signed-in user', function () {
    $this->patch(route('account.font-scale'), ['font_scale' => 125])->assertRedirect(route('login'));
});
