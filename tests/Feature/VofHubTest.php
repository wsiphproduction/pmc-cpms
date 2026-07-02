<?php

use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeApproverForVof(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function makeProjectForVof(User $creator): Project
{
    return Project::create([
        'project_no' => 'PRJ-TEST-' . uniqid(),
        'title' => 'VOF Test Project',
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

it('updates only the status of a variation order via the quick-status endpoint', function () {
    $approver = makeApproverForVof();
    $project = makeProjectForVof($approver);

    $this->actingAs($approver)->post(route('hub.vof.store', $project), [
        'title' => 'Add extra scaffolding',
        'amount' => 15000,
    ])->assertRedirect();

    $vof = $project->fresh()->variationOrders()->firstOrFail();
    expect($vof->status)->toBe('pending');

    $this->actingAs($approver)
        ->patch(route('hub.vof.update-status', [$project, $vof]), ['status' => 'approved'])
        ->assertRedirect();

    $vof->refresh();
    expect($vof->status)->toBe('approved');
    expect($vof->approved_date)->not->toBeNull();
});

it('clears the approved date when a variation order status is reverted to pending', function () {
    $approver = makeApproverForVof();
    $project = makeProjectForVof($approver);

    $this->actingAs($approver)->post(route('hub.vof.store', $project), [
        'title' => 'Add extra scaffolding',
        'amount' => 15000,
    ]);
    $vof = $project->fresh()->variationOrders()->firstOrFail();

    $this->actingAs($approver)->patch(route('hub.vof.update-status', [$project, $vof]), ['status' => 'rejected']);
    expect($vof->fresh()->approved_date)->not->toBeNull();

    $this->actingAs($approver)->patch(route('hub.vof.update-status', [$project, $vof]), ['status' => 'pending']);
    expect($vof->fresh()->approved_date)->toBeNull();
});

it('assigns unique vo_no values across different projects', function () {
    $approver = makeApproverForVof();
    $projectA = makeProjectForVof($approver);
    $projectB = makeProjectForVof($approver);

    $this->actingAs($approver)->post(route('hub.vof.store', $projectA), [
        'title' => 'First VO on project A',
        'amount' => 1000,
    ])->assertRedirect();

    $this->actingAs($approver)->post(route('hub.vof.store', $projectB), [
        'title' => 'First VO on project B',
        'amount' => 1000,
    ])->assertRedirect();

    $voA = $projectA->fresh()->variationOrders()->firstOrFail();
    $voB = $projectB->fresh()->variationOrders()->firstOrFail();

    expect($voA->vo_no)->not->toBe($voB->vo_no);
});

it('rejects an invalid status on the quick-status endpoint', function () {
    $approver = makeApproverForVof();
    $project = makeProjectForVof($approver);

    $this->actingAs($approver)->post(route('hub.vof.store', $project), [
        'title' => 'Add extra scaffolding',
        'amount' => 15000,
    ]);
    $vof = $project->fresh()->variationOrders()->firstOrFail();

    $this->actingAs($approver)
        ->patch(route('hub.vof.update-status', [$project, $vof]), ['status' => 'bogus'])
        ->assertSessionHasErrors('status');
});
