<?php

use App\Models\Category;
use App\Models\CostCode;
use App\Models\Department;
use App\Models\MasterClass;
use App\Models\MasterStatus;
use App\Models\Priority;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ServiceType;
use App\Models\Site;
use App\Models\Structure;
use App\Models\User;
use App\Models\WorkForce;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeApproverUser(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function seedProjectMasterData(): void
{
    Site::create(['name' => 'Main Plant']);
    Structure::create(['name' => 'Asset A']);
    Department::create(['name' => 'Engineering']);
    MasterClass::create(['name' => 'Major']);
    Priority::create(['name' => 'P1 - Urgent']);
    MasterStatus::create(['name' => 'For Planning']);
    WorkForce::create(['name' => 'In-house Team']);
    CostCode::create(['name' => 'CC-001']);
    Category::create(['name' => 'Renovation']);
    ServiceType::create(['name' => 'Design-Build']);
}

function projectPayload(User $manager, array $overrides = []): array
{
    return array_merge([
        'project_type' => 'minor',
        'title' => 'Cooling Tower Upgrade',
        'project_manager' => (string) $manager->id,
        'site' => 'Main Plant',
        'asset_id' => 'Asset A',
        'cls' => 'Major',
        'priority' => 'P1 - Urgent',
        'status' => 'PLANNING',
        'work_force' => 'In-house Team',
        'wr_no' => '1234',
        'wr_date' => '2026-05-04',
        'dept_owner' => 'Engineering',
        'cost_code' => 'CC-001',
        'category' => 'Renovation',
        'service_type' => 'Design-Build',
        'deadline' => '2026-06-04',
        'owner_email' => 'owner@example.com',
        'structure_type' => 'Asset A',
        'jip' => true,
        'need_civil' => true,
        'need_electrical' => false,
        'need_mechanical' => true,
        'notes' => 'Initial project notes.',
    ], $overrides);
}

it('renders project create with master data options', function () {
    $user = makeApproverUser();
    seedProjectMasterData();

    $this->actingAs($user)
        ->get(route('projects.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('project-management/create')
            ->where('sites.0.label', 'Main Plant')
            ->where('classes.0.label', 'Major')
            ->where('costCodes.0.label', 'CC-001')
        );
});

it('stores and shows a project', function () {
    $user = makeApproverUser();
    $manager = User::factory()->create(['name' => 'PM Engineer']);
    seedProjectMasterData();

    $this->actingAs($user)
        ->post(route('projects.store'), projectPayload($manager))
        ->assertRedirect();

    $project = Project::firstOrFail();

    expect($project->project_no)->toStartWith('PRJ-' . now()->format('Y') . '-');
    expect($project->project_manager_name)->toBe('PM Engineer');

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('project-management/show')
            ->where('project.title', 'Cooling Tower Upgrade')
            ->where('project.status_key', 'PLANNING')
        );
});

it('updates project status and writes a log', function () {
    $user = makeApproverUser();
    $manager = User::factory()->create();
    seedProjectMasterData();

    $this->actingAs($user)->post(route('projects.store'), projectPayload($manager));
    $project = Project::firstOrFail();

    $this->actingAs($user)
        ->patch(route('projects.update-status', $project), [
            'status_key' => 'ONGOING',
            'remarks' => 'Work started.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'status_key' => 'ONGOING',
    ]);

    $this->assertDatabaseHas('project_status_logs', [
        'project_id' => $project->id,
        'status_key' => 'ONGOING',
        'remarks' => 'Work started.',
    ]);
});

it('lets an approver edit only the projects they created', function () {
    $creator = makeApproverUser();
    $otherApprover = makeApproverUser();
    $manager = User::factory()->create();
    seedProjectMasterData();

    $this->actingAs($creator)->post(route('projects.store'), projectPayload($manager));
    $project = Project::firstOrFail();

    $this->actingAs($creator)
        ->get(route('projects.edit', $project))
        ->assertOk();

    $this->actingAs($otherApprover)
        ->get(route('projects.edit', $project))
        ->assertForbidden();

    $this->actingAs($otherApprover)
        ->patch(route('projects.update-status', $project), ['status_key' => 'ONGOING'])
        ->assertForbidden();
});

it('lets an admin edit any project regardless of creator', function () {
    Role::firstOrCreate(['name' => 'admin']);
    $creator = makeApproverUser();
    $admin   = User::factory()->create();
    $admin->assignRole('admin');
    $manager = User::factory()->create();
    seedProjectMasterData();

    $this->actingAs($creator)->post(route('projects.store'), projectPayload($manager));
    $project = Project::firstOrFail();

    $this->actingAs($admin)
        ->get(route('projects.edit', $project))
        ->assertOk();
});

it('lists sub-projects under their parent rather than as rows of their own', function () {
    $user = makeApproverUser();

    $parent = Project::create([
        'project_no'   => 'PRJ-2026-0001',
        'title'        => 'Warehouse Rehab',
        'site'         => 'Main Plant',
        'asset_id'     => 'Asset A',
        'class_name'   => 'Major',
        'priority'     => 'P1 - Urgent',
        'status_key'   => 'PLANNING',
        'work_force'   => 'In-house Team',
        'wr_no'        => 'WR-1',
        'wr_date'      => '2026-05-04',
        'dept_owner'   => 'Engineering',
        'cost_code'    => 'CC-001',
        'category'     => 'Renovation',
        'service_type' => 'Design-Build',
        'deadline'     => '2026-06-04',
        'created_by'   => $user->id,
    ]);

    $child = Project::create([
        'parent_id'    => $parent->id,
        'project_no'   => 'PRJ-2026-0001-A',
        'title'        => 'Warehouse Rehab — Roofing',
        'site'         => 'Main Plant',
        'asset_id'     => 'Asset A',
        'class_name'   => 'Major',
        'priority'     => 'P1 - Urgent',
        'status_key'   => 'ONGOING',
        'work_force'   => 'In-house Team',
        'wr_no'        => 'WR-1',
        'wr_date'      => '2026-05-04',
        'dept_owner'   => 'Engineering',
        'cost_code'    => 'CC-001',
        'category'     => 'Renovation',
        'service_type' => 'Design-Build',
        'deadline'     => '2026-06-04',
        'created_by'   => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // One row — the parent — carrying its child for the toggle to reveal.
            ->has('projects.data', 1)
            ->where('projects.data.0.project_no', 'PRJ-2026-0001')
            ->has('projects.data.0.sub_projects', 1)
            ->where('projects.data.0.sub_projects.0.id', $child->id)
            ->where('projects.data.0.sub_projects.0.project_no', 'PRJ-2026-0001-A')
            // No source NTP, so the list falls back to the project number.
            ->where('projects.data.0.sub_projects.0.ntp_no', null));
});

it('labels a sub-project by the NTP it was created from', function () {
    $user = makeApproverUser();

    $parent = Project::create([
        'project_no'   => 'PRJ-2026-0002',
        'title'        => 'Warehouse Rehab',
        'site'         => 'Main Plant',
        'asset_id'     => 'Asset A',
        'class_name'   => 'Major',
        'priority'     => 'P1 - Urgent',
        'status_key'   => 'PLANNING',
        'work_force'   => 'In-house Team',
        'wr_no'        => 'WR-1',
        'wr_date'      => '2026-05-04',
        'dept_owner'   => 'Engineering',
        'cost_code'    => 'CC-001',
        'category'     => 'Renovation',
        'service_type' => 'Design-Build',
        'deadline'     => '2026-06-04',
        'created_by'   => $user->id,
    ]);

    $ntp = ProjectNtp::create([
        'project_id'      => $parent->id,
        'ntp_no'          => 'NTP-2026-0007',
        'contractor_name' => 'ABC Builders',
        'baseline_start'  => '2026-01-01',
        'baseline_end'    => '2026-06-01',
        'approved_cost'   => 250000,
        'status'          => 'issued',
    ]);

    $child = Project::create([
        'parent_id'     => $parent->id,
        'source_ntp_id' => $ntp->id,
        'project_no'    => 'PRJ-2026-0002-01',
        'title'         => 'Warehouse Rehab (NTP-2026-0007)',
        'site'          => 'Main Plant',
        'asset_id'      => 'Asset A',
        'class_name'    => 'Major',
        'priority'      => 'P1 - Urgent',
        'status_key'    => 'ONGOING',
        'work_force'    => 'In-house Team',
        'wr_no'         => 'WR-1',
        'wr_date'       => '2026-05-04',
        'dept_owner'    => 'Engineering',
        'cost_code'     => 'CC-001',
        'category'      => 'Renovation',
        'service_type'  => 'Design-Build',
        'deadline'      => '2026-06-04',
        'created_by'    => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.sub_projects.0.id', $child->id)
            // The list shows the NTP number; the project number stays available
            // for the row tooltip.
            ->where('projects.data.0.sub_projects.0.ntp_no', 'NTP-2026-0007')
            ->where('projects.data.0.sub_projects.0.project_no', 'PRJ-2026-0002-01'));
});

it('reports no sub-projects for a project without children', function () {
    $user = makeApproverUser();
    seedProjectMasterData();

    $this->actingAs($user)->post(route('projects.store'), projectPayload($user))->assertRedirect();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page->has('projects.data.0.sub_projects', 0));
});
