<?php

use App\Models\User;

test('account page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/account')->assertOk();
});

test('account profile can be updated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/account/profile', [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/account');

    $user->refresh();
    expect($user->name)->toBe('Updated Name');
    expect($user->email)->toBe('updated@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('account password can be updated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/account/password', [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/account');
    expect(\Illuminate\Support\Facades\Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('account password update fails with wrong current password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/account/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasErrors('current_password');
});
