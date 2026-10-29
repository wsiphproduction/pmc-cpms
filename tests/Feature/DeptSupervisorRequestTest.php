<?php

use App\Models\Notification;
use App\Models\ProjectRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/** A user holding the role, optionally tied to a department. */
function supervisorFlowUser(string $role, ?string $department = null): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create(['department' => $department]);
    $user->assignRole($role);

    return $user;
}

/** A supervisor's request, raised through the form so its chain is written as in use. */
function supervisorRequest(User $supervisor): ProjectRequest
{
    test()->actingAs($supervisor)->post(route('requests.store'), [
        'title'         => 'Replace roof sheets',
        'job_type'      => 'civil',
        'description'   => 'Leaks over the warehouse.',
        'job_location'  => 'Site A',
        'costcode'      => 'CC-001',
        'opex'          => false,
        'capex'         => false,
        'for_budgeting' => true,
    ])->assertRedirect();

    return ProjectRequest::latest('id')->firstOrFail();
}

beforeEach(function () {
    $this->supervisor = supervisorFlowUser(User::ROLE_DEPT_SUPERVISOR, 'Mill');
    $this->manager    = supervisorFlowUser(User::ROLE_REQUESTOR, 'Mill');
    $this->engineer   = supervisorFlowUser(User::ROLE_ENGINEER);
});

it('puts the department user’s review in front of a supervisor’s request', function () {
    $pr = supervisorRequest($this->supervisor);

    expect($pr->status)->toBe('pending')
        ->and($pr->awaitingDepartmentReview())->toBeTrue()
        ->and(collect($pr->approvalTimeline())->pluck('role')->all())
        ->toBe([User::ROLE_REQUESTOR, User::ROLE_ENGINEER, User::ROLE_PMD_ASST_MANAGER, User::ROLE_PMD_DEPT_MANAGER]);

    // The department user is called on; the engineers are not, yet.
    expect(Notification::where('recipient', $this->manager->id)->exists())->toBeTrue()
        ->and(Notification::where('recipient', $this->engineer->id)->exists())->toBeFalse();
});

it('leaves a department user’s own request starting at the engineer', function () {
    $pr = supervisorRequest($this->manager);

    expect($pr->awaitingDepartmentReview())->toBeFalse()
        ->and($pr->currentApprovalRole())->toBe(User::ROLE_ENGINEER);

    $this->actingAs($this->engineer)->get(route('requests.show', $pr))->assertOk();
});

it('hides a supervisor’s request from PMD until the department user approves it', function () {
    $pr = supervisorRequest($this->supervisor);

    $this->actingAs($this->engineer)
        ->get(route('requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', 0));
    $this->actingAs($this->engineer)->get(route('requests.show', $pr))->assertForbidden();

    // Nor can the engineer jump the queue.
    $this->actingAs($this->engineer)
        ->patch(route('requests.update', $pr), ['status' => 'approved'])
        ->assertForbidden();

    $this->actingAs($this->manager)
        ->get(route('requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.can.decide', true)
            ->where('request_reviews_count', 1));

    $this->actingAs($this->manager)
        ->patch(route('requests.update', $pr), ['status' => 'approved'])
        ->assertRedirect();

    $pr->refresh();

    expect($pr->status)->toBe('pending')
        ->and($pr->currentApprovalRole())->toBe(User::ROLE_ENGINEER)
        ->and(Notification::where('recipient', $this->engineer->id)->exists())->toBeTrue();

    $this->actingAs($this->engineer)
        ->get(route('requests.show', $pr))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('projectRequest.can.decide', true));

    // From here the usual chain runs: the engineer endorses it to PMD.
    $this->actingAs($this->engineer)
        ->patch(route('requests.update', $pr), ['status' => 'approved'])
        ->assertRedirect();

    expect($pr->fresh()->status)->toBe('in_approval')
        ->and($pr->fresh()->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER);
});

it('tells the PMD Department Manager once the department approves, so they can assign an engineer', function () {
    $pmdManager = supervisorFlowUser(User::ROLE_PMD_DEPT_MANAGER);
    $pr = supervisorRequest($this->supervisor);

    // Nothing while it is still with the department.
    expect(Notification::where('recipient', $pmdManager->id)->exists())->toBeFalse();

    $this->actingAs($this->manager)->patch(route('requests.update', $pr), ['status' => 'approved']);

    $notice = Notification::where('recipient', $pmdManager->id)->sole();

    expect($notice->message)->toContain("#{$pr->request_no}")
        ->toContain('approved by the Department Manager of Mill')
        ->toContain('You can now assign a project engineer')
        ->and($notice->link)->toBe(route('requests.show', $pr->id, absolute: false));
});

it('tells the PMD Department Manager of a request a department manager raises themselves', function () {
    $pmdManager = supervisorFlowUser(User::ROLE_PMD_DEPT_MANAGER);
    $pr = supervisorRequest($this->manager);

    expect(Notification::where('recipient', $pmdManager->id)->sole()->message)
        ->toContain("#{$pr->request_no}")
        ->toContain('You can now assign a project engineer');
});

it('puts a supervisor’s request on hold when the department manager comments, until the supervisor edits it', function () {
    $pr = supervisorRequest($this->supervisor);

    $this->actingAs($this->manager)
        ->postJson(route('comments.store', $pr), ['content' => 'Please attach the site photos.'])
        ->assertOk();

    $pr->refresh();

    expect($pr->status)->toBe('hold')
        ->and($pr->awaitingDepartmentReview())->toBeTrue()
        ->and(Notification::where('recipient', $this->supervisor->id)
            ->where('message', 'like', '%ON HOLD after a comment from the Department Manager%')->exists())->toBeTrue();

    // Still the department's: PMD sees nothing of it.
    $this->actingAs($this->engineer)->get(route('requests.show', $pr))->assertForbidden();

    $this->actingAs($this->supervisor)
        ->put(route('requests.update', $pr), [
            'title'         => 'Replace roof sheets',
            'job_type'      => 'civil',
            'description'   => 'Leaks over the warehouse. Photos attached.',
            'job_location'  => 'Site A',
            'costcode'      => 'CC-001',
            'opex'          => false,
            'capex'         => false,
            'for_budgeting' => true,
        ])
        ->assertRedirect();

    $pr->refresh();

    expect($pr->status)->toBe('pending')
        ->and($pr->awaitingDepartmentReview())->toBeTrue();
});

it('lets the department manager approve or lift their own hold', function () {
    $pr = supervisorRequest($this->supervisor);
    $this->actingAs($this->manager)->postJson(route('comments.store', $pr), ['content' => 'Check the cost code.']);

    $this->actingAs($this->manager)
        ->patch(route('requests.update', $pr), ['status' => 'resume'])
        ->assertRedirect();

    expect($pr->fresh()->status)->toBe('pending');

    $this->actingAs($this->manager)->postJson(route('comments.store', $pr), ['content' => 'Never mind, it is fine.']);
    expect($pr->fresh()->status)->toBe('hold');

    // Approving straight from the hold sends it on to the engineers as For Approval.
    $this->actingAs($this->manager)
        ->patch(route('requests.update', $pr), ['status' => 'approved'])
        ->assertRedirect();

    $pr->refresh();

    expect($pr->status)->toBe('pending')
        ->and($pr->status_before_hold)->toBeNull()
        ->and($pr->currentApprovalRole())->toBe(User::ROLE_ENGINEER);
});

it('leaves a department manager’s comment on a request past their review as a comment only', function () {
    $pr = supervisorRequest($this->supervisor);
    $this->actingAs($this->manager)->patch(route('requests.update', $pr), ['status' => 'approved']);

    $this->actingAs($this->manager)->postJson(route('comments.store', $pr), ['content' => 'Thanks, PMD.']);

    expect($pr->fresh()->status)->toBe('pending');
});

it('keeps the PMD queue clear of a request still with the department', function () {
    $pr = supervisorRequest($this->supervisor);

    expect(ProjectRequest::awaitingRole(User::ROLE_ENGINEER)->pluck('id')->all())->toBe([]);

    $this->actingAs($this->manager)->patch(route('requests.update', $pr), ['status' => 'approved']);

    expect(ProjectRequest::awaitingRole(User::ROLE_ENGINEER)->pluck('id')->all())->toBe([$pr->id]);
});

it('lets the department user reject a supervisor’s request, which PMD then never sees', function () {
    $pr = supervisorRequest($this->supervisor);

    $this->actingAs($this->manager)
        ->patch(route('requests.update', $pr), ['status' => 'rejected'])
        ->assertRedirect();

    expect($pr->fresh()->status)->toBe('rejected');

    $this->actingAs($this->engineer)->get(route('requests.show', $pr))->assertForbidden();
});

it('limits the department user to approving or rejecting', function () {
    $pr = supervisorRequest($this->supervisor);

    $this->actingAs($this->manager)
        ->patch(route('requests.update', $pr), ['status' => 'hold'])
        ->assertForbidden();

    expect($pr->fresh()->status)->toBe('pending');
});

it('keeps another department’s user out of the review', function () {
    $pr = supervisorRequest($this->supervisor);
    $elsewhere = supervisorFlowUser(User::ROLE_REQUESTOR, 'Assay');

    $this->actingAs($elsewhere)->get(route('requests.show', $pr))->assertForbidden();
    $this->actingAs($elsewhere)
        ->patch(route('requests.update', $pr), ['status' => 'approved'])
        ->assertForbidden();

    // Nor does a supervisor review their own request.
    $this->actingAs($this->supervisor)
        ->patch(route('requests.update', $pr), ['status' => 'approved'])
        ->assertForbidden();

    expect($pr->fresh()->awaitingDepartmentReview())->toBeTrue();
});

it('lets the supervisor edit the request while it is with the department', function () {
    $pr = supervisorRequest($this->supervisor);

    $this->actingAs($this->supervisor)
        ->get(route('requests.edit', $pr))
        ->assertOk();
});

it('leaves the NTP department signature with the department user, not the supervisor who raised it', function () {
    $pr = ProjectRequest::factory()->create(['requester_id' => $this->supervisor->id, 'status' => 'approved']);

    $project = \App\Models\Project::create([
        'project_no' => 'PRJ-SUP-0001', 'title' => 'Supervisor Project', 'site' => 'Main Plant',
        'asset_id' => 'Asset A', 'class_name' => 'Minor', 'priority' => 'P1 - Urgent',
        'status_key' => 'ONGOING', 'work_force' => 'Contractor', 'wr_no' => 'WR-1', 'wr_date' => now(),
        'dept_owner' => 'Mill', 'cost_code' => 'CC-001', 'category' => 'General',
        'service_type' => 'Repair', 'deadline' => now()->addDays(30),
        'created_by' => $this->engineer->id, 'project_request_id' => $pr->id,
    ]);

    $ntp = \App\Models\ProjectNtp::create([
        'project_id' => $project->id, 'ntp_no' => 'NTP-SUP-1', 'contractor_name' => 'ABC Builders',
        'baseline_start' => '2026-01-01', 'baseline_end' => '2026-06-01',
        'approved_cost' => 250000, 'status' => 'pending_review',
    ]);
    $ntp->startApprovalChain();

    $step = $ntp->approvals()->where('role', User::ROLE_REQUESTOR)->firstOrFail();

    expect($ntp->approvalStepAuthorizes($step, $this->supervisor))->toBeFalse()
        ->and($ntp->approvalStepAuthorizes($step, $this->manager))->toBeTrue();
});

it('requires a department on a supervisor account', function () {
    $admin = supervisorFlowUser(User::ROLE_ADMIN);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name'     => 'Sup',
            'email'    => 'sup@example.com',
            'password' => 'password123',
            'role'     => User::ROLE_DEPT_SUPERVISOR,
        ])
        ->assertSessionHasErrors('department');

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name'       => 'Sup',
            'email'      => 'sup@example.com',
            'password'   => 'password123',
            'role'       => User::ROLE_DEPT_SUPERVISOR,
            'department' => 'Mill',
        ])
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'sup@example.com')->value('department'))->toBe('Mill');
});
