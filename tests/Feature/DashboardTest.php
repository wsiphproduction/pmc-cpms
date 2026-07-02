<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('a department user sees the dept-user dashboard stats', function () {
    Role::firstOrCreate(['name' => 'requestor']);
    $user = User::factory()->create();
    $user->assignRole('requestor');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('stats.active_projects')
            ->has('stats.unread_messages')
            ->has('stats.my_requests')
            ->where('kpi', null)
        );
});

test('a project engineer sees the engineer dashboard stats and KPI', function () {
    Role::firstOrCreate(['name' => 'approver']);
    $user = User::factory()->create();
    $user->assignRole('approver');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('stats.active_project')
            ->has('stats.delayed')
            ->has('stats.about_to_lapse')
            ->has('stats.pending_request')
            ->has('kpi.target')
            ->has('kpi.actual')
        );
});