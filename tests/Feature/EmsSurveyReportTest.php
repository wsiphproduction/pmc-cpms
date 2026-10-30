<?php

use App\Mail\EmsReportActivity;
use App\Models\EmsSurveyReport;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function emsUser(string $role, array $attributes = []): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

/** What a representative fills in before submitting. */
function emsBody(array $overrides = []): array
{
    return array_merge([
        'major_activities'       => ['construction'],
        'major_activities_other' => '',
        'other_scope'            => 'Perimeter fence and drainage canal.',
        'aspects'                => ['heavy_equipment', 'dust'],
        'aspects_other'          => '',
        'impacts'                => 'Dust from excavation.',
        'mitigation'             => 'Water spraying twice a day.',
        'mepeo_status'           => 'proceed',
        'remarks'                => 'Cleared.',
    ], $overrides);
}

beforeEach(function () {
    Mail::fake();

    $this->engineer = emsUser(User::ROLE_ENGINEER);
    $this->rep = emsUser(User::ROLE_MEPEO_REP);
    $this->mepeoManager = emsUser(User::ROLE_MEPEO_MANAGER);
    $this->pmdAsst = emsUser(User::ROLE_PMD_ASST_MANAGER);
    $this->pmdDept = emsUser(User::ROLE_PMD_DEPT_MANAGER);

    $this->project = Project::create([
        'project_no' => 'PRJ-EMS-' . uniqid(), 'title' => 'Tailings Fence', 'site' => 'Mill Site',
        'asset_id' => 'A1', 'class_name' => 'Minor', 'priority' => '1', 'status_key' => 'PLANNING',
        'work_force' => 'In-House', 'wr_no' => 'WR-1', 'wr_date' => now(), 'dept_owner' => 'MET',
        'cost_code' => 'CC-001', 'category' => 'General', 'service_type' => 'Repair',
        'deadline' => now()->addDays(30), 'created_by' => $this->engineer->id,
        'project_manager_id' => $this->engineer->id,
    ]);

    $this->header = [
        'date_requested' => '2026-10-30',
        'location'       => 'Mill Site',
        'contractor'     => 'Acme Builders',
        'target_start'   => '2026-11-03',
        'target_end'     => '2026-11-28',
    ];
});

/** A report already with the representative. */
function emsSentReport($test): EmsSurveyReport
{
    $test->actingAs($test->engineer)->post(route('hub.ems.store', $test->project), $test->header)->assertSessionHasNoErrors();
    $report = EmsSurveyReport::sole();
    $test->actingAs($test->engineer)->post(route('hub.ems.send', [$test->project, $report]), ['mepeo_rep_id' => $test->rep->id])->assertSessionHasNoErrors();

    return $report->fresh();
}

/** A report the representative has filled out and submitted. */
function emsSubmittedReport($test): EmsSurveyReport
{
    $report = emsSentReport($test);
    $test->actingAs($test->rep)->post(route('mepeo.reports.submit', $report), emsBody())->assertSessionHasNoErrors();

    return $report->fresh();
}

// ── The engineer's side ──────────────────────────────────────────────────────

it('lets the assigned engineer create blank reports, as many as needed', function () {
    $this->actingAs($this->engineer)->post(route('hub.ems.store', $this->project), $this->header)
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($this->engineer)->post(route('hub.ems.store', $this->project), $this->header)
        ->assertSessionHasNoErrors();

    $reports = EmsSurveyReport::orderBy('id')->get();
    expect($reports)->toHaveCount(2)
        ->and($reports[0]->status)->toBe(EmsSurveyReport::STATUS_DRAFT)
        ->and($reports[0]->contractor)->toBe('Acme Builders')
        ->and($reports[0]->major_activities)->toBeNull()
        ->and($reports[0]->control_no)->not->toBe($reports[1]->control_no);

    $this->actingAs($this->engineer)
        ->get(route('projects.hub.ems', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('active_section', 'ems')
            ->where('hub_data.can_manage', true)
            ->has('hub_data.reports', 2)
            ->where('hub_counts.ems', 2));
});

it('keeps other engineers from creating reports on a project not theirs', function () {
    $other = emsUser(User::ROLE_ENGINEER);

    $this->actingAs($other)->post(route('hub.ems.store', $this->project), $this->header)->assertForbidden();

    $this->actingAs($other)
        ->get(route('projects.hub.ems', $this->project))
        ->assertInertia(fn (Assert $page) => $page->where('hub_data.can_manage', false));
});

it('lets the engineer named as project manager raise them too', function () {
    $named = emsUser(User::ROLE_ENGINEER);
    $this->project->update(['project_manager_id' => $named->id]);

    $this->actingAs($named)->post(route('hub.ems.store', $this->project), $this->header)->assertSessionHasNoErrors();

    expect(EmsSurveyReport::count())->toBe(1);
});

it('sends the report to a MEPEO representative by notification and email', function () {
    $report = emsSentReport($this);

    expect($report->status)->toBe(EmsSurveyReport::STATUS_WITH_MEPEO)
        ->and($report->mepeo_rep_id)->toBe($this->rep->id)
        ->and(Notification::where('recipient', $this->rep->id)->count())->toBe(1);

    Mail::assertQueued(EmsReportActivity::class, fn ($mail) => $mail->hasTo($this->rep->email));
});

it('can send by notification alone when the engineer unticks email', function () {
    $this->actingAs($this->engineer)->post(route('hub.ems.store', $this->project), $this->header);
    $report = EmsSurveyReport::sole();

    $this->actingAs($this->engineer)
        ->post(route('hub.ems.send', [$this->project, $report]), ['mepeo_rep_id' => $this->rep->id, 'send_email' => false])
        ->assertSessionHasNoErrors();

    expect(Notification::where('recipient', $this->rep->id)->count())->toBe(1);
    Mail::assertNothingQueued();
});

it('only sends to a MEPEO representative', function () {
    $this->actingAs($this->engineer)->post(route('hub.ems.store', $this->project), $this->header);
    $report = EmsSurveyReport::sole();

    $this->actingAs($this->engineer)
        ->post(route('hub.ems.send', [$this->project, $report]), ['mepeo_rep_id' => $this->pmdAsst->id])
        ->assertSessionHasErrors('mepeo_rep_id');
});

// ── MEPEO filling it out ─────────────────────────────────────────────────────

it('lets the representative fill out and submit, which goes to the MEPEO Manager', function () {
    $report = emsSentReport($this);

    $this->actingAs($this->rep)->patch(route('mepeo.reports.update', $report), emsBody(['mepeo_status' => null]))
        ->assertSessionHasNoErrors();
    expect($report->fresh()->aspects)->toBe(['heavy_equipment', 'dust'])
        ->and($report->fresh()->status)->toBe(EmsSurveyReport::STATUS_WITH_MEPEO);

    $this->actingAs($this->rep)->post(route('mepeo.reports.submit', $report), emsBody())
        ->assertRedirect(route('mepeo.index'));

    $report->refresh();
    expect($report->status)->toBe(EmsSurveyReport::STATUS_FOR_APPROVAL)
        ->and($report->currentApprovalRole())->toBe(User::ROLE_MEPEO_MANAGER)
        ->and(Notification::where('recipient', $this->mepeoManager->id)->count())->toBe(1);
});

it('asks for the status and at least one activity and aspect before submitting', function () {
    $report = emsSentReport($this);

    $this->actingAs($this->rep)
        ->post(route('mepeo.reports.submit', $report), emsBody(['mepeo_status' => null, 'major_activities' => [], 'aspects' => []]))
        ->assertSessionHasErrors('mepeo_status');

    $this->actingAs($this->rep)
        ->post(route('mepeo.reports.submit', $report), emsBody(['major_activities' => [], 'aspects' => []]))
        ->assertSessionHasErrors(['major_activities', 'aspects']);

    expect($report->fresh()->status)->toBe(EmsSurveyReport::STATUS_WITH_MEPEO);
});

it('keeps a representative to the reports sent to them', function () {
    $report = emsSentReport($this);
    $otherRep = emsUser(User::ROLE_MEPEO_REP);

    $this->actingAs($otherRep)->get(route('mepeo.reports.show', $report))->assertNotFound();
    $this->actingAs($otherRep)->patch(route('mepeo.reports.update', $report), emsBody())->assertForbidden();

    $this->actingAs($otherRep)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->component('mepeo/index')->has('reports', 0));
    $this->actingAs($this->rep)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->has('reports', 1)->where('reports.0.needs_me', true));
});

it('fixes the header once MEPEO has submitted', function () {
    $report = emsSubmittedReport($this);

    $this->actingAs($this->engineer)->patch(route('hub.ems.update', [$this->project, $report]), $this->header)->assertStatus(422);
    $this->actingAs($this->engineer)->delete(route('hub.ems.destroy', [$this->project, $report]))->assertStatus(422);
});

// ── The sign-off chain ───────────────────────────────────────────────────────

it('is approved by MEPEO, reviewed by the PMD Assistant Manager and noted by the PMD Manager', function () {
    $report = emsSubmittedReport($this);

    // Not PMD's turn yet.
    $this->actingAs($this->pmdAsst)->patch(route('ems.approve', $report))->assertForbidden();
    $this->actingAs($this->pmdAsst)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->has('ems_reports', 0));

    $this->actingAs($this->mepeoManager)->patch(route('ems.approve', $report), ['remarks' => 'OK'])->assertRedirect();
    expect($report->fresh()->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER);

    $this->actingAs($this->pmdAsst)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->has('ems_reports', 1)->where('ems_reports.0.id', $report->id));
    $this->actingAs($this->pmdAsst)->patch(route('ems.approve', $report))->assertRedirect();

    $this->actingAs($this->pmdDept)->patch(route('ems.approve', $report))->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe(EmsSurveyReport::STATUS_APPROVED)
        ->and($report->approvalChainComplete())->toBeTrue();

    expect(Notification::where('recipient', $this->engineer->id)->where('message', 'like', '%completed its sign-off%')->exists())->toBeTrue();

    // The decision lands in the PMD manager's history.
    $this->actingAs($this->pmdDept)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->where('history.0.type', 'EMS'));
});

it('goes back to the representative when returned, and restarts on resubmission', function () {
    $report = emsSubmittedReport($this);

    $this->actingAs($this->mepeoManager)->patch(route('ems.approve', $report));
    $this->actingAs($this->pmdAsst)->patch(route('ems.return', $report), ['remarks' => ''])->assertSessionHasErrors('remarks');
    $this->actingAs($this->pmdAsst)->patch(route('ems.return', $report), ['remarks' => 'Add the silt fence.'])->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe(EmsSurveyReport::STATUS_WITH_MEPEO)
        ->and($report->return_remarks)->toBe('Add the silt fence.')
        ->and($report->editableBy($this->rep))->toBeTrue();

    $this->actingAs($this->rep)->post(route('mepeo.reports.submit', $report), emsBody(['mitigation' => 'Silt fence added.']))
        ->assertSessionHasNoErrors();

    $report->refresh();
    expect($report->status)->toBe(EmsSurveyReport::STATUS_FOR_APPROVAL)
        ->and($report->return_remarks)->toBeNull()
        ->and($report->currentApprovalRole())->toBe(User::ROLE_MEPEO_MANAGER)
        ->and($report->approvals()->count())->toBe(3);
});

// ── MEPEO sees nothing else ──────────────────────────────────────────────────

it('keeps the MEPEO roles on their own page', function () {
    foreach ([$this->rep, $this->mepeoManager] as $user) {
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('mepeo.index'));
        $this->actingAs($user)->get(route('projects.index'))->assertRedirect(route('mepeo.index'));
        $this->actingAs($user)->get(route('requests.index'))->assertRedirect(route('mepeo.index'));
        $this->actingAs($user)->get(route('projects.hub.ems', $this->project))->assertRedirect(route('mepeo.index'));
        $this->actingAs($user)->post(route('hub.ems.store', $this->project), $this->header)->assertForbidden();

        $this->actingAs($user)->get(route('mepeo.index'))->assertOk();
        $this->actingAs($user)->get(route('account.edit'))->assertOk();
        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
    }
});

it('keeps everyone else off the MEPEO page', function () {
    $this->actingAs($this->engineer)->get(route('mepeo.index'))->assertForbidden();
});

it('counts what waits on each MEPEO user for the menu badge', function () {
    $report = emsSentReport($this);

    $this->actingAs($this->rep)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->where('mepeo_count', 1));

    $this->actingAs($this->rep)->post(route('mepeo.reports.submit', $report), emsBody());

    $this->actingAs($this->rep)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->where('mepeo_count', 0));
    $this->actingAs($this->mepeoManager)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->where('mepeo_count', 1)->where('reports.0.needs_me', true));
});

// ── The printed form ─────────────────────────────────────────────────────────

it('prints FRM-19 with the ticks and the signatures given so far', function () {
    $report = emsSubmittedReport($this);
    $this->actingAs($this->mepeoManager)->patch(route('ems.approve', $report));

    $html = $this->actingAs($this->engineer)->get(route('ems.print', $report))->assertOk()->getContent();

    expect($html)->toContain('PMD-PRJ-FRM-19')
        ->toContain('ENVIRONMENTAL IMPACT ASSESSMENT')
        ->toContain($report->control_no)
        ->toContain('Acme Builders')
        ->toContain('Water spraying twice a day.')
        ->toContain('VERIFIED')
        ->toContain('PREPARED')
        ->not->toContain('NOTED');

    // MEPEO prints the reports it works; a representative not on it cannot.
    $this->actingAs($this->rep)->get(route('ems.print', $report))->assertOk();
    $this->actingAs(emsUser(User::ROLE_MEPEO_REP))->get(route('ems.print', $report))->assertForbidden();
});
