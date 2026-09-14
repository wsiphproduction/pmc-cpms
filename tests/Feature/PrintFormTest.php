<?php

use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRfq;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

function makeEngineerForPrint(): User
{
    Role::firstOrCreate(['name' => User::ROLE_ENGINEER]);

    $user = User::factory()->create();
    $user->assignRole(User::ROLE_ENGINEER);

    return $user;
}

function makeProjectForPrint(User $engineer): Project
{
    return Project::create([
        'project_no'         => 'PRJ-PRINT-' . uniqid(),
        'title'              => 'Print Test Project',
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

beforeEach(function () {
    Mail::fake();

    $this->engineer = makeEngineerForPrint();
    $this->project  = makeProjectForPrint($this->engineer);

    $this->actingAs($this->engineer)->post(route('hub.rfq.store', $this->project), [
        'contractor_name' => 'Acme Builders',
    ])->assertRedirect();

    $this->rfq = ProjectRfq::where('project_id', $this->project->id)->firstOrFail();

    // An RFQ is dispatched with nothing offered against it; editing the row
    // raises the first quotation and makes it the final one.
    $this->actingAs($this->engineer)
        ->patch(route('hub.rfq.update', [$this->project, $this->rfq]), [
            'scope_of_work'    => 'Detailed Estimate',
            'due_date'         => '2026-12-01',
            'duration_days'    => 30,
            'terms_conditions' => 'Net 30',
            'inclusions'       => 'Labor',
            'exclusions'       => 'Permits',
            'items'            => [
                ['description' => 'Site works', 'qty' => 2, 'unit' => 'lot', 'unit_cost' => 1000, 'total_cost' => 2000],
            ],
        ])->assertRedirect();

    $this->actingAs($this->engineer)->post(route('hub.ntp.store', $this->project), [
        'contractor_name' => 'Acme Builders',
        'project_rfq_id'  => $this->rfq->id,
        'baseline_start'  => '2026-10-01',
        'baseline_end'    => '2026-10-31',
        'approved_cost'   => 2000,
    ])->assertRedirect();

    $this->ntp = ProjectNtp::where('project_id', $this->project->id)->firstOrFail();
});

/*
 * The Blade views carry every field of the controlled forms, so rendering them
 * is what catches a missing variable or a bad accessor. These stop short of
 * invoking Chrome, which the route test below covers once.
 */

it('renders the rfq form with its figures', function () {
    $html = view('print.rfq', [
        'project'      => $this->project,
        'rfq'          => $this->rfq->fresh(['items']),
        'signatories'  => ['prepared_by' => 'Test Engineer', 'pmd_assistant_manager' => 'A. Manager', 'pmd_manager' => 'P. Manager'],
        'subProjectNo' => null,
    ])->render();

    expect($html)->toContain('PMD-PRJ-FRM-03')
        ->toContain('REQUEST FOR QUOTATION')
        ->toContain('Acme Builders')
        ->toContain($this->project->project_no)
        ->toContain('Site works')
        ->toContain('2,000.00')
        ->toContain('30 Working Days')
        // Scope ticks the matching box on the paper form.
        ->toContain('☒ Detailed Estimate')
        ->toContain('☐ Conceptual Design');
});

it('stamps only the prepared-by block before anyone has approved', function () {
    $html = view('print.ntp', [
        'project'      => $this->project,
        'ntp'          => $this->ntp->load('rfq.items', 'creator', 'approvals.user'),
        'signatories'  => ['prepared_by' => 'Test Engineer', 'pmd_assistant_manager' => 'A. Manager', 'pmd_manager' => 'P. Manager'],
        'subProjectNo' => null,
    ])->render();

    expect($html)->toContain('PMD-PRJ-FRM-04')
        ->toContain('NOTICE TO PROCEED')
        ->toContain($this->ntp->ntp_no)
        ->toContain('Acme Builders')
        ->toContain('Php 2,000.00')
        // Preparing the NTP is the engineer's own sign-off, so that block is
        // stamped from the start; every approval step is still blank.
        ->toContain('APPROVED');

    expect(substr_count($html, 'class="stamp"'))->toBe(1);
});

it('stamps the ntp form for each step that has been signed', function () {
    $reviewer = User::factory()->create(['name' => 'Alma Reviewer']);
    Role::firstOrCreate(['name' => User::ROLE_PMD_ASST_MANAGER]);
    $reviewer->assignRole(User::ROLE_PMD_ASST_MANAGER);

    // The PMD Assistant Manager opens the chain; nobody after them has signed.
    (new App\Support\ApprovalFlow)->approveNtp($this->ntp->fresh(), $reviewer);

    $html = view('print.ntp', [
        'project'      => $this->project,
        'ntp'          => $this->ntp->fresh()->load('rfq.items', 'creator', 'approvals.user'),
        'signatories'  => ['prepared_by' => 'Test Engineer', 'pmd_assistant_manager' => 'A. Manager', 'pmd_manager' => 'P. Manager'],
        'subProjectNo' => null,
    ])->render();

    expect($html)->toContain('APPROVED')
        ->toContain('stamped')
        // The signatory's own name replaces the configured placeholder.
        ->toContain('Alma Reviewer')
        // The step still awaiting a decision is not stamped.
        ->toContain('P. Manager');

    // Prepared by, plus the one signed step.
    expect(substr_count($html, 'class="stamp"'))->toBe(2);
});

it('renders both completion documents', function () {
    $signatories = [
        'prepared_by' => 'Test Engineer', 'pmd_assistant_manager' => 'A. Manager',
        'pmd_manager' => 'P. Manager', 'ecs_division_manager' => 'E. Manager',
        'operations_director' => 'O. Director',
    ];

    $acceptance = view('print.acceptance', [
        'project' => $this->project, 'completion' => null, 'signatories' => $signatories,
    ])->render();

    $summary = view('print.completion-summary', [
        'project' => $this->project, 'completion' => null, 'signatories' => $signatories,
    ])->render();

    expect($acceptance)->toContain('PMD-PRJ-FRM-06')
        ->toContain('PROJECT COMPLETION AND ACCEPTANCE CERTIFICATE')
        ->toContain($this->project->project_no);

    expect($summary)->toContain('PMD-PRJ-FRM-12')
        ->toContain('PROJECT COMPLETION SUMMARY')
        ->toContain('No documentation photos attached.');
});

it('signs the completion documents with whoever holds each office', function () {
    foreach ([
        User::ROLE_PMD_ASST_MANAGER  => 'Asst. Holder',
        User::ROLE_PMD_DEPT_MANAGER  => 'Dept. Holder',
        User::ROLE_DIVISION_MANAGER  => 'Division Holder',
    ] as $role => $name) {
        Role::firstOrCreate(['name' => $role]);
        User::factory()->create(['name' => $name])->assignRole($role);
    }
    // A configured placeholder loses to the person actually in the seat.
    App\Models\Setting::set('signatory_pmd_manager', 'Configured Placeholder');

    $html = $this->actingAs($this->engineer)
        ->get(route('print.completion-summary', $this->project))
        ->assertOk()->getContent();

    expect($html)->toContain('Asst. Holder')
        ->toContain('Dept. Holder')
        ->toContain('Division Holder')
        ->not->toContain('Configured Placeholder');
});

it('refuses a print to someone who cannot view the project', function () {
    Role::firstOrCreate(['name' => User::ROLE_REQUESTOR]);
    $stranger = User::factory()->create(['department' => 'Some Other Department']);
    $stranger->assignRole(User::ROLE_REQUESTOR);

    $this->actingAs($stranger)
        ->get(route('print.rfq', [$this->project, $this->rfq]))
        ->assertForbidden();
});

it('will not print an rfq belonging to another project', function () {
    $other = makeProjectForPrint($this->engineer);

    $this->actingAs($this->engineer)
        ->get(route('print.rfq', [$other, $this->rfq]))
        ->assertNotFound();
});

it('streams a real pdf for inline preview under the chrome driver', function () {
    config(['pdf.driver' => 'chrome']);

    $response = $this->actingAs($this->engineer)
        ->get(route('print.ntp', [$this->project, $this->ntp]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))->toStartWith('inline;');
    // A real PDF, not an error page rendered with the wrong content type.
    $body = $response->getContent();
    expect(substr($body, 0, 5))->toBe('%PDF-')
        ->and(strlen($body))->toBeGreaterThan(1000);
})->group('browser');

it('serves a self-printing html page by default', function () {
    // The default driver, for hosts with neither node nor Chrome.
    $response = $this->actingAs($this->engineer)
        ->get(route('print.ntp', [$this->project, $this->ntp]));

    $response->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8');

    $body = $response->getContent();

    // The form itself, plus what opens the print dialog on it.
    expect($body)->toContain($this->ntp->ntp_no)
        ->and($body)->toContain('window.print()')
        ->and($body)->toContain('@page');
});
