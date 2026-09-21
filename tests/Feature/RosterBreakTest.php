<?php

use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRequest;
use App\Models\RosterBreak;
use App\Models\User;
use App\Support\ApprovalFlow;
use Spatie\Permission\Models\Role;

function makeUserWithRoleForBreak(string $role, array $attributes = []): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

/** A request already endorsed by the engineer and PMD assistant manager — sitting with the dept manager. */
function makeRequestAtDeptManager(User $requester, User $engineer, User $pmdAsst): ProjectRequest
{
    $projectRequest = ProjectRequest::create([
        'request_no'   => 'REQ-BREAK-' . uniqid(),
        'title'        => 'Roster Break Request',
        'job_type'     => 'Repair',
        'job_location' => 'Main Plant',
        'description'  => 'Raised for a roster-break test.',
        'requester_id' => $requester->id,
        'status'       => 'in_approval',
    ]);
    $projectRequest->startApprovalChain();
    $projectRequest->recordApproval($engineer);
    $projectRequest->recordApproval($pmdAsst);

    return $projectRequest->fresh();
}

function makeNtpAtDeptManager(User $engineer, User $pmdAsst): ProjectNtp
{
    $project = Project::create([
        'project_no'         => 'PRJ-BREAK-' . uniqid(),
        'title'              => 'Roster Break Project',
        'site'               => 'Main Plant',
        'asset_id'           => 'A1',
        'class_name'         => 'Minor',
        'priority'           => '1',
        'status_key'         => 'PLANNING',
        'work_force'         => 'In-House',
        'wr_no'              => 'WR-1',
        'wr_date'            => now(),
        'dept_owner'         => 'Engineering',
        'cost_code'          => 'CC-001',
        'category'           => 'General',
        'service_type'       => 'Repair',
        'deadline'           => now()->addDays(30),
        'created_by'         => $engineer->id,
        'project_manager_id' => $engineer->id,
    ]);

    $ntp = ProjectNtp::create([
        'project_id'      => $project->id,
        'ntp_no'          => 'PMC-NTP-BREAK-' . uniqid(),
        'contractor_name' => 'Acme Builders',
        'baseline_start'  => '2026-01-01',
        'baseline_end'    => '2026-06-01',
        'approved_cost'   => 100000,
        'status'          => 'pending_review',
        'created_by'      => $engineer->id,
    ]);
    $ntp->startApprovalChain();
    $ntp->recordApproval($pmdAsst);

    return $ntp->fresh();
}

beforeEach(function () {
    $this->flow      = new ApprovalFlow;
    $this->engineer  = makeUserWithRoleForBreak(User::ROLE_ENGINEER);
    $this->requester = makeUserWithRoleForBreak(User::ROLE_REQUESTOR, ['department' => 'Engineering']);
    $this->manager   = makeUserWithRoleForBreak(User::ROLE_PMD_DEPT_MANAGER);
    $this->pmdAsst   = makeUserWithRoleForBreak(User::ROLE_PMD_ASST_MANAGER);
    // A second assistant manager, to be named OIC — so the OIC's own
    // signature and their cover are given by different people.
    $this->oic       = makeUserWithRoleForBreak(User::ROLE_PMD_ASST_MANAGER);
});

// ── Scheduling ────────────────────────────────────────────────────────────

it('lets the pmd dept manager schedule a break and tells the oic', function () {
    $this->actingAs($this->manager)
        ->post(route('roster-break.store'), [
            'oic_user_id' => $this->oic->id,
            'starts_on'   => today()->addDays(3)->toDateString(),
            'ends_on'     => today()->addDays(10)->toDateString(),
            'notes'       => 'Back on the 11th.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $break = RosterBreak::first();

    expect($break)->not->toBeNull()
        ->and($break->user_id)->toBe($this->manager->id)
        ->and($break->oic_user_id)->toBe($this->oic->id)
        ->and($break->role)->toBe(User::ROLE_PMD_DEPT_MANAGER)
        ->and($break->notes)->toBe('Back on the 11th.');

    expect(Notification::where('recipient', $this->oic->id)
        ->where('message', 'like', '%named you OIC%')
        ->where('message', 'like', '%Notes: Back on the 11th.%')
        ->exists())->toBeTrue();
});

it('keeps the roster break page to the pmd dept manager', function () {
    $this->actingAs($this->pmdAsst)->get(route('roster-break.index'))->assertForbidden();
    $this->actingAs($this->engineer)->get(route('roster-break.index'))->assertForbidden();
    $this->actingAs($this->manager)->get(route('roster-break.index'))->assertOk();
});

it('only accepts a pmd assistant manager as oic', function () {
    $this->actingAs($this->manager)
        ->from(route('roster-break.index'))
        ->post(route('roster-break.store'), [
            'oic_user_id' => $this->engineer->id,
            'starts_on'   => today()->toDateString(),
            'ends_on'     => today()->addDays(2)->toDateString(),
        ])
        ->assertSessionHasErrors('oic_user_id');

    $this->actingAs($this->manager)
        ->from(route('roster-break.index'))
        ->post(route('roster-break.store'), [
            'oic_user_id' => $this->manager->id,
            'starts_on'   => today()->toDateString(),
            'ends_on'     => today()->addDays(2)->toDateString(),
        ])
        ->assertSessionHasErrors('oic_user_id');

    expect(RosterBreak::count())->toBe(0);
});

it('refuses a break in the past, an inverted range, or one overlapping another', function () {
    $post = fn (array $data) => $this->actingAs($this->manager)
        ->from(route('roster-break.index'))
        ->post(route('roster-break.store'), ['oic_user_id' => $this->oic->id] + $data);

    $post(['starts_on' => today()->subDays(5)->toDateString(), 'ends_on' => today()->subDay()->toDateString()])
        ->assertSessionHasErrors('ends_on');

    $post(['starts_on' => today()->addDays(5)->toDateString(), 'ends_on' => today()->addDays(2)->toDateString()])
        ->assertSessionHasErrors('ends_on');

    $post(['starts_on' => today()->addDays(2)->toDateString(), 'ends_on' => today()->addDays(6)->toDateString()])
        ->assertSessionHasNoErrors();

    // Straddles the end of the break just booked.
    $post(['starts_on' => today()->addDays(6)->toDateString(), 'ends_on' => today()->addDays(9)->toDateString()])
        ->assertSessionHasErrors('starts_on');

    expect(RosterBreak::count())->toBe(1);
});

it('lets the manager change or cancel a break that has not ended', function () {
    $break = $this->manager->rosterBreaks()->create([
        'oic_user_id' => $this->oic->id,
        'role'        => User::ROLE_PMD_DEPT_MANAGER,
        'starts_on'   => today()->addDays(2),
        'ends_on'     => today()->addDays(4),
    ]);

    $this->actingAs($this->manager)
        ->put(route('roster-break.update', $break), [
            'oic_user_id' => $this->pmdAsst->id,
            'starts_on'   => today()->addDays(2)->toDateString(),
            'ends_on'     => today()->addDays(6)->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    expect($break->fresh()->oic_user_id)->toBe($this->pmdAsst->id)
        ->and($break->fresh()->ends_on->toDateString())->toBe(today()->addDays(6)->toDateString());

    // The replaced OIC hears they are off the hook.
    expect(Notification::where('recipient', $this->oic->id)->where('message', 'like', '%no longer its OIC%')->exists())->toBeTrue();

    $this->actingAs($this->manager)->delete(route('roster-break.destroy', $break))->assertRedirect();
    expect(RosterBreak::count())->toBe(0);
});

it('leaves a finished break alone and keeps other managers out', function () {
    $ended = $this->manager->rosterBreaks()->create([
        'oic_user_id' => $this->oic->id,
        'role'        => User::ROLE_PMD_DEPT_MANAGER,
        'starts_on'   => today()->subDays(10),
        'ends_on'     => today()->subDays(5),
    ]);

    $this->actingAs($this->manager)->delete(route('roster-break.destroy', $ended))->assertStatus(422);
    expect(RosterBreak::count())->toBe(1);

    // The dept manager is a singleton role; a second account faking it still
    // may not touch somebody else's break.
    $other = makeUserWithRoleForBreak(User::ROLE_PMD_DEPT_MANAGER);
    $this->actingAs($other)->delete(route('roster-break.destroy', $ended))->assertForbidden();
});

// ── Cover ─────────────────────────────────────────────────────────────────

it('puts the dept manager queue in front of the oic while the break runs', function () {
    $projectRequest = makeRequestAtDeptManager($this->requester, $this->engineer, $this->pmdAsst);
    $ntp = makeNtpAtDeptManager($this->engineer, $this->pmdAsst);

    // Not yet an OIC: their own queue holds neither (the asst step is signed).
    expect($this->oic->actsAs(User::ROLE_PMD_DEPT_MANAGER))->toBeFalse()
        ->and($projectRequest->awaitingApprovalFrom($this->oic))->toBeFalse()
        ->and($ntp->awaitingApprovalFrom($this->oic))->toBeFalse();

    $this->manager->rosterBreaks()->create([
        'oic_user_id' => $this->oic->id,
        'role'        => User::ROLE_PMD_DEPT_MANAGER,
        'starts_on'   => today(),
        'ends_on'     => today()->addDays(7),
        'notes'       => 'Call me only for anything above 1M.',
    ]);

    $oic = $this->oic->fresh();

    expect($oic->actsAs(User::ROLE_PMD_DEPT_MANAGER))->toBeTrue()
        ->and($projectRequest->awaitingApprovalFrom($oic))->toBeTrue()
        ->and($ntp->awaitingApprovalFrom($oic))->toBeTrue();

    $this->actingAs($oic)
        ->get(route('approvals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('role', User::ROLE_PMD_ASST_MANAGER)
            ->where('oic_for.manager', $this->manager->name)
            ->where('oic_for.role_label', 'PMD Department Manager')
            ->where('oic_for.notes', 'Call me only for anything above 1M.')
            ->where('shows_requests', true)
            ->where('requests.0.id', $projectRequest->id)
            ->where('ntps.0.id', $ntp->id)
            ->where('approvals_count', 2));

    // The manager still sees their own queue, with a note about who covers.
    $this->actingAs($this->manager->fresh())
        ->get(route('approvals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('on_break.oic', $oic->name)
            ->where('requests.0.id', $projectRequest->id)
            ->where('approvals_count', 2));
});

it('does not cover before the break starts or after it ends', function () {
    $projectRequest = makeRequestAtDeptManager($this->requester, $this->engineer, $this->pmdAsst);

    $this->manager->rosterBreaks()->create([
        'oic_user_id' => $this->oic->id,
        'role'        => User::ROLE_PMD_DEPT_MANAGER,
        'starts_on'   => today()->addDays(2),
        'ends_on'     => today()->addDays(4),
    ]);
    $this->manager->rosterBreaks()->create([
        'oic_user_id' => $this->oic->id,
        'role'        => User::ROLE_PMD_DEPT_MANAGER,
        'starts_on'   => today()->subDays(9),
        'ends_on'     => today()->subDay(),
    ]);

    expect($this->oic->fresh()->actsAs(User::ROLE_PMD_DEPT_MANAGER))->toBeFalse()
        ->and($this->flow->approveRequest($projectRequest, $this->oic->fresh()))->toBeFalse()
        ->and($projectRequest->fresh()->status)->toBe('in_approval');

    $this->actingAs($this->oic->fresh())
        ->patch(route('approvals.requests.approve', $projectRequest))
        ->assertForbidden();
});

it('records an oic signature as given on the manager\'s behalf', function () {
    $projectRequest = makeRequestAtDeptManager($this->requester, $this->engineer, $this->pmdAsst);

    $this->manager->rosterBreaks()->create([
        'oic_user_id' => $this->oic->id,
        'role'        => User::ROLE_PMD_DEPT_MANAGER,
        'starts_on'   => today(),
        'ends_on'     => today(),
    ]);

    $this->actingAs($this->oic->fresh())
        ->patch(route('approvals.requests.approve', $projectRequest), ['remarks' => 'Signed while the manager is off roster.'])
        ->assertRedirect();

    $projectRequest->refresh();
    $step = $projectRequest->approvals()->where('role', User::ROLE_PMD_DEPT_MANAGER)->first();

    expect($projectRequest->status)->toBe('approved')
        ->and($step->status)->toBe('approved')
        ->and($step->user_id)->toBe($this->oic->id)
        ->and($step->on_behalf_of_user_id)->toBe($this->manager->id)
        ->and($step->officeLabel())->toBe('PMD Department Manager (OIC)');

    $timeline = collect($projectRequest->approvalTimeline())->firstWhere('role', User::ROLE_PMD_DEPT_MANAGER);
    expect($timeline['actor'])->toBe($this->oic->name)
        ->and($timeline['on_behalf_of'])->toBe($this->manager->name);

    // The manager's own signature never carries the marker.
    $own = makeRequestAtDeptManager($this->requester, $this->engineer, $this->pmdAsst);
    $this->flow->approveRequest($own, $this->manager->fresh());
    $ownStep = $own->approvals()->where('role', User::ROLE_PMD_DEPT_MANAGER)->first();
    expect($ownStep->on_behalf_of_user_id)->toBeNull()
        ->and($ownStep->officeLabel())->toBe('PMD Department Manager');
});

it('lets the oic carry an ntp past the dept manager step', function () {
    $ntp = makeNtpAtDeptManager($this->engineer, $this->pmdAsst);

    $this->manager->rosterBreaks()->create([
        'oic_user_id' => $this->oic->id,
        'role'        => User::ROLE_PMD_DEPT_MANAGER,
        'starts_on'   => today()->subDay(),
        'ends_on'     => today()->addDay(),
    ]);

    $this->actingAs($this->oic->fresh())
        ->patch(route('approvals.ntps.approve', $ntp))
        ->assertRedirect();

    $ntp->refresh();

    expect($ntp->currentApprovalRole())->toBe(User::ROLE_DIVISION_MANAGER)
        ->and($ntp->approvals()->where('role', User::ROLE_PMD_DEPT_MANAGER)->value('on_behalf_of_user_id'))->toBe($this->manager->id);

    // The engineer is told the (OIC) office signed it.
    expect(Notification::where('recipient', $this->engineer->id)
        ->where('message', 'like', '%approved by the PMD Department Manager (OIC)%')
        ->exists())->toBeTrue();
});

it('notifies the oic alongside the manager when an item reaches the dept manager step', function () {
    $this->manager->rosterBreaks()->create([
        'oic_user_id' => $this->oic->id,
        'role'        => User::ROLE_PMD_DEPT_MANAGER,
        'starts_on'   => today(),
        'ends_on'     => today()->addDays(3),
    ]);

    $projectRequest = ProjectRequest::create([
        'request_no'   => 'REQ-NOTIFY-' . uniqid(),
        'title'        => 'Notify Request',
        'job_type'     => 'Repair',
        'job_location' => 'Main Plant',
        'description'  => 'Raised for a roster-break notification test.',
        'requester_id' => $this->requester->id,
        'status'       => 'in_approval',
    ]);
    $projectRequest->startApprovalChain();
    $projectRequest->recordApproval($this->engineer);

    // The assistant manager's signature hands it to the dept manager.
    $this->flow->approveRequest($projectRequest, $this->pmdAsst);

    $awaiting = fn (int $userId) => Notification::where('recipient', $userId)
        ->where('message', 'like', '%is awaiting your approval%')
        ->exists();

    expect($awaiting($this->manager->id))->toBeTrue()
        ->and($awaiting($this->oic->id))->toBeTrue();
});
