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

it('adds an approved variation order to the project cost', function () {
    $approver = makeApproverForVof();
    $project  = makeProjectForVof($approver);
    $project->update(['budget_base' => 500000, 'budget_total' => 500000]);

    $this->actingAs($approver)->post(route('hub.vof.store', $project), [
        'title' => 'Extra roofing', 'amount' => 120000,
    ])->assertRedirect();

    // Raised but not approved yet — the cost must not move.
    expect((float) $project->fresh()->budget_total)->toBe(500000.0);

    $vof = $project->variationOrders()->firstOrFail();
    $this->actingAs($approver)
        ->patch(route('hub.vof.update-status', [$project, $vof]), ['status' => 'approved'])
        ->assertRedirect();

    expect((float) $project->fresh()->budget_total)->toBe(620000.0)
        ->and((float) $project->fresh()->budget_base)->toBe(500000.0);
});

it('keeps a rejected variation order out of the project cost', function () {
    $approver = makeApproverForVof();
    $project  = makeProjectForVof($approver);
    $project->update(['budget_base' => 500000, 'budget_total' => 500000]);

    $this->actingAs($approver)->post(route('hub.vof.store', $project), [
        'title' => 'Declined scope', 'amount' => 90000,
    ])->assertRedirect();

    $vof = $project->variationOrders()->firstOrFail();
    $this->actingAs($approver)
        ->patch(route('hub.vof.update-status', [$project, $vof]), ['status' => 'rejected'])
        ->assertRedirect();

    expect((float) $project->fresh()->budget_total)->toBe(500000.0);
});

it('takes an approved variation back out when it is un-approved or deleted', function () {
    $approver = makeApproverForVof();
    $project  = makeProjectForVof($approver);
    $project->update(['budget_base' => 500000, 'budget_total' => 500000]);

    $this->actingAs($approver)->post(route('hub.vof.store', $project), [
        'title' => 'Extra roofing', 'amount' => 120000,
    ])->assertRedirect();

    $vof = $project->variationOrders()->firstOrFail();
    $approve = fn () => $this->actingAs($approver)
        ->patch(route('hub.vof.update-status', [$project, $vof]), ['status' => 'approved']);

    $approve()->assertRedirect();
    expect((float) $project->fresh()->budget_total)->toBe(620000.0);

    // Back to pending.
    $this->actingAs($approver)
        ->patch(route('hub.vof.update-status', [$project, $vof]), ['status' => 'pending'])
        ->assertRedirect();
    expect((float) $project->fresh()->budget_total)->toBe(500000.0);

    // Re-approved, then deleted outright.
    $approve()->assertRedirect();
    $this->actingAs($approver)
        ->delete(route('hub.vof.destroy', [$project, $vof]))
        ->assertRedirect();

    expect((float) $project->fresh()->budget_total)->toBe(500000.0);
});

it('does not fold approved variations into the base when the project form is re-saved', function () {
    $approver = makeApproverForVof();
    $project  = makeProjectForVof($approver);
    $project->update(['budget_base' => 500000, 'budget_total' => 500000]);

    $this->actingAs($approver)->post(route('hub.vof.store', $project), [
        'title' => 'Extra roofing', 'amount' => 120000,
    ]);
    $vof = $project->variationOrders()->firstOrFail();
    $this->actingAs($approver)
        ->patch(route('hub.vof.update-status', [$project, $vof]), ['status' => 'approved']);

    expect((float) $project->fresh()->budget_total)->toBe(620000.0);

    // The edit form is prefilled with the base, so re-saving it unchanged must
    // leave the total where it is rather than compounding the variation.
    $form = $this->actingAs($approver)->get(route('projects.edit', $project));
    $prefilled = $form->viewData('page')['props']['project']['project_cost'];
    expect((float) $prefilled)->toBe(500000.0);

    $this->actingAs($approver)->patch(route('projects.update', $project), [
        'project_type' => 'minor',
        'title' => 'VOF Test Project',
        'project_manager' => (string) $approver->id,
        'site' => 'Main Plant', 'asset_id' => 'A1', 'cls' => 'Minor',
        'priority' => '1', 'status' => 'PLANNING', 'work_force' => 'In-House',
        'wr_no' => 'WR-1', 'wr_date' => '2026-05-04', 'dept_owner' => 'Engineering',
        'cost_code' => 'CC-001', 'category' => 'General', 'service_type' => 'Repair',
        'deadline' => '2026-06-04', 'project_cost' => $prefilled,
    ])->assertRedirect();

    expect((float) $project->fresh()->budget_total)->toBe(620000.0)
        ->and((float) $project->fresh()->budget_base)->toBe(500000.0);
});
