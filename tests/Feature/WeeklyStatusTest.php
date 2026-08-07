<?php

use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectWeeklyReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// -------------------------------------------------
// Helpers
// -------------------------------------------------

function makeWeeklyStatusEngineer(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function makeWeeklyStatusProject(User $engineer, array $overrides = []): Project
{
    static $sequence = 0;
    $sequence++;

    return Project::create(array_merge([
        'project_no'           => sprintf('PRJ-2026-%04d', $sequence),
        'title'                => 'Weekly Status Project ' . $sequence,
        'project_manager_id'   => $engineer->id,
        'project_manager_name' => $engineer->name,
        'site'                 => 'Main Plant',
        'asset_id'             => 'Asset A',
        'class_name'           => 'Minor',
        'priority'             => 'P1 - Urgent',
        'status_key'           => 'ONGOING',
        'work_force'           => 'In-house Team',
        'wr_no'                => '1234',
        'wr_date'              => '2026-05-04',
        'dept_owner'           => 'Engineering',
        'cost_code'            => 'CC-001',
        'category'             => 'Renovation',
        'service_type'         => 'Design-Build',
        'deadline'             => '2026-12-31',
        'budget_total'         => 100000,
        'project_type'         => 'minor',
        'created_by'           => $engineer->id,
    ], $overrides));
}

/** A .csv upload with the cross-project header the Weekly Status template uses. */
function weeklyStatusCsv(array $rows, string $header = 'project_no,week_code,ntp_no,completion_pct,submitted_date,progress_updates'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'ws') . '.csv';
    file_put_contents($path, implode("\n", [$header, ...$rows]) . "\n");

    return new UploadedFile($path, 'weekly-status.csv', 'text/csv', null, true);
}

// -------------------------------------------------
// Access
// -------------------------------------------------

describe('access', function () {

    it('redirects guests to login', function () {
        $this->get(route('weekly-status.index'))->assertRedirect(route('login'));
    });

    it('is closed to department users', function () {
        Role::firstOrCreate(['name' => 'requestor']);
        $user = User::factory()->create();
        $user->assignRole('requestor');

        $this->actingAs($user)->get(route('weekly-status.index'))->assertForbidden();
    });

    it('is open to project engineers', function () {
        $engineer = makeWeeklyStatusEngineer();

        $this->actingAs($engineer)->get(route('weekly-status.index'))->assertOk();
    });
});

// -------------------------------------------------
// Index
// -------------------------------------------------

describe('index', function () {

    it('lists only the projects the engineer handles', function () {
        $engineer = makeWeeklyStatusEngineer();
        $other    = makeWeeklyStatusEngineer();

        $mine     = makeWeeklyStatusProject($engineer);
        $theirs   = makeWeeklyStatusProject($other);

        $this->actingAs($engineer)->get(route('weekly-status.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('weekly-status/index')
                ->has('projects', 1)
                ->where('projects.0.project_no', $mine->project_no));

        expect($theirs->project_no)->not->toBe($mine->project_no);
    });

    it('includes sub-projects, flagged as such', function () {
        $engineer = makeWeeklyStatusEngineer();
        $parent   = makeWeeklyStatusProject($engineer);
        makeWeeklyStatusProject($engineer, [
            'project_no' => $parent->project_no . '-01',
            'parent_id'  => $parent->id,
        ]);

        $this->actingAs($engineer)->get(route('weekly-status.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 2)
                ->where('projects.1.is_sub', true));
    });

    it('shows admins every project', function () {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $engineer = makeWeeklyStatusEngineer();
        makeWeeklyStatusProject($engineer);
        makeWeeklyStatusProject($engineer);

        $this->actingAs($admin)->get(route('weekly-status.index'))
            ->assertInertia(fn (Assert $page) => $page->has('projects', 2));
    });
});

// -------------------------------------------------
// Template
// -------------------------------------------------

describe('template', function () {

    it('downloads a workbook listing the engineer\'s projects', function () {
        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);

        $response = $this->actingAs($engineer)->get(route('weekly-status.template'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'wst') . '.xlsx';
        file_put_contents($path, $response->getContent());

        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

        expect($book->getSheetNames())->toContain('Weekly Reports', 'My Projects', 'Checklist Guide');
        // project_no leads the sheet — the difference from the per-project template.
        expect($book->getSheet(0)->getCell('A1')->getValue())->toBe('project_no');
        expect($book->getSheetByName('My Projects')->getCell('A2')->getValue())->toBe($project->project_no);

        $book->disconnectWorksheets();
        @unlink($path);
    });
});

// -------------------------------------------------
// Bulk import
// -------------------------------------------------

describe('import', function () {

    it('files reports across several projects from one upload', function () {
        $engineer = makeWeeklyStatusEngineer();
        $one = makeWeeklyStatusProject($engineer);
        $two = makeWeeklyStatusProject($engineer);

        $this->actingAs($engineer)->post(route('weekly-status.import'), [
            'file' => weeklyStatusCsv([
                "{$one->project_no},W2-AUG,,45,2026-08-05,Trusses erected",
                "{$two->project_no},W2-AUG,,60,2026-08-05,Base course done",
            ]),
        ])->assertRedirect();

        expect(ProjectWeeklyReport::count())->toBe(2);
        expect($one->fresh()->completion_percent)->toBe(45);
        expect($two->fresh()->completion_percent)->toBe(60);
    });

    it('matches an ntp within the row\'s own project', function () {
        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);

        $ntp = ProjectNtp::create([
            'project_id'      => $project->id,
            'ntp_no'          => 'NTP-001',
            'contractor_name' => 'ABC Builders',
            'baseline_start'  => '2026-01-01',
            'baseline_end'    => '2026-06-01',
            'status'          => 'issued',
        ]);

        $this->actingAs($engineer)->post(route('weekly-status.import'), [
            'file' => weeklyStatusCsv([
                "{$project->project_no},W2-AUG,NTP-001,45,2026-08-05,Trusses erected",
            ]),
        ])->assertRedirect();

        expect(ProjectWeeklyReport::first()->project_ntp_id)->toBe($ntp->id);
    });

    it('skips rows naming a project the engineer does not handle', function () {
        $engineer = makeWeeklyStatusEngineer();
        $other    = makeWeeklyStatusEngineer();
        $mine     = makeWeeklyStatusProject($engineer);
        $theirs   = makeWeeklyStatusProject($other);

        $this->actingAs($engineer)->post(route('weekly-status.import'), [
            'file' => weeklyStatusCsv([
                "{$mine->project_no},W2-AUG,,45,2026-08-05,Mine",
                "{$theirs->project_no},W2-AUG,,90,2026-08-05,Not mine",
                "PRJ-9999-0000,W2-AUG,,10,2026-08-05,Nonexistent",
            ]),
        ])->assertRedirect()->assertSessionHas('success');

        expect(ProjectWeeklyReport::count())->toBe(1);
        expect(ProjectWeeklyReport::first()->project_id)->toBe($mine->id);
        expect($theirs->fresh()->completion_percent)->toBe(0);
    });

    it('rejects a file with no project_no column', function () {
        $engineer = makeWeeklyStatusEngineer();
        makeWeeklyStatusProject($engineer);

        $this->actingAs($engineer)->post(route('weekly-status.import'), [
            'file' => weeklyStatusCsv(['W2-AUG,45'], 'week_code,completion_pct'),
        ])->assertRedirect()->assertSessionHas('error');

        expect(ProjectWeeklyReport::count())->toBe(0);
    });

    it('accepts the basic template shape, headline issues and all', function () {
        $engineer = makeWeeklyStatusEngineer();
        $one = makeWeeklyStatusProject($engineer);
        $two = makeWeeklyStatusProject($engineer);

        $this->actingAs($engineer)->post(route('weekly-status.import'), [
            'file' => weeklyStatusCsv(
                [
                    "{$one->project_no},W1-OCT,25,Delayed delivery of materials,Foundation works started,,",
                    "{$two->project_no},W1-OCT,40,,Column rebars installed,,",
                ],
                'project_no,week_code,completion_pct,identified_issues,progress_updates,submitted_date,ntp_no',
            ),
        ])->assertRedirect()->assertSessionHas('success');

        expect(ProjectWeeklyReport::count())->toBe(2);

        $first = ProjectWeeklyReport::where('project_id', $one->id)->first();

        expect($first->identified_issues)->toBe('Delayed delivery of materials');
        expect($first->progress_updates)->toBe('Foundation works started');
        // A blank submitted_date falls back to today.
        expect($first->submitted_date->toDateString())->toBe(now()->toDateString());
        expect($one->fresh()->completion_percent)->toBe(25);
        expect($two->fresh()->completion_percent)->toBe(40);
    });

    it('carries the detailed checklist and issue columns through', function () {
        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);

        $this->actingAs($engineer)->post(route('weekly-status.import'), [
            'file' => weeklyStatusCsv(
                ["{$project->project_no},W2-AUG,45,yes,Fence up,Rain delays,Night shift,2026-08-12"],
                'project_no,week_code,completion_pct,chk_1_1_status,chk_1_1_remarks,issue_1,action_1,commitment_date_1',
            ),
        ])->assertRedirect();

        $report = ProjectWeeklyReport::first();

        expect($report->checklist)->toBe([['seq' => '1.1', 'status' => '√', 'remarks' => 'Fence up']]);
        expect($report->issues)->toBe([['issue' => 'Rain delays', 'action' => 'Night shift', 'commitment_date' => '2026-08-12']]);
        // The first issue row doubles as the headline issue.
        expect($report->identified_issues)->toBe('Rain delays');
    });
});

// -------------------------------------------------
// Single submission
// -------------------------------------------------

describe('store', function () {

    it('files one report and updates the project completion', function () {
        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);

        $this->actingAs($engineer)->post(route('weekly-status.store'), [
            'project_id'       => $project->id,
            'week_code'        => 'W2-AUG',
            'completion_pct'   => 55,
            'progress_updates' => 'Slab poured',
            'checklist'        => [['seq' => '1.1', 'status' => '√', 'remarks' => 'Clear']],
            'issues'           => [['issue' => 'Late delivery', 'action' => 'Chase supplier', 'commitment_date' => '2026-08-14']],
        ])->assertRedirect()->assertSessionHas('success');

        $report = ProjectWeeklyReport::first();

        expect($report->project_id)->toBe($project->id);
        expect($report->identified_issues)->toBe('Late delivery');
        expect($project->fresh()->completion_percent)->toBe(55);
    });

    it('refuses a project the engineer does not handle', function () {
        $engineer = makeWeeklyStatusEngineer();
        $other    = makeWeeklyStatusEngineer();
        $theirs   = makeWeeklyStatusProject($other);

        $this->actingAs($engineer)->post(route('weekly-status.store'), [
            'project_id'     => $theirs->id,
            'week_code'      => 'W2-AUG',
            'completion_pct' => 55,
        ])->assertNotFound();

        expect(ProjectWeeklyReport::count())->toBe(0);
    });

    it('drops an ntp belonging to another project', function () {
        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);
        $sibling  = makeWeeklyStatusProject($engineer);

        $foreignNtp = ProjectNtp::create([
            'project_id'      => $sibling->id,
            'ntp_no'          => 'NTP-777',
            'contractor_name' => 'XYZ Builders',
            'baseline_start'  => '2026-01-01',
            'baseline_end'    => '2026-06-01',
            'status'          => 'issued',
        ]);

        $this->actingAs($engineer)->post(route('weekly-status.store'), [
            'project_id'     => $project->id,
            'project_ntp_id' => $foreignNtp->id,
            'week_code'      => 'W2-AUG',
            'completion_pct' => 55,
        ])->assertRedirect();

        expect(ProjectWeeklyReport::first()->project_ntp_id)->toBeNull();
    });
});

// -------------------------------------------------
// Revise
// -------------------------------------------------

describe('update', function () {

    it('revises the figures and re-derives completion', function () {
        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);

        $report = $project->weeklyReports()->create([
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'submitted_date' => '2026-08-05',
            'created_by'     => $engineer->id,
        ]);

        $this->actingAs($engineer)->put(route('weekly-status.update', $report->id), [
            'week_code'        => 'W2-AUG',
            'completion_pct'   => 65,
            'progress_updates' => 'Revised after site walk',
            'issues'           => [['issue' => 'Rain', 'action' => 'Tarp works', 'commitment_date' => '2026-08-12']],
        ])->assertRedirect()->assertSessionHas('success');

        $report->refresh();

        expect($report->completion_pct)->toBe(65);
        expect($report->progress_updates)->toBe('Revised after site walk');
        expect($report->identified_issues)->toBe('Rain');
        expect($project->fresh()->completion_percent)->toBe(65);
    });

    it('attaches a pdf a bulk upload could not carry', function () {
        \Illuminate\Support\Facades\Storage::fake('public');

        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);

        $report = $project->weeklyReports()->create([
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'submitted_date' => '2026-08-05',
            'created_by'     => $engineer->id,
        ]);

        expect($report->file_path)->toBeNull();

        $this->actingAs($engineer)->put(route('weekly-status.update', $report->id), [
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'file'           => UploadedFile::fake()->create('signed-report.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $report->refresh();

        expect($report->filename)->toBe('signed-report.pdf');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($report->file_path);
    });

    it('replaces an attachment and clears the old file', function () {
        \Illuminate\Support\Facades\Storage::fake('public');

        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);

        $this->actingAs($engineer)->post(route('weekly-status.store'), [
            'project_id'     => $project->id,
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'file'           => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $report  = ProjectWeeklyReport::first();
        $oldPath = $report->file_path;

        $this->actingAs($engineer)->put(route('weekly-status.update', $report->id), [
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'file'           => UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $report->refresh();

        expect($report->filename)->toBe('second.pdf');
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldPath);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($report->file_path);
    });

    it('removes an attachment when asked', function () {
        \Illuminate\Support\Facades\Storage::fake('public');

        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);

        $this->actingAs($engineer)->post(route('weekly-status.store'), [
            'project_id'     => $project->id,
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'file'           => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $report  = ProjectWeeklyReport::first();
        $oldPath = $report->file_path;

        $this->actingAs($engineer)->put(route('weekly-status.update', $report->id), [
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'remove_file'    => true,
        ])->assertRedirect();

        $report->refresh();

        expect($report->file_path)->toBeNull();
        expect($report->filename)->toBeNull();
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldPath);
    });

    it('keeps the existing attachment when the revision touches nothing else', function () {
        \Illuminate\Support\Facades\Storage::fake('public');

        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);

        $this->actingAs($engineer)->post(route('weekly-status.store'), [
            'project_id'     => $project->id,
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'file'           => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $report = ProjectWeeklyReport::first();

        $this->actingAs($engineer)->put(route('weekly-status.update', $report->id), [
            'week_code'      => 'W2-AUG',
            'completion_pct' => 55,
        ])->assertRedirect();

        $report->refresh();

        expect($report->filename)->toBe('signed.pdf');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($report->file_path);
    });

    it('refuses a report on someone else\'s project', function () {
        $engineer = makeWeeklyStatusEngineer();
        $other    = makeWeeklyStatusEngineer();
        $theirs   = makeWeeklyStatusProject($other);

        $report = $theirs->weeklyReports()->create([
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'submitted_date' => '2026-08-05',
            'created_by'     => $other->id,
        ]);

        $this->actingAs($engineer)->put(route('weekly-status.update', $report->id), [
            'week_code'      => 'HACKED',
            'completion_pct' => 99,
        ])->assertNotFound();

        expect($report->fresh()->week_code)->toBe('W2-AUG');
    });

    it('drops an ntp belonging to another project', function () {
        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);
        $sibling  = makeWeeklyStatusProject($engineer);

        $foreignNtp = ProjectNtp::create([
            'project_id'      => $sibling->id,
            'ntp_no'          => 'NTP-888',
            'contractor_name' => 'XYZ Builders',
            'baseline_start'  => '2026-01-01',
            'baseline_end'    => '2026-06-01',
            'status'          => 'issued',
        ]);

        $report = $project->weeklyReports()->create([
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'submitted_date' => '2026-08-05',
            'created_by'     => $engineer->id,
        ]);

        $this->actingAs($engineer)->put(route('weekly-status.update', $report->id), [
            'week_code'      => 'W2-AUG',
            'completion_pct' => 40,
            'project_ntp_id' => $foreignNtp->id,
        ])->assertRedirect();

        expect($report->fresh()->project_ntp_id)->toBeNull();
    });
});

// -------------------------------------------------
// Delete
// -------------------------------------------------

describe('destroy', function () {

    it('removes a report and re-derives completion', function () {
        $engineer = makeWeeklyStatusEngineer();
        $project  = makeWeeklyStatusProject($engineer);

        $this->actingAs($engineer)->post(route('weekly-status.store'), [
            'project_id'     => $project->id,
            'week_code'      => 'W2-AUG',
            'completion_pct' => 55,
        ])->assertRedirect();

        $report = ProjectWeeklyReport::first();

        $this->actingAs($engineer)->delete(route('weekly-status.destroy', $report->id))->assertRedirect();

        expect(ProjectWeeklyReport::count())->toBe(0);
        expect($project->fresh()->completion_percent)->toBe(0);
    });

    it('refuses a report on someone else\'s project', function () {
        $engineer = makeWeeklyStatusEngineer();
        $other    = makeWeeklyStatusEngineer();
        $theirs   = makeWeeklyStatusProject($other);

        $report = $theirs->weeklyReports()->create([
            'week_code'      => 'W2-AUG',
            'completion_pct' => 55,
            'submitted_date' => '2026-08-05',
            'created_by'     => $other->id,
        ]);

        $this->actingAs($engineer)->delete(route('weekly-status.destroy', $report->id))->assertNotFound();

        expect(ProjectWeeklyReport::count())->toBe(1);
    });
});
