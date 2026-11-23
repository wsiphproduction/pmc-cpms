<?php

use App\Mail\ContractorFindingNotice;
use App\Models\ContractorEvaluation;
use App\Models\ContractorFinding;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ContractorEvaluationScoring as Scoring;
use App\Support\ContractorPerformance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function ncrUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function ncrProject(User $engineer): Project
{
    $project = Project::create([
        'project_no'           => 'PRJ-NCR-' . uniqid(),
        'title'                => 'Findings Project',
        'project_manager_id'   => $engineer->id,
        'project_manager_name' => $engineer->name,
        'site'                 => 'Main Plant',
        'asset_id'             => 'A1',
        'class_name'           => 'Minor',
        'priority'             => '1',
        'status_key'           => 'ONGOING',
        'work_force'           => 'Contractor',
        'wr_no'                => 'WR-1',
        'wr_date'              => now(),
        'dept_owner'           => 'Engineering',
        'cost_code'            => 'CC-001',
        'category'             => 'General',
        'service_type'         => 'Repair',
        'deadline'             => now()->addDays(30),
        'created_by'           => $engineer->id,
    ]);

    ProjectNtp::create([
        'project_id'      => $project->id,
        'ntp_no'          => 'NTP-' . uniqid(),
        'contractor_name' => 'Acme Builders',
        'baseline_start'  => '2026-01-01',
        'baseline_end'    => '2026-06-01',
        'approved_cost'   => 100000,
        'status'          => 'issued',
    ]);

    return $project;
}

function ncrLog($test, User $engineer, Project $project, array $overrides = []): ContractorFinding
{
    $test->actingAs($engineer)->post(route('hub.ncr.store', $project), [
        'contractor_name'   => 'Acme Builders',
        'category'          => 'hse',
        'deficiency'        => 'Workers without hard hats at the batching area.',
        'date_identified'   => now()->subDays(10)->toDateString(),
        'corrective_action' => 'Enforce PPE and submit a toolbox talk record.',
        'target_date'       => now()->addDays(5)->toDateString(),
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return ContractorFinding::latest('id')->firstOrFail();
}

it('logs a non-compliance record with a control number, a portal token and its contractor link', function () {
    Storage::fake('public');
    $supplier = Supplier::create(['company' => 'Acme Builders', 'email' => 'site@acme.test', 'is_active' => true]);
    $engineer = ncrUser(User::ROLE_ENGINEER);
    $project  = ncrProject($engineer);

    $finding = ncrLog($this, $engineer, $project, ['evidence' => [UploadedFile::fake()->image('site.jpg')]]);

    expect($finding->control_no)->toStartWith('NCR-')
        ->and(strlen($finding->portal_token))->toBe(48)
        ->and($finding->supplier_id)->toBe($supplier->id)
        ->and($finding->status)->toBe(ContractorFinding::STATUS_OPEN)
        ->and($finding->attachments()->where('type', ContractorFinding::FILE_EVIDENCE)->count())->toBe(1);

    $this->actingAs($engineer)->get(route('projects.hub.ncr', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hub_data.findings.0.control_no', $finding->control_no)
            ->where('hub_data.findings.0.category_label', 'E. Health, Safety and Environmental Compliance')
            ->where('hub_data.findings.0.supplier_emails', ['site@acme.test'])
            ->where('hub_counts.ncr', 1));
});

it('rejects a target date before the date identified', function () {
    $engineer = ncrUser(User::ROLE_ENGINEER);
    $project  = ncrProject($engineer);

    $this->actingAs($engineer)->post(route('hub.ncr.store', $project), [
        'contractor_name'   => 'Acme Builders',
        'deficiency'        => 'x',
        'date_identified'   => '2026-10-10',
        'corrective_action' => 'y',
        'target_date'       => '2026-10-01',
    ])->assertSessionHasErrors('target_date');
});

it('emails the contractor a link to the record', function () {
    Mail::fake();
    $engineer = ncrUser(User::ROLE_ENGINEER);
    $project  = ncrProject($engineer);
    $finding  = ncrLog($this, $engineer, $project);

    $this->actingAs($engineer)->post(route('hub.ncr.send', [$project, $finding]), ['email' => 'site@acme.test; pm@acme.test'])
        ->assertSessionHasNoErrors();

    Mail::assertQueued(ContractorFindingNotice::class, 2);
    expect($finding->fresh()->notified_email)->toBe('site@acme.test, pm@acme.test');

    // The email renders, carrying the record and the contractor's link.
    $html = (new ContractorFindingNotice($finding->fresh()))->render();
    expect($html)->toContain($finding->control_no)
        ->and($html)->toContain($finding->portal_token)
        ->and($html)->toContain('Enforce PPE');

    $this->actingAs($engineer)->post(route('hub.ncr.send', [$project, $finding]), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

it('lets the contractor read the record and respond through the tokened link, without seeing PMD evidence', function () {
    Storage::fake('public');
    $engineer = ncrUser(User::ROLE_ENGINEER);
    $project  = ncrProject($engineer);
    $finding  = ncrLog($this, $engineer, $project, ['evidence' => [UploadedFile::fake()->image('internal.jpg')]]);

    $this->get(route('contractor-finding.show', $finding->portal_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contractor-finding/index')
            ->where('finding.control_no', $finding->control_no)
            ->missing('finding.evidence'));

    $this->post(route('contractor-finding.respond', $finding->portal_token), [
        'contractor_response' => 'Hard hats issued; toolbox talk held on site.',
        'files'               => [UploadedFile::fake()->create('toolbox.pdf', 20, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $finding->refresh();
    expect($finding->status)->toBe(ContractorFinding::STATUS_RESPONDED)
        ->and($finding->contractor_response)->toBe('Hard hats issued; toolbox talk held on site.')
        ->and($finding->response_recorded_by)->toBeNull()
        ->and($finding->attachments()->where('type', ContractorFinding::FILE_RESPONSE)->count())->toBe(1);

    $this->get(route('contractor-finding.show', 'not-a-real-token'))->assertNotFound();
});

it('closes a record once verified, keeps it, and can reopen it', function () {
    $engineer = ncrUser(User::ROLE_ENGINEER);
    $project  = ncrProject($engineer);
    $finding  = ncrLog($this, $engineer, $project);

    $this->actingAs($engineer)->post(route('hub.ncr.response', [$project, $finding]), [
        'contractor_response' => 'Replied by letter.',
    ])->assertSessionHasNoErrors();
    expect($finding->fresh()->response_recorded_by)->toBe($engineer->id);

    $this->actingAs($engineer)->post(route('hub.ncr.close', [$project, $finding]), [
        'closure_date' => now()->addDay()->toDateString(),
        'closure_remarks' => 'Verified on site.',
    ])->assertSessionHasErrors('closure_date');

    $this->actingAs($engineer)->post(route('hub.ncr.close', [$project, $finding]), [
        'closure_date'    => now()->toDateString(),
        'closure_remarks' => 'Verified on site.',
    ])->assertSessionHasNoErrors();
    expect($finding->fresh()->status)->toBe(ContractorFinding::STATUS_CLOSED);

    // Closed: no more responses from the contractor, no deletion.
    $this->post(route('contractor-finding.respond', $finding->portal_token), ['contractor_response' => 'late'])->assertStatus(422);
    $this->actingAs($engineer)->delete(route('hub.ncr.destroy', [$project, $finding]))->assertStatus(422);

    $this->actingAs($engineer)->post(route('hub.ncr.reopen', [$project, $finding]))->assertSessionHasNoErrors();
    $finding->refresh();
    expect($finding->status)->toBe(ContractorFinding::STATUS_RESPONDED)
        ->and($finding->closure_date)->toBeNull();
});

it('raises a record for each corrective action when an evaluation is approved, once', function () {
    Storage::fake('public');
    $engineer = ncrUser(User::ROLE_ENGINEER);
    $asst     = ncrUser(User::ROLE_PMD_ASST_MANAGER);
    $manager  = ncrUser(User::ROLE_PMD_DEPT_MANAGER);
    $project  = ncrProject($engineer);

    $this->actingAs($engineer)->post(route('hub.evaluations.store', $project), [
        'period_type' => 'monthly', 'period_label' => 'October 2026', 'contractor_name' => 'Acme Builders',
    ]);
    $evaluation = ContractorEvaluation::firstOrFail();

    foreach (array_keys(Scoring::categories()) as $category) {
        $this->actingAs($engineer)->post(route('hub.evaluations.category', [$project, $evaluation, $category]), [
            'ratings'            => array_fill_keys(Scoring::criterionKeys($category), 3),
            'evidence'           => [UploadedFile::fake()->create("{$category}.pdf", 10, 'application/pdf')],
            'comments'           => $category === 'hse' ? 'PPE lapses observed twice.' : null,
            'corrective_actions' => in_array($category, ['hse', 'schedule'], true) ? "Fix {$category}." : null,
        ]);
    }
    $this->actingAs($engineer)->post(route('hub.evaluations.submit', [$project, $evaluation]));
    $this->actingAs($asst)->patch(route('evaluations.approve', $evaluation));

    expect(ContractorFinding::count())->toBe(0); // only on final approval

    $this->actingAs($manager)->patch(route('evaluations.approve', $evaluation));

    $findings = ContractorFinding::orderBy('category')->get();
    expect($findings)->toHaveCount(2)
        ->and($findings->pluck('category')->all())->toBe(['hse', 'schedule'])
        ->and($findings->firstWhere('category', 'hse')->deficiency)->toBe('PPE lapses observed twice.')
        ->and($findings->firstWhere('category', 'schedule')->corrective_action)->toBe('Fix schedule.')
        ->and($findings->first()->contractor_evaluation_id)->toBe($evaluation->id);

    expect(\App\Support\ContractorFindings::raiseFromEvaluation($evaluation->fresh()))->toBe(0);
});

it('flags overdue corrective actions and lists them on the monitoring page and reports', function () {
    $engineer = ncrUser(User::ROLE_ENGINEER);
    $project  = ncrProject($engineer);
    $overdue  = ncrLog($this, $engineer, $project, ['target_date' => now()->subDay()->toDateString()]);
    ncrLog($this, $engineer, $project);

    expect($overdue->fresh()->isOverdue())->toBeTrue()
        ->and($overdue->fresh()->statusLabel())->toBe('Overdue');

    $row = (new ContractorPerformance)->ranking()[0];
    expect($row['findings_overdue'])->toBe(1)
        ->and(collect($row['flags'])->pluck('type'))->toContain('overdue');

    $manager = ncrUser(User::ROLE_PMD_DEPT_MANAGER);
    $this->actingAs($manager)->get(route('contractor-performance.findings', ['ncr_status' => 'overdue']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contractor-performance/findings')
            ->where('stats.total', 2)
            ->where('stats.overdue', 1)
            ->has('findings', 1));

    foreach (['noncompliance', 'corrective'] as $report) {
        $response = $this->actingAs($manager)->get(route('contractor-performance.export', ['report' => $report]));
        $response->assertOk();
        expect(substr($response->getContent(), 0, 2))->toBe('PK');
    }
});
