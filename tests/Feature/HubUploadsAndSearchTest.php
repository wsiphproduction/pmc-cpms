<?php

use App\Models\Project;
use App\Models\ProjectContract;
use App\Models\ProjectEmsRegisterEntry;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function uploadsUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function uploadsProject(User $engineer, array $attributes = []): Project
{
    return Project::create([
        'project_no'           => 'PRJ-UPL-' . uniqid(),
        'title'                => 'Uploads Project',
        'project_manager_id'   => $engineer->id,
        'project_manager_name' => $engineer->name,
        'site'                 => 'Main Plant',
        'asset_id'             => 'A1',
        'class_name'           => 'Minor',
        'priority'             => '1',
        'status_key'           => 'ONGOING',
        'work_force'           => 'In-House',
        'wr_no'                => 'WR-1',
        'wr_date'              => now(),
        'dept_owner'           => 'Engineering',
        'cost_code'            => 'CC-001',
        'category'             => 'General',
        'service_type'         => 'Repair',
        'deadline'             => now()->addDays(30),
        'created_by'           => $engineer->id,
        ...$attributes,
    ]);
}

// ── Project Contract ─────────────────────────────────────────────────────

it('files a project contract as a labelled pdf and lists it on its tab', function () {
    Storage::fake('public');
    $engineer = uploadsUser(User::ROLE_ENGINEER);
    $project  = uploadsProject($engineer);

    $this->actingAs($engineer)->post(route('hub.contract.store', $project), [
        'label' => 'Approved Project Contract',
        'file'  => UploadedFile::fake()->create('contract.pdf', 80, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $contract = ProjectContract::firstOrFail();
    Storage::disk('public')->assertExists($contract->file_path);
    expect($contract->label)->toBe('Approved Project Contract')
        ->and($contract->versionsOf())->toHaveCount(1);

    $this->actingAs($engineer)
        ->get(route('projects.hub.contract', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('active_section', 'contract')
            ->where('hub_data.contracts.0.label', 'Approved Project Contract')
            ->where('hub_data.contracts.0.filename', 'contract.pdf')
            ->where('hub_counts.contract', 1));
});

it('takes only a pdf for the project contract, on upload and on replace', function () {
    Storage::fake('public');
    $engineer = uploadsUser(User::ROLE_ENGINEER);
    $project  = uploadsProject($engineer);

    $this->actingAs($engineer)->post(route('hub.contract.store', $project), [
        'label' => 'Contract',
        'file'  => UploadedFile::fake()->create('contract.docx', 80),
    ])->assertSessionHasErrors('file');

    expect(ProjectContract::count())->toBe(0);

    $this->actingAs($engineer)->post(route('hub.contract.store', $project), [
        'label' => 'Contract',
        'file'  => UploadedFile::fake()->create('contract.pdf', 80, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $contract = ProjectContract::firstOrFail();

    $this->actingAs($engineer)->post(route('files.replace', [$project, 'contract', $contract->id]), [
        'file' => UploadedFile::fake()->create('amended.docx', 80),
    ])->assertSessionHasErrors('file');

    $this->actingAs($engineer)->post(route('files.replace', [$project, 'contract', $contract->id]), [
        'file' => UploadedFile::fake()->create('amended.pdf', 80, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    expect($contract->fresh()->filename)->toBe('amended.pdf');
});

it('deletes a contract only from its own project', function () {
    Storage::fake('public');
    $engineer = uploadsUser(User::ROLE_ENGINEER);
    $project  = uploadsProject($engineer);
    $other    = uploadsProject($engineer);

    $this->actingAs($engineer)->post(route('hub.contract.store', $project), [
        'label' => 'Contract',
        'file'  => UploadedFile::fake()->create('contract.pdf', 80, 'application/pdf'),
    ]);
    $contract = ProjectContract::firstOrFail();

    $this->actingAs($engineer)->delete(route('hub.contract.destroy', [$other, $contract]))->assertForbidden();
    $this->actingAs($engineer)->delete(route('hub.contract.destroy', [$project, $contract]))->assertRedirect();

    expect(ProjectContract::count())->toBe(0);
});

// ── EMS Register ─────────────────────────────────────────────────────────

it('files any document in the ems register with a description', function () {
    Storage::fake('public');
    $engineer = uploadsUser(User::ROLE_ENGINEER);
    $project  = uploadsProject($engineer);

    $this->actingAs($engineer)->post(route('hub.ems-register.store', $project), [
        'description' => 'ECC compliance monitoring, Q3',
        'file'        => UploadedFile::fake()->create('ecc.xlsx', 40),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $entry = ProjectEmsRegisterEntry::firstOrFail();
    Storage::disk('public')->assertExists($entry->file_path);

    $this->actingAs($engineer)
        ->get(route('projects.hub.ems-register', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hub_data.entries.0.description', 'ECC compliance monitoring, Q3')
            ->where('hub_data.entries.0.filename', 'ecc.xlsx')
            ->where('hub_counts.ems-register', 1));

    $this->actingAs($engineer)->delete(route('hub.ems-register.destroy', [$project, $entry]))->assertRedirect();
    expect(ProjectEmsRegisterEntry::count())->toBe(0);
});

it('needs a description for an ems register file', function () {
    Storage::fake('public');
    $engineer = uploadsUser(User::ROLE_ENGINEER);
    $project  = uploadsProject($engineer);

    $this->actingAs($engineer)->post(route('hub.ems-register.store', $project), [
        'file' => UploadedFile::fake()->create('ecc.pdf', 40, 'application/pdf'),
    ])->assertSessionHasErrors('description');
});

// ── Advanced search ──────────────────────────────────────────────────────

it('filters project requests by date range, project type and job location', function () {
    $admin    = uploadsUser(User::ROLE_ADMIN);
    $engineer = uploadsUser(User::ROLE_ENGINEER);

    $old   = ProjectRequest::factory()->create(['job_location' => 'Mill', 'created_at' => '2026-01-10 09:00:00']);
    $major = ProjectRequest::factory()->create(['job_location' => 'Mine', 'created_at' => '2026-03-15 09:00:00']);
    $other = ProjectRequest::factory()->create(['job_location' => 'Other - Haulage Road', 'created_at' => '2026-03-20 09:00:00']);
    uploadsProject($engineer, ['project_request_id' => $major->id, 'project_type' => 'major']);
    uploadsProject($engineer, ['project_request_id' => $other->id, 'project_type' => 'minor']);

    $listed = fn (array $filters) => collect($this->actingAs($admin)
        ->get(route('requests.index', $filters))
        ->assertOk()
        ->inertiaPage()['props']['requests']['data'])->pluck('id')->sort()->values()->all();

    expect($listed(['date_from' => '2026-03-01', 'date_to' => '2026-03-31']))->toBe(collect([$major->id, $other->id])->sort()->values()->all())
        ->and($listed(['date_to' => '2026-01-31']))->toBe([$old->id])
        ->and($listed(['project_type' => 'major']))->toBe([$major->id])
        ->and($listed(['project_type' => 'minor']))->toBe([$other->id])
        // "Other" on the dropdown finds the typed-in "Other - …" locations.
        ->and($listed(['job_location' => 'Other']))->toBe([$other->id])
        ->and($listed(['job_location' => 'Mill']))->toBe([$old->id]);
});

it('offers the master list of job locations to the request search', function () {
    \App\Models\JobLocation::create(['name' => 'Padigusan', 'is_active' => true]);
    \App\Models\JobLocation::create(['name' => 'Retired', 'is_active' => false]);

    $this->actingAs(uploadsUser(User::ROLE_ADMIN))
        ->get(route('requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('jobLocations', ['Padigusan']));
});

it('filters projects by the date they were registered', function () {
    $admin    = uploadsUser(User::ROLE_ADMIN);
    $engineer = uploadsUser(User::ROLE_ENGINEER);

    $early = uploadsProject($engineer, ['project_no' => 'PRJ-EARLY']);
    $late  = uploadsProject($engineer, ['project_no' => 'PRJ-LATE']);
    Project::whereKey($early->id)->update(['created_at' => '2026-02-01 08:00:00']);
    Project::whereKey($late->id)->update(['created_at' => '2026-06-01 08:00:00']);

    $listed = fn (array $filters) => collect($this->actingAs($admin)
        ->get(route('projects.index', $filters))
        ->assertOk()
        ->inertiaPage()['props']['projects']['data'])->pluck('project_no')->all();

    expect($listed(['date_from' => '2026-05-01']))->toBe(['PRJ-LATE'])
        ->and($listed(['date_to' => '2026-03-01']))->toBe(['PRJ-EARLY'])
        ->and($listed(['date_from' => '2026-01-01', 'date_to' => '2026-12-31']))->toHaveCount(2);
});
