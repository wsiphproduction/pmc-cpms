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

it('offers the source NTP pre-selected on a sub-project billing form', function () {
    $approver = makeApproverForRfp();
    $parent   = makeProjectForRfp($approver);

    $ntp = App\Models\ProjectNtp::create([
        'project_id'      => $parent->id,
        'ntp_no'          => 'PMC-NTP-2026-0044',
        'contractor_name' => 'Primo Konstruk',
        'baseline_start'  => '2026-01-01',
        'baseline_end'    => '2026-06-01',
        'approved_cost'   => 168766.72,
        'status'          => 'issued',
    ]);

    $sub = makeProjectForRfp($approver);
    $sub->forceFill(['parent_id' => $parent->id, 'source_ntp_id' => $ntp->id])->save();

    // The sub-project raises no NTPs of its own, so without the source-NTP
    // fallback the billing form would offer nothing to bill against.
    expect($sub->ntps()->count())->toBe(0);

    $this->actingAs($approver)
        ->get(route('projects.hub.rfp', $sub))
        ->assertOk()
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->has('hub_data.ntps', 1)
            ->where('hub_data.ntps.0.id', $ntp->id)
            ->where('hub_data.ntps.0.ntp_no', 'PMC-NTP-2026-0044')
            ->where('hub_data.ntps.0.contractor', 'Primo Konstruk')
            ->where('hub_data.default_ntp_id', $ntp->id)
            ->where('hub_data.ntp_locked', true));
});

it('leaves the billing NTP unselected and choosable on a top-level project', function () {
    $approver = makeApproverForRfp();
    $project  = makeProjectForRfp($approver);

    foreach (['PMC-NTP-2026-0051', 'PMC-NTP-2026-0052'] as $no) {
        App\Models\ProjectNtp::create([
            'project_id'      => $project->id,
            'ntp_no'          => $no,
            'contractor_name' => 'ABC Builders',
            'baseline_start'  => '2026-01-01',
            'baseline_end'    => '2026-06-01',
            'approved_cost'   => 100000,
            'status'          => 'issued',
        ]);
    }

    $this->actingAs($approver)
        ->get(route('projects.hub.rfp', $project))
        ->assertOk()
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->has('hub_data.ntps', 2)
            ->where('hub_data.default_ntp_id', null)
            ->where('hub_data.ntp_locked', false));
});

it('rolls a sub-project billing up into the parent paid total', function () {
    $approver = makeApproverForRfp();
    $parent   = makeProjectForRfp($approver);
    $parent->update(['budget_total' => 1000000]);

    $sub = makeProjectForRfp($approver);
    $sub->forceFill(['parent_id' => $parent->id])->save();

    // Own billing on the parent.
    $this->actingAs($approver)->post(route('hub.rfp.store', $parent), [
        'billing_type' => 'Milestone (Progress)', 'amount' => 300000,
    ])->assertRedirect();

    // Billing raised from the sub-project's own hub.
    $this->actingAs($approver)->post(route('hub.rfp.store', $sub), [
        'billing_type' => 'Milestone (Progress)', 'amount' => 200000,
    ])->assertRedirect();

    // Pending billings count toward neither total.
    expect($parent->fresh()->budget_paid)->toEqual(0.0);

    $own = App\Models\ProjectBilling::where('project_id', $parent->id)->firstOrFail();
    $subBilling = App\Models\ProjectBilling::where('project_id', $sub->id)->firstOrFail();

    $this->actingAs($approver)
        ->patch(route('hub.rfp.update-status', [$parent, $own]), ['status' => 'approved'])
        ->assertRedirect();
    expect($parent->fresh()->budget_paid)->toEqual(300000.0);

    // Approving on the sub-project side must move the parent too.
    $this->actingAs($approver)
        ->patch(route('hub.rfp.update-status', [$sub, $subBilling]), ['status' => 'approved'])
        ->assertRedirect();

    expect($parent->fresh()->budget_paid)->toEqual(500000.0)
        ->and($sub->fresh()->budget_paid)->toEqual(200000.0);
});

it('drops a deleted sub-project billing back out of the parent total', function () {
    $approver = makeApproverForRfp();
    $parent   = makeProjectForRfp($approver);
    $sub      = makeProjectForRfp($approver);
    $sub->forceFill(['parent_id' => $parent->id])->save();

    $this->actingAs($approver)->post(route('hub.rfp.store', $sub), [
        'billing_type' => 'Milestone (Progress)', 'amount' => 200000,
    ])->assertRedirect();

    $billing = App\Models\ProjectBilling::where('project_id', $sub->id)->firstOrFail();
    $this->actingAs($approver)
        ->patch(route('hub.rfp.update-status', [$sub, $billing]), ['status' => 'approved'])
        ->assertRedirect();
    expect($parent->fresh()->budget_paid)->toEqual(200000.0);

    $this->actingAs($approver)
        ->delete(route('hub.rfp.destroy', [$sub, $billing]))
        ->assertRedirect();

    expect($parent->fresh()->budget_paid)->toEqual(0.0)
        ->and($sub->fresh()->budget_paid)->toEqual(0.0);
});

it('splits a retained billing the way the reference sheet does', function () {
    $approver = makeApproverForRfp();
    $project  = makeProjectForRfp($approver);
    $project->update(['budget_base' => 100000, 'budget_total' => 100000]);

    App\Models\Setting::set('retention_pct', '5');

    // Four statements at 25% of a PhP 100,000 contract, each carrying retention.
    foreach (range(1, 4) as $i) {
        $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
            'billing_type'    => 'Milestone (Progress)',
            'amount'          => 25000,
            'progress_pct'    => 25,
            'apply_retention' => true,
        ])->assertRedirect();
    }

    $billings = App\Models\ProjectBilling::where('project_id', $project->id)->get();
    expect($billings)->toHaveCount(4);

    foreach ($billings as $b) {
        expect(round((float) $b->retention_amount, 2))->toBe(1190.48)
            ->and(round($b->net_amount, 2))->toBe(23809.52)
            ->and((float) $b->retention_pct)->toBe(5.0);
    }

    // Totals from the sheet: 95,238.10 released, 4,761.90 held, summing to the
    // full contract.
    expect(round($billings->sum(fn ($b) => $b->net_amount), 2))->toBe(95238.08)
        ->and(round($billings->sum(fn ($b) => (float) $b->retention_amount), 2))->toBe(4761.92);
});

it('counts only the released portion of a retained billing as paid', function () {
    $approver = makeApproverForRfp();
    $project  = makeProjectForRfp($approver);
    $project->update(['budget_base' => 100000, 'budget_total' => 100000]);

    App\Models\Setting::set('retention_pct', '5');

    $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)', 'amount' => 25000, 'apply_retention' => true,
    ])->assertRedirect();

    $billing = App\Models\ProjectBilling::where('project_id', $project->id)->firstOrFail();
    $this->actingAs($approver)
        ->patch(route('hub.rfp.update-status', [$project, $billing]), ['status' => 'approved'])
        ->assertRedirect();

    // The retention is withheld, so it must not count toward paid.
    expect(round((float) $project->fresh()->budget_paid, 2))->toBe(23809.52);
});

it('leaves a billing whole when retention is not applied', function () {
    $approver = makeApproverForRfp();
    $project  = makeProjectForRfp($approver);
    App\Models\Setting::set('retention_pct', '5');

    $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)', 'amount' => 25000,
    ])->assertRedirect();

    $billing = App\Models\ProjectBilling::where('project_id', $project->id)->firstOrFail();
    expect($billing->retention_pct)->toBeNull()
        ->and((float) $billing->retention_amount)->toBe(0.0)
        ->and(round($billing->net_amount, 2))->toBe(25000.0);
});

it('keeps an existing billing on its original rate when the setting changes', function () {
    $approver = makeApproverForRfp();
    $project  = makeProjectForRfp($approver);

    App\Models\Setting::set('retention_pct', '5');
    $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)', 'amount' => 25000, 'apply_retention' => true,
    ])->assertRedirect();

    // Admin raises the rate afterwards.
    App\Models\Setting::set('retention_pct', '10');

    $billing = App\Models\ProjectBilling::where('project_id', $project->id)->firstOrFail();
    $this->actingAs($approver)->post(route('hub.rfp.update', [$project, $billing]), [
        '_method' => 'patch',
        'billing_type' => 'Milestone (Progress)', 'amount' => 25000, 'apply_retention' => true,
    ])->assertRedirect();

    expect((float) $billing->fresh()->retention_pct)->toBe(5.0)
        ->and(round((float) $billing->fresh()->retention_amount, 2))->toBe(1190.48);

    // A brand new one picks up the current rate.
    $this->actingAs($approver)->post(route('hub.rfp.store', $project), [
        'billing_type' => 'Milestone (Progress)', 'amount' => 25000, 'apply_retention' => true,
    ])->assertRedirect();

    $latest = App\Models\ProjectBilling::where('project_id', $project->id)->latest('id')->firstOrFail();
    expect((float) $latest->retention_pct)->toBe(10.0);
});
