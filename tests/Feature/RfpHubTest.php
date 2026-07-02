<?php

use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeApproverForRfp(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function makeProjectForRfp(User $creator): Project
{
    return Project::create([
        'project_no' => 'PRJ-TEST-' . uniqid(),
        'title' => 'RFP Test Project',
        'site' => 'Main Plant',
        'asset_id' => 'A1',
        'class_name' => 'Minor',
        'priority' => '1',
        'status_key' => 'PLANNING',
        'work_force' => 'In-House',
        'wr_no' => 'WR-1',
        'wr_date' => now(),
        'dept_owner' => 'Engineering',
        'cost_code' => 'CC-001',
        'category' => 'General',
        'service_type' => 'Repair',
        'deadline' => now()->addDays(30),
        'created_by' => $creator->id,
    ]);
}

it('persists the recommendation when a billing is submitted', function () {
    $approver = makeApproverForRfp();
    $project = makeProjectForRfp($approver);

    $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)',
        'amount' => 10000,
        'recommendation' => 'For Payment',
    ])->assertRedirect();

    $billing = $project->fresh()->billings()->firstOrFail();
    expect($billing->recommendation)->toBe('For Payment');
});

it('updates the recommendation when a billing is edited', function () {
    $approver = makeApproverForRfp();
    $project = makeProjectForRfp($approver);

    $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)',
        'amount' => 10000,
    ]);
    $billing = $project->fresh()->billings()->firstOrFail();

    $this->actingAs($approver)->patch(route('hub.rfp.update', [$project, $billing]), [
        'billing_type' => 'Milestone (Progress)',
        'amount' => 10000,
        'status' => 'pending',
        'recommendation' => 'Withhold (Pending Clarification)',
    ])->assertRedirect();

    expect($billing->fresh()->recommendation)->toBe('Withhold (Pending Clarification)');
});
