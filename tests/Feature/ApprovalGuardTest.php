<?php

use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Support\ApprovalFlow;
use Spatie\Permission\Models\Role;

function makeUserWithRoleForGuard(string $role, array $attributes = []): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

function makeProjectForGuard(User $engineer, string $department = 'Engineering'): Project
{
    return Project::create([
        'project_no'         => 'PRJ-GUARD-' . uniqid(),
        'title'              => 'Guard Test Project',
        'site'               => 'Main Plant',
        'asset_id'           => 'A1',
        'class_name'         => 'Minor',
        'priority'           => '1',
        'status_key'         => 'PLANNING',
        'work_force'         => 'In-House',
        'wr_no'              => 'WR-1',
        'wr_date'            => now(),
        'dept_owner'         => $department,
        'cost_code'          => 'CC-001',
        'category'           => 'General',
        'service_type'       => 'Repair',
        'deadline'           => now()->addDays(30),
        'created_by'         => $engineer->id,
        'project_manager_id' => $engineer->id,
    ]);
}

function makeRequestForGuard(User $requester, string $status = 'pending'): ProjectRequest
{
    return ProjectRequest::create([
        'request_no'   => 'REQ-GUARD-' . uniqid(),
        'title'        => 'Guard Request',
        'job_type'     => 'Repair',
        'job_location' => 'Main Plant',
        'description'  => 'Raised for an approval-guard test.',
        'requester_id' => $requester->id,
        'status'       => $status,
    ]);
}

/** An NTP as it existed before the approval chain — issued, holding no steps. */
function makeLegacyIssuedNtp(Project $project): ProjectNtp
{
    return ProjectNtp::create([
        'project_id'      => $project->id,
        'ntp_no'          => 'PMC-NTP-LEGACY-' . uniqid(),
        'contractor_name' => 'Acme Builders',
        'baseline_start'  => '2026-01-01',
        'baseline_end'    => '2026-06-01',
        'approved_cost'   => 100000,
        'status'          => 'issued',
        'issued_date'     => '2026-01-01',
    ]);
}

beforeEach(function () {
    $this->flow     = new ApprovalFlow;
    $this->engineer = makeUserWithRoleForGuard(User::ROLE_ENGINEER);
    $this->project  = makeProjectForGuard($this->engineer);
});

it('will not let an already-issued ntp be rejected', function () {
    $ntp = makeLegacyIssuedNtp($this->project);
    expect($ntp->approvalChainStarted())->toBeFalse();

    $division = makeUserWithRoleForGuard(User::ROLE_DIVISION_MANAGER);

    expect($this->flow->rejectNtp($ntp, $division))->toBeFalse()
        ->and($ntp->fresh()->status)->toBe('issued')
        // The refusal must not leave a freshly minted chain behind either.
        ->and($ntp->fresh()->approvalChainStarted())->toBeFalse();
});

it('will not let an already-issued ntp be approved again, even by an admin', function () {
    $ntp   = makeLegacyIssuedNtp($this->project);
    $admin = makeUserWithRoleForGuard(User::ROLE_ADMIN);

    expect($this->flow->approveNtp($ntp, $admin))->toBeFalse()
        ->and($ntp->fresh()->status)->toBe('issued');
});

it('refuses the review endpoints on an issued ntp', function () {
    $ntp      = makeLegacyIssuedNtp($this->project);
    $division = makeUserWithRoleForGuard(User::ROLE_DIVISION_MANAGER);

    $this->actingAs($division)
        ->patch(route('approvals.ntps.reject', $ntp), ['remarks' => 'changed my mind'])
        ->assertForbidden();

    expect($ntp->fresh()->status)->toBe('issued');
});

it('keeps the department step to the owning department\'s requestors', function () {
    $ntp = ProjectNtp::create([
        'project_id'      => $this->project->id,
        'ntp_no'          => 'PMC-NTP-DEPT-' . uniqid(),
        'contractor_name' => 'Acme Builders',
        'baseline_start'  => '2026-01-01',
        'baseline_end'    => '2026-06-01',
        'approved_cost'   => 100000,
        'status'          => 'pending_review',
    ]);
    $ntp->startApprovalChain();

    // Carries the right department but is not a department user.
    $imposter = makeUserWithRoleForGuard(User::ROLE_PMD_ASST_MANAGER, ['department' => 'Engineering']);
    expect($ntp->awaitingApprovalFrom($imposter))->toBeFalse();

    $requestor = makeUserWithRoleForGuard(User::ROLE_REQUESTOR, ['department' => 'Engineering']);
    expect($ntp->awaitingApprovalFrom($requestor))->toBeTrue();

    // A requestor from a different department is not being waited on either.
    $outsider = makeUserWithRoleForGuard(User::ROLE_REQUESTOR, ['department' => 'Mining']);
    expect($ntp->awaitingApprovalFrom($outsider))->toBeFalse();
});

it('lets an assistant manager settle the engineer step they are shown', function () {
    $requester = makeUserWithRoleForGuard(User::ROLE_REQUESTOR, ['department' => 'Engineering']);

    $projectRequest = makeRequestForGuard($requester);
    $projectRequest->startApprovalChain();

    $assistant = makeUserWithRoleForGuard(User::ROLE_ASSISTANT_MANAGER);

    // The policy shows them the buttons, so the flow must accept the click.
    expect($assistant->can('decide', $projectRequest))->toBeTrue()
        ->and($this->flow->approveRequest($projectRequest, $assistant))->toBeTrue()
        ->and($projectRequest->fresh()->status)->toBe('in_approval');
});

it('keeps the approvals portal standing when a project is soft-deleted', function () {
    $ntp = ProjectNtp::create([
        'project_id'      => $this->project->id,
        'ntp_no'          => 'PMC-NTP-ORPHAN-' . uniqid(),
        'contractor_name' => 'Acme Builders',
        'baseline_start'  => '2026-01-01',
        'baseline_end'    => '2026-06-01',
        'approved_cost'   => 100000,
        'status'          => 'pending_review',
    ]);
    $ntp->startApprovalChain();
    $ntp->approvals()->where('sequence', 1)->update(['status' => 'approved', 'acted_at' => now()]);

    $this->project->delete();

    $pmdAsst = makeUserWithRoleForGuard(User::ROLE_PMD_ASST_MANAGER);

    $this->actingAs($pmdAsst)
        ->get(route('approvals.index'))
        ->assertOk();
});

it('counts requests still climbing the chain as outstanding', function () {
    $requester = makeUserWithRoleForGuard(User::ROLE_REQUESTOR, ['department' => 'Engineering']);

    foreach (['pending', 'in_approval', 'approved', 'rejected'] as $status) {
        makeRequestForGuard($requester, $status);
    }

    $this->actingAs($this->engineer)
        ->get(route('dashboard'))
        ->assertOk()
        // pending + in_approval; the settled two are not outstanding.
        ->assertInertia(fn ($page) => $page->where('stats.pending_request', 2));
});
