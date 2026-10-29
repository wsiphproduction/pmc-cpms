<?php

use App\Models\Notification;
use App\Models\ProjectRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function earlyAssignUser(string $role, ?string $department = null): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create(['department' => $department]);
    $user->assignRole($role);

    return $user;
}

beforeEach(function () {
    $this->pmdManager = earlyAssignUser(User::ROLE_PMD_DEPT_MANAGER);
    $this->pmdAsst    = earlyAssignUser(User::ROLE_PMD_ASST_MANAGER);
    $this->assigned   = earlyAssignUser(User::ROLE_ENGINEER);
    $this->other      = earlyAssignUser(User::ROLE_ENGINEER);

    $this->pr = ProjectRequest::factory()->create(['status' => 'pending']);
    $this->pr->startApprovalChain();
});

it('lets the PMD Department Manager assign an engineer before the request reaches them', function () {
    $this->actingAs($this->pmdManager)
        ->get(route('requests.show', $this->pr))
        ->assertInertia(fn (Assert $page) => $page
            ->where('projectRequest.can.assignEngineer', true)
            ->has('engineers', 2));

    $this->actingAs($this->pmdManager)
        ->patch(route('requests.assign-engineer', $this->pr), ['engineer_id' => $this->assigned->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->pr->fresh()->assigned_engineer_id)->toBe($this->assigned->id)
        ->and(Notification::where('recipient', $this->assigned->id)->exists())->toBeTrue();

    // Still with the engineers: only the one named sees it and may endorse it.
    $this->actingAs($this->other)->get(route('requests.show', $this->pr))->assertForbidden();
    $this->actingAs($this->other)
        ->patch(route('requests.update', $this->pr), ['status' => 'approved'])
        ->assertForbidden();

    $this->actingAs($this->assigned)
        ->patch(route('requests.update', $this->pr), ['status' => 'approved'])
        ->assertRedirect();

    expect($this->pr->fresh()->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER);
});

it('keeps the early assignment through the final signature unless it names someone else', function () {
    $this->actingAs($this->pmdManager)
        ->patch(route('requests.assign-engineer', $this->pr), ['engineer_id' => $this->assigned->id]);

    $this->actingAs($this->assigned)->patch(route('requests.update', $this->pr), ['status' => 'approved']);
    $this->actingAs($this->pmdAsst)->patch(route('approvals.requests.approve', $this->pr));

    $this->actingAs($this->pmdManager)
        ->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requests.0.assigned_engineer_id', $this->assigned->id));

    // Signed without naming anyone: the earlier choice stands.
    $this->actingAs($this->pmdManager)
        ->patch(route('approvals.requests.approve', $this->pr))
        ->assertRedirect();

    expect($this->pr->fresh())
        ->status->toBe('approved')
        ->assigned_engineer_id->toBe($this->assigned->id);
});

it('lets the final signature change an early assignment', function () {
    $this->actingAs($this->pmdManager)
        ->patch(route('requests.assign-engineer', $this->pr), ['engineer_id' => $this->assigned->id]);

    $this->actingAs($this->assigned)->patch(route('requests.update', $this->pr), ['status' => 'approved']);
    $this->actingAs($this->pmdAsst)->patch(route('approvals.requests.approve', $this->pr));

    $this->actingAs($this->pmdManager)
        ->patch(route('approvals.requests.approve', $this->pr), ['engineer_id' => $this->other->id]);

    expect($this->pr->fresh()->assigned_engineer_id)->toBe($this->other->id);
});

it('can clear an assignment', function () {
    $this->pr->update(['assigned_engineer_id' => $this->assigned->id]);

    $this->actingAs($this->pmdManager)
        ->patch(route('requests.assign-engineer', $this->pr), ['engineer_id' => null])
        ->assertRedirect();

    expect($this->pr->fresh()->assigned_engineer_id)->toBeNull();
});

it('leaves assigning to the PMD Department Manager', function () {
    foreach ([$this->pmdAsst, $this->other] as $user) {
        $this->actingAs($user)
            ->patch(route('requests.assign-engineer', $this->pr), ['engineer_id' => $this->assigned->id])
            ->assertForbidden();
    }

    $this->actingAs($this->pmdAsst)
        ->get(route('requests.show', $this->pr))
        ->assertInertia(fn (Assert $page) => $page
            ->where('projectRequest.can.assignEngineer', false)
            ->has('engineers', 0));

    expect($this->pr->fresh()->assigned_engineer_id)->toBeNull();
});

it('refuses a non-engineer, and a request that is rejected', function () {
    $this->actingAs($this->pmdManager)
        ->patch(route('requests.assign-engineer', $this->pr), ['engineer_id' => $this->pmdAsst->id])
        ->assertSessionHasErrors('engineer_id');

    $this->pr->update(['status' => 'rejected']);

    $this->actingAs($this->pmdManager)
        ->patch(route('requests.assign-engineer', $this->pr), ['engineer_id' => $this->assigned->id])
        ->assertForbidden();
});

it('waits for the department review on a supervisor’s request', function () {
    $supervisor = earlyAssignUser(User::ROLE_DEPT_SUPERVISOR, 'Mill');
    $pr = ProjectRequest::factory()->create(['status' => 'pending', 'requester_id' => $supervisor->id]);
    $pr->startApprovalChain();
    $pr->startDepartmentReview();

    $this->actingAs($this->pmdManager)
        ->patch(route('requests.assign-engineer', $pr), ['engineer_id' => $this->assigned->id])
        ->assertForbidden();
});
