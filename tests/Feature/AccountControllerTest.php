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

test('account profile update emails the old and new addresses', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->actingAs($user)->patch('/account/profile', [
        'name' => 'Updated Name',
        'email' => 'new@example.com',
    ])->assertSessionHasNoErrors();

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\AccountUpdated::class, fn ($mail) => $mail->hasTo('old@example.com'));
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\AccountUpdated::class, fn ($mail) => $mail->hasTo('new@example.com'));
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\AccountUpdated::class, 2);
});

test('account profile update sends no email when nothing changed', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/account/profile', [
        'name' => $user->name,
        'email' => $user->email,
    ])->assertSessionHasNoErrors();

    \Illuminate\Support\Facades\Mail::assertNothingSent();
});

test('account password update emails the user', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->put('/account/password', [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasNoErrors();

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\AccountUpdated::class, fn ($mail) => $mail->hasTo($user->email));
});
