<?php

use App\Models\Project;
use App\Models\ProjectBilling;
use App\Models\ProjectRfq;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

function makeEngineerForSub(): User
{
    Role::firstOrCreate(['name' => User::ROLE_ENGINEER]);

    $user = User::factory()->create();
    $user->assignRole(User::ROLE_ENGINEER);

    return $user;
}

/** The project form as the create screen posts it. */
function subProjectPayload(User $manager, array $overrides = []): array
{
    return array_merge([
        'title'           => 'Sub Test Project',
        'project_manager' => (string) $manager->id,
        'site'            => 'Main Plant',
        'asset_id'        => 'A1',
        'cls'             => 'Minor',
        'priority'        => '1',
        'status'          => 'PLANNING',
        'work_force'      => 'In-House',
        'wr_no'           => 'WR-1',
        'wr_date'         => now()->toDateString(),
        'dept_owner'      => 'Engineering',
        'cost_code'       => 'CC-001',
        'category'        => 'General',
        'service_type'    => 'Repair',
        'deadline'        => now()->addDays(30)->toDateString(),
        'project_type'    => 'minor',
        'project_cost'    => 100000,
    ], $overrides);
}

function makeRootProject(User $engineer): Project
{
    return Project::create([
        'project_no'         => 'PRJ-SUB-' . uniqid(),
        'title'              => 'Root Project',
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
}

/** Attach a child directly, bypassing the form. */
function makeChildOf(Project $parent, User $engineer, array $attributes = []): Project
{
    return Project::create(array_merge([
        'project_no'         => $parent->project_no . '-' . str_pad((string) ($parent->children()->count() + 1), 2, '0', STR_PAD_LEFT),
        'title'              => 'Child of ' . $parent->project_no,
        'parent_id'          => $parent->id,
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
    ], $attributes));
}

beforeEach(function () {
    Mail::fake();
    $this->engineer = makeEngineerForSub();
    $this->project  = makeRootProject($this->engineer);
});

// ── Creation without an NTP ──────────────────────────────────────────────

it('opens the sub-project form from a parent alone, with no ntp', function () {
    $this->actingAs($this->engineer)
        ->get(route('projects.create', ['parent' => $this->project->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sub_context.parent_id', $this->project->id)
            ->where('sub_context.depth', 2)
            ->where('next_project_no', $this->project->project_no . '-01'));
});

it('creates a sub-project without an ntp', function () {
    $this->actingAs($this->engineer)
        ->post(route('projects.store'), subProjectPayload($this->engineer, [
            'title'     => 'Civil Works Package',
            'parent_id' => $this->project->id,
        ]))
        ->assertRedirect();

    $child = Project::where('parent_id', $this->project->id)->firstOrFail();

    expect($child->project_no)->toBe($this->project->project_no . '-01')
        ->and($child->source_ntp_id)->toBeNull()
        ->and($child->depth())->toBe(2);
});

it('lets a sub-project have a sub-project of its own', function () {
    $child = makeChildOf($this->project, $this->engineer);

    $this->actingAs($this->engineer)
        ->post(route('projects.store'), subProjectPayload($this->engineer, ['parent_id' => $child->id]))
        ->assertRedirect();

    $grandchild = Project::where('parent_id', $child->id)->firstOrFail();

    expect($grandchild->depth())->toBe(3)
        ->and($grandchild->project_no)->toBe($child->project_no . '-01')
        ->and($grandchild->rootAncestor()->id)->toBe($this->project->id);
});

it('stops the tree at three levels deep', function () {
    $child      = makeChildOf($this->project, $this->engineer);
    $grandchild = makeChildOf($child, $this->engineer);

    expect($grandchild->canHaveSubProjects())->toBeFalse();

    $this->actingAs($this->engineer)
        ->get(route('projects.create', ['parent' => $grandchild->id]))
        ->assertStatus(422);

    $this->actingAs($this->engineer)
        ->post(route('projects.store'), subProjectPayload($this->engineer, ['parent_id' => $grandchild->id]))
        ->assertStatus(422);

    expect(Project::where('parent_id', $grandchild->id)->exists())->toBeFalse();
});

// ── Sub-projects are ordinary projects ───────────────────────────────────

it('gives a sub-project its own rfq and ntp sections', function () {
    $child = makeChildOf($this->project, $this->engineer);

    foreach (['rfq', 'ntp', 'subprojects'] as $section) {
        $this->actingAs($this->engineer)
            ->get(route('projects.hub.' . $section, $child))
            ->assertOk();
    }
});

it('lets a sub-project raise its own rfq', function () {
    $child = makeChildOf($this->project, $this->engineer);

    $this->actingAs($this->engineer)
        ->post(route('hub.rfq.store', $child), ['contractor_name' => 'Acme Builders'])
        ->assertRedirect();

    expect(ProjectRfq::where('project_id', $child->id)->exists())->toBeTrue();
});

it('lists a project\'s direct sub-projects in its hub', function () {
    $child      = makeChildOf($this->project, $this->engineer);
    $grandchild = makeChildOf($child, $this->engineer);

    $this->actingAs($this->engineer)
        ->get(route('projects.hub.subprojects', $this->project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('hub_data.subprojects', 1)
            ->where('hub_data.subprojects.0.project_no', $child->project_no)
            // The grandchild is listed under its own parent, not flattened here.
            ->where('hub_data.subprojects.0.children_count', 1)
            ->where('hub_data.can_add', true));

    $this->actingAs($this->engineer)
        ->get(route('projects.hub.subprojects', $grandchild))
        ->assertInertia(fn ($page) => $page->where('hub_data.can_add', false));
});

// ── Roll-ups across the tree ─────────────────────────────────────────────

it('rolls completion up one level at a time', function () {
    $this->project->update(['completion_percent' => 40]);
    $child      = makeChildOf($this->project, $this->engineer, ['completion_percent' => 20]);
    $grandchild = makeChildOf($child, $this->engineer, ['completion_percent' => 80]);

    // The child blends its own 20% with the grandchild's 80% → 50%.
    expect($child->fresh()->effectiveCompletionPercent())->toBe(50);

    // The root blends its own 40% with the child's rolled-up 50% → 45%.
    // Flattening the tree instead would have given (40+20+80)/3 = 47%.
    expect($this->project->fresh()->effectiveCompletionPercent())->toBe(45);

    expect($grandchild->fresh()->effectiveCompletionPercent())->toBe(80);
});

it('skips sub-projects that have not started when rolling up', function () {
    $this->project->update(['completion_percent' => 60]);
    makeChildOf($this->project, $this->engineer, ['completion_percent' => 0]);

    expect($this->project->fresh()->effectiveCompletionPercent())->toBe(60);
});

it('rolls a sub-sub-project\'s billing all the way up the tree', function () {
    $child      = makeChildOf($this->project, $this->engineer);
    $grandchild = makeChildOf($child, $this->engineer);

    $this->actingAs($this->engineer)
        ->post(route('hub.rfp.store', $grandchild), [
            'billing_type' => 'Progress Billing',
            'amount'       => 50000,
            'progress_pct' => 50,
        ])->assertRedirect();

    $billing = ProjectBilling::where('project_id', $grandchild->id)->firstOrFail();

    $this->actingAs($this->engineer)
        ->patch(route('hub.rfp.update-status', [$grandchild, $billing]), ['status' => 'approved'])
        ->assertRedirect();

    // What the billing actually releases, once retention is withheld.
    $released = (float) $billing->fresh()->amount - (float) $billing->fresh()->retention_amount;
    expect($released)->toBeGreaterThan(0.0);

    // It reaches the sub-sub-project, its parent and the root alike.
    expect((float) $grandchild->fresh()->budget_paid)->toBe($released)
        ->and((float) $child->fresh()->budget_paid)->toBe($released)
        ->and((float) $this->project->fresh()->budget_paid)->toBe($released);
});

it('counts the whole subtree in a project\'s id list', function () {
    $child      = makeChildOf($this->project, $this->engineer);
    $grandchild = makeChildOf($child, $this->engineer);

    expect($this->project->fresh()->subtreeIds())
        ->toEqualCanonicalizing([$this->project->id, $child->id, $grandchild->id]);
});

it('keeps a sub-project out of the main project list', function () {
    makeChildOf($this->project, $this->engineer);

    $this->actingAs($this->engineer)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});
