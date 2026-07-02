<?php

use App\Models\Setting;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function makeApproverForSettings(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

it('redirects guests to login', function () {
    $this->get(route('system-settings.index'))
        ->assertRedirect(route('login'));
});

it('forbids non-admin roles from viewing settings', function () {
    $this->actingAs(makeApproverForSettings())
        ->get(route('system-settings.index'))
        ->assertForbidden();
});

it('lets an admin view the settings page', function () {
    $this->actingAs(makeAdmin())
        ->get(route('system-settings.index'))
        ->assertOk();
});

it('lets an admin update the project completion KPI', function () {
    $this->actingAs(makeAdmin())
        ->patch(route('system-settings.update'), ['project_completion_kpi' => 65])
        ->assertRedirect();

    expect(Setting::get('project_completion_kpi'))->toBe('65');
});

it('forbids a non-admin from updating settings', function () {
    $this->actingAs(makeApproverForSettings())
        ->patch(route('system-settings.update'), ['project_completion_kpi' => 65])
        ->assertForbidden();
});

it('fails validation when the KPI value is out of range', function () {
    $this->actingAs(makeAdmin())
        ->patch(route('system-settings.update'), ['project_completion_kpi' => 150])
        ->assertSessionHasErrors(['project_completion_kpi']);
});
