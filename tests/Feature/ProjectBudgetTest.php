<?php

use App\Models\Project;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeApproverForBudget(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function makeProjectForBudget(User $creator): Project
{
    return Project::create([
        'project_no' => 'PRJ-TEST-' . uniqid(),
        'title' => 'Budget Test Project',
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

it('sets the project total cost from the sum of approved NTPs', function () {
    $approver = makeApproverForBudget();
    $project = makeProjectForBudget($approver);

    $this->actingAs($approver)->post(route('hub.ntp.store', $project), [
        'contractor_name' => 'ABC Construction',
        'baseline_start' => '2026-01-01',
        'baseline_end' => '2026-02-01',
        'approved_cost' => 100000,
    ])->assertRedirect();

    expect((float) $project->fresh()->budget_total)->toBe(100000.0);

    $this->actingAs($approver)->post(route('hub.ntp.store', $project), [
        'contractor_name' => 'XYZ Builders',
        'baseline_start' => '2026-01-01',
        'baseline_end' => '2026-02-01',
        'approved_cost' => 50000,
    ])->assertRedirect();

    expect((float) $project->fresh()->budget_total)->toBe(150000.0);
});

it('reduces the project total cost when an NTP is deleted', function () {
    $approver = makeApproverForBudget();
    $project = makeProjectForBudget($approver);

    $this->actingAs($approver)->post(route('hub.ntp.store', $project), [
        'contractor_name' => 'ABC Construction',
        'baseline_start' => '2026-01-01',
        'baseline_end' => '2026-02-01',
        'approved_cost' => 100000,
    ]);

    $ntp = $project->fresh()->ntps()->firstOrFail();

    $this->actingAs($approver)
        ->delete(route('hub.ntp.destroy', [$project, $ntp]))
        ->assertRedirect();

    expect((float) $project->fresh()->budget_total)->toBe(0.0);
});

it('increases budget_paid only once when a billing is approved, even if submitted twice', function () {
    $approver = makeApproverForBudget();
    $project = makeProjectForBudget($approver);

    $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)',
        'amount' => 20000,
    ]);
    $billing = $project->fresh()->billings()->firstOrFail();

    $this->actingAs($approver)
        ->patch(route('hub.rfp.update-status', [$project, $billing]), ['status' => 'approved'])
        ->assertRedirect();

    expect((float) $project->fresh()->budget_paid)->toBe(20000.0);

    // Submitting the same status again must not double-count.
    $this->actingAs($approver)
        ->patch(route('hub.rfp.update-status', [$project, $billing]), ['status' => 'approved'])
        ->assertRedirect();

    expect((float) $project->fresh()->budget_paid)->toBe(20000.0);
});

it('reduces budget_paid when an approved billing is reverted to pending', function () {
    $approver = makeApproverForBudget();
    $project = makeProjectForBudget($approver);

    $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)',
        'amount' => 20000,
    ]);
    $billing = $project->fresh()->billings()->firstOrFail();

    $this->actingAs($approver)->patch(route('hub.rfp.update-status', [$project, $billing]), ['status' => 'approved']);
    expect((float) $project->fresh()->budget_paid)->toBe(20000.0);

    $this->actingAs($approver)
        ->patch(route('hub.rfp.update-status', [$project, $billing]), ['status' => 'pending'])
        ->assertRedirect();

    expect((float) $project->fresh()->budget_paid)->toBe(0.0);
});

it('reduces budget_paid when an approved billing is deleted', function () {
    $approver = makeApproverForBudget();
    $project = makeProjectForBudget($approver);

    $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)',
        'amount' => 20000,
    ]);
    $billing = $project->fresh()->billings()->firstOrFail();

    $this->actingAs($approver)->patch(route('hub.rfp.update-status', [$project, $billing]), ['status' => 'approved']);
    expect((float) $project->fresh()->budget_paid)->toBe(20000.0);

    $this->actingAs($approver)
        ->delete(route('hub.rfp.destroy', [$project, $billing]))
        ->assertRedirect();

    expect((float) $project->fresh()->budget_paid)->toBe(0.0);
});

it('forbids a department user from changing a billing status', function () {
    $approver = makeApproverForBudget();
    $project = makeProjectForBudget($approver);

    $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)',
        'amount' => 20000,
    ]);
    $billing = $project->fresh()->billings()->firstOrFail();

    // Department users (requestors) may only view — never edit the hub.
    Role::firstOrCreate(['name' => 'requestor']);
    $deptUser = User::factory()->create();
    $deptUser->assignRole('requestor');

    $this->actingAs($deptUser)
        ->patch(route('hub.rfp.update-status', [$project, $billing]), ['status' => 'approved'])
        ->assertForbidden();

    expect((float) $project->fresh()->budget_paid)->toBe(0.0);
});

it('allows the assigned project manager to change a billing status even when not the creator', function () {
    $creator = makeApproverForBudget();
    $project = makeProjectForBudget($creator);

    $this->actingAs($creator)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)',
        'amount' => 20000,
    ]);
    $billing = $project->fresh()->billings()->firstOrFail();

    // A different user assigned as the project manager.
    $pm = makeApproverForBudget();
    $project->update(['project_manager_id' => $pm->id]);

    $this->actingAs($pm)
        ->patch(route('hub.rfp.update-status', [$project, $billing]), ['status' => 'approved'])
        ->assertRedirect();

    expect((float) $project->fresh()->budget_paid)->toBe(20000.0);
});

it('forbids an approver who is neither the creator nor the assigned PM', function () {
    $creator = makeApproverForBudget();
    $project = makeProjectForBudget($creator);

    $this->actingAs($creator)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)',
        'amount' => 20000,
    ]);
    $billing = $project->fresh()->billings()->firstOrFail();

    $other = makeApproverForBudget(); // not the creator, not the assigned PM

    $this->actingAs($other)
        ->patch(route('hub.rfp.update-status', [$project, $billing]), ['status' => 'approved'])
        ->assertForbidden();

    expect((float) $project->fresh()->budget_paid)->toBe(0.0);
});
