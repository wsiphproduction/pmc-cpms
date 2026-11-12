<?php

use App\Mail\EmsReportActivity;
use App\Models\EmsSurveyReport;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function emsUser(string $role, array $attributes = []): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

/** The assessment the engineer prepares before submitting. */
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
    ], $overrides);
}

/** MEPEO's part, filled in by the representative to verify it. */
function emsFinding(array $overrides = []): array
{
    return array_merge(['mepeo_status' => 'proceed', 'remarks' => 'Cleared.'], $overrides);
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

/** A draft with the assessment filled in. */
function emsDraft($test): EmsSurveyReport
{
    $test->actingAs($test->engineer)->post(route('hub.ems.store', $test->project), [...$test->header, ...emsBody()])->assertSessionHasNoErrors();

    return EmsSurveyReport::sole();
}

/** Submitted for review: waiting on the PMD Assistant Manager. */
function emsSubmitted($test, array $extra = []): EmsSurveyReport
{
    $report = emsDraft($test);
    $test->actingAs($test->engineer)
        ->post(route('hub.ems.send', [$test->project, $report]), ['mepeo_rep_id' => $test->rep->id, ...$extra])
        ->assertSessionHasNoErrors();

    return $report->fresh();
}

/** Reviewed and noted by PMD: waiting on the representative's verification. */
function emsAtMepeo($test): EmsSurveyReport
{
    $report = emsSubmitted($test);
    $test->actingAs($test->pmdAsst)->patch(route('ems.approve', $report))->assertRedirect();
    $test->actingAs($test->pmdDept)->patch(route('ems.approve', $report))->assertRedirect();

    return $report->fresh();
}

/** Verified by the representative: waiting on the MEPEO Manager. */
function emsVerified($test): EmsSurveyReport
{
    $report = emsAtMepeo($test);
    $test->actingAs($test->rep)->post(route('mepeo.reports.submit', $report), emsFinding())->assertSessionHasNoErrors();

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
        ->and($reports[0]->major_activities)->toBe([])
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

it('lets the engineer fill in the assessment, leaving MEPEO\'s part to MEPEO', function () {
    $this->actingAs($this->engineer)
        ->post(route('hub.ems.store', $this->project), [...$this->header, ...emsBody(), ...emsFinding(['remarks' => 'Typed by the engineer.'])])
        ->assertSessionHasNoErrors();

    $report = EmsSurveyReport::sole();
    expect($report->major_activities)->toBe(['construction'])
        ->and($report->aspects)->toBe(['heavy_equipment', 'dust'])
        ->and($report->mitigation)->toBe('Water spraying twice a day.')
        ->and($report->mepeo_status)->toBeNull()
        ->and($report->remarks)->toBeNull();

    // Unticking everything clears the lists, the way a multipart form sends it.
    $this->actingAs($this->engineer)
        ->patch(route('hub.ems.update', [$this->project, $report]), [...$this->header, 'aspects_other' => 'Noise', 'mepeo_status' => 'survey_required'])
        ->assertSessionHasNoErrors();

    $report->refresh();
    expect($report->major_activities)->toBe([])
        ->and($report->aspects)->toBe([])
        ->and($report->aspects_other)->toBe('Noise')
        ->and($report->mepeo_status)->toBeNull();

    $this->actingAs($this->engineer)
        ->patch(route('hub.ems.update', [$this->project, $report]), [...$this->header, 'aspects' => ['not_on_the_form']])
        ->assertSessionHasErrors('aspects.0');
});

it('lets the engineer named as project manager raise them too', function () {
    $named = emsUser(User::ROLE_ENGINEER);
    $this->project->update(['project_manager_id' => $named->id]);

    $this->actingAs($named)->post(route('hub.ems.store', $this->project), $this->header)->assertSessionHasNoErrors();

    expect(EmsSurveyReport::count())->toBe(1);
});

// ── Submitting for review ────────────────────────────────────────────────────

it('asks for at least one activity and aspect before submitting', function () {
    $this->actingAs($this->engineer)->post(route('hub.ems.store', $this->project), $this->header);
    $report = EmsSurveyReport::sole();

    $this->actingAs($this->engineer)
        ->post(route('hub.ems.send', [$this->project, $report]), ['mepeo_rep_id' => $this->rep->id])
        ->assertSessionHasErrors(['major_activities', 'aspects']);

    expect($report->fresh()->status)->toBe(EmsSurveyReport::STATUS_DRAFT);
});

it('goes to the PMD Assistant Manager first, by notification and email', function () {
    $report = emsSubmitted($this);

    expect($report->status)->toBe(EmsSurveyReport::STATUS_FOR_APPROVAL)
        ->and($report->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER)
        ->and($report->statusLabel())->toBe('For PMD Review')
        ->and($report->mepeo_rep_id)->toBe($this->rep->id)
        ->and($report->submitted_at)->not->toBeNull()
        ->and($report->sent_at)->toBeNull();

    $notice = Notification::where('recipient', $this->pmdAsst->id)->sole();
    expect($notice->message)->toContain($report->control_no);
    $this->actingAs($this->pmdAsst)->get($notice->link)->assertOk();
    Mail::assertQueued(EmsReportActivity::class, fn ($mail) => $mail->hasTo($this->pmdAsst->email));

    // MEPEO hears nothing, and sees nothing, until PMD has signed.
    expect(Notification::where('recipient', $this->rep->id)->count())->toBe(0)
        ->and(Notification::where('recipient', $this->mepeoManager->id)->count())->toBe(0);
    $this->actingAs($this->rep)->get(route('mepeo.index'))->assertInertia(fn (Assert $page) => $page->has('reports', 0));
    $this->actingAs($this->mepeoManager)->get(route('mepeo.index'))->assertInertia(fn (Assert $page) => $page->has('reports', 0));
    $this->actingAs($this->rep)->get(route('mepeo.reports.show', $report))->assertNotFound();
});

it('can submit by notification alone when the engineer unticks email', function () {
    emsSubmitted($this, ['send_email' => false]);

    expect(Notification::where('recipient', $this->pmdAsst->id)->count())->toBe(1);
    Mail::assertNothingQueued();
});

it('only names a MEPEO representative', function () {
    $report = emsDraft($this);

    $this->actingAs($this->engineer)
        ->post(route('hub.ems.send', [$this->project, $report]), ['mepeo_rep_id' => $this->pmdAsst->id])
        ->assertSessionHasErrors('mepeo_rep_id');
});

it('fixes the report once it is submitted', function () {
    $report = emsSubmitted($this);

    $this->actingAs($this->engineer)->patch(route('hub.ems.update', [$this->project, $report]), $this->header)->assertStatus(422);
    $this->actingAs($this->engineer)->delete(route('hub.ems.destroy', [$this->project, $report]))->assertStatus(422);
});

// ── The sign-off chain ───────────────────────────────────────────────────────

it('is reviewed by the PMD Assistant Manager, noted by the PMD Manager, verified by the representative and approved by the MEPEO Manager', function () {
    $report = emsSubmitted($this);

    // Not the PMD Manager's or MEPEO's turn yet.
    $this->actingAs($this->pmdDept)->patch(route('ems.approve', $report))->assertForbidden();
    $this->actingAs($this->mepeoManager)->patch(route('ems.approve', $report))->assertForbidden();
    $this->actingAs($this->pmdDept)->get(route('approvals.index'))->assertInertia(fn (Assert $page) => $page->has('ems_reports', 0));

    $this->actingAs($this->pmdAsst)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->has('ems_reports', 1)->where('ems_reports.0.id', $report->id));
    $this->actingAs($this->pmdAsst)->patch(route('ems.approve', $report))->assertRedirect();
    expect($report->fresh()->currentApprovalRole())->toBe(User::ROLE_PMD_DEPT_MANAGER);

    $this->actingAs($this->pmdDept)->patch(route('ems.approve', $report))->assertRedirect();

    // The PMD Manager's note passes it to MEPEO: the named representative, only.
    $report->refresh();
    expect($report->currentApprovalRole())->toBe(User::ROLE_MEPEO_REP)
        ->and($report->statusLabel())->toBe('With MEPEO')
        ->and($report->sent_at)->not->toBeNull()
        ->and(Notification::where('recipient', $this->rep->id)->where('message', 'like', '%awaiting your verification%')->count())->toBe(1);
    $this->actingAs($this->rep)->get(route('mepeo.reports.show', $report))
        ->assertInertia(fn (Assert $page) => $page->where('can_edit', true)->where('can_sign', false));

    // The verification needs MEPEO's Status; it is given from the MEPEO page.
    $this->actingAs($this->rep)->patch(route('ems.approve', $report))->assertForbidden();
    $this->actingAs($this->rep)->post(route('mepeo.reports.submit', $report), emsFinding(['mepeo_status' => null]))
        ->assertSessionHasErrors('mepeo_status');

    $this->actingAs($this->rep)->post(route('mepeo.reports.submit', $report), emsFinding())
        ->assertRedirect(route('mepeo.index'));

    $report->refresh();
    expect($report->currentApprovalRole())->toBe(User::ROLE_MEPEO_MANAGER)
        ->and($report->mepeo_status)->toBe('proceed')
        ->and($report->remarks)->toBe('Cleared.')
        ->and(Notification::where('recipient', $this->mepeoManager->id)->count())->toBe(1);

    $this->actingAs($this->mepeoManager)->get(route('mepeo.reports.show', $report))
        ->assertInertia(fn (Assert $page) => $page->where('can_edit', false)->where('can_sign', true));
    $this->actingAs($this->mepeoManager)->patch(route('ems.approve', $report), ['remarks' => 'OK'])->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe(EmsSurveyReport::STATUS_APPROVED)
        ->and($report->approvalChainComplete())->toBeTrue()
        ->and(collect($report->approvalTimeline())->pluck('role')->all())->toBe([
            User::ROLE_PMD_ASST_MANAGER, User::ROLE_PMD_DEPT_MANAGER, User::ROLE_MEPEO_REP, User::ROLE_MEPEO_MANAGER,
        ]);

    expect(Notification::where('recipient', $this->engineer->id)->where('message', 'like', '%completed its sign-off%')->exists())->toBeTrue();

    // The decision lands in the PMD manager's history.
    $this->actingAs($this->pmdDept)->get(route('approvals.index'))
        ->assertInertia(fn (Assert $page) => $page->where('history.0.type', 'EMS'));
});

it('lets the representative fill in only MEPEO\'s part', function () {
    $report = emsAtMepeo($this);

    $this->actingAs($this->rep)->patch(route('mepeo.reports.update', $report), [
        ...emsBody(['mitigation' => 'Changed by MEPEO.', 'aspects' => ['dust']]),
        ...emsFinding(['mepeo_status' => 'survey_required', 'remarks' => 'Needs a survey.']),
    ])->assertSessionHasNoErrors();

    $report->refresh();
    expect($report->mitigation)->toBe('Water spraying twice a day.')
        ->and($report->aspects)->toBe(['heavy_equipment', 'dust'])
        ->and($report->mepeo_status)->toBe('survey_required')
        ->and($report->remarks)->toBe('Needs a survey.')
        ->and($report->currentApprovalRole())->toBe(User::ROLE_MEPEO_REP);
});

it('keeps a representative to the reports naming them', function () {
    $report = emsAtMepeo($this);
    $otherRep = emsUser(User::ROLE_MEPEO_REP);

    $this->actingAs($otherRep)->get(route('mepeo.reports.show', $report))->assertNotFound();
    $this->actingAs($otherRep)->patch(route('mepeo.reports.update', $report), emsFinding())->assertForbidden();
    $this->actingAs($otherRep)->post(route('mepeo.reports.submit', $report), emsFinding())->assertForbidden();

    $this->actingAs($otherRep)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->component('mepeo/index')->has('reports', 0));
    $this->actingAs($this->rep)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->has('reports', 1)->where('reports.0.needs_me', true));
});

it('goes back to the engineer when returned, and restarts from PMD on resubmission', function () {
    $report = emsSubmitted($this);

    $this->actingAs($this->pmdAsst)->patch(route('ems.return', $report), ['remarks' => ''])->assertSessionHasErrors('remarks');
    $this->actingAs($this->pmdAsst)->patch(route('ems.return', $report), ['remarks' => 'Add the silt fence.'])->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe(EmsSurveyReport::STATUS_DRAFT)
        ->and($report->return_remarks)->toBe('Add the silt fence.')
        ->and(Notification::where('recipient', $this->engineer->id)->where('message', 'like', '%returned to you%')->count())->toBe(1);

    // A draft again: the engineer revises and resubmits.
    $this->actingAs($this->engineer)
        ->patch(route('hub.ems.update', [$this->project, $report]), [...$this->header, ...emsBody(['mitigation' => 'Silt fence added.'])])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->engineer)
        ->post(route('hub.ems.send', [$this->project, $report]), ['mepeo_rep_id' => $this->rep->id])
        ->assertSessionHasNoErrors();

    $report->refresh();
    expect($report->status)->toBe(EmsSurveyReport::STATUS_FOR_APPROVAL)
        ->and($report->return_remarks)->toBeNull()
        ->and($report->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER)
        ->and($report->approvals()->where('status', 'pending')->count())->toBe(4);
});

it('can be returned to the engineer by MEPEO too', function () {
    $report = emsAtMepeo($this);

    $this->actingAs($this->rep)->patch(route('ems.return', $report), ['remarks' => 'Wrong site.'])
        ->assertRedirect(route('mepeo.index'));
    expect($report->fresh()->status)->toBe(EmsSurveyReport::STATUS_DRAFT);

    // After the representative has verified it, the MEPEO Manager's return tells them as well.
    EmsSurveyReport::query()->delete();
    $report = emsVerified($this);

    $this->actingAs($this->mepeoManager)->patch(route('ems.return', $report), ['remarks' => 'Redo the status.'])->assertRedirect();
    expect($report->fresh()->status)->toBe(EmsSurveyReport::STATUS_DRAFT)
        ->and(Notification::where('recipient', $this->rep->id)->where('message', 'like', '%returned to the engineer%')->count())->toBe(1);
});

it('lets the engineer reassign the representative until they have verified it', function () {
    $report = emsAtMepeo($this);
    $newRep = emsUser(User::ROLE_MEPEO_REP);

    $this->actingAs($this->engineer)
        ->post(route('hub.ems.send', [$this->project, $report]), ['mepeo_rep_id' => $newRep->id])
        ->assertSessionHasNoErrors();

    $report->refresh();
    expect($report->mepeo_rep_id)->toBe($newRep->id)
        ->and($report->currentApprovalRole())->toBe(User::ROLE_MEPEO_REP)
        ->and(Notification::where('recipient', $newRep->id)->count())->toBe(1);
    $this->actingAs($this->rep)->get(route('mepeo.reports.show', $report))->assertNotFound();

    $this->actingAs($newRep)->post(route('mepeo.reports.submit', $report), emsFinding())->assertSessionHasNoErrors();

    $this->actingAs($this->engineer)
        ->post(route('hub.ems.send', [$this->project, $report]), ['mepeo_rep_id' => $this->rep->id])
        ->assertStatus(422);
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
    $report = emsAtMepeo($this);

    $this->actingAs($this->rep)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->where('mepeo_count', 1));
    $this->actingAs($this->mepeoManager)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->where('mepeo_count', 0));

    $this->actingAs($this->rep)->post(route('mepeo.reports.submit', $report), emsFinding());

    $this->actingAs($this->rep)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->where('mepeo_count', 0));
    $this->actingAs($this->mepeoManager)->get(route('mepeo.index'))
        ->assertInertia(fn (Assert $page) => $page->where('mepeo_count', 1)->where('reports.0.needs_me', true));
});

// ── Supporting documents ─────────────────────────────────────────────────────

it('takes supporting documents from the engineer and the representative', function () {
    Storage::fake('public');

    $this->actingAs($this->engineer)->post(route('hub.ems.store', $this->project), [
        ...$this->header,
        ...emsBody(),
        'attachments' => [UploadedFile::fake()->create('site-plan.pdf', 300, 'application/pdf'), UploadedFile::fake()->image('area.jpg')],
    ])->assertSessionHasNoErrors();

    $report = EmsSurveyReport::sole();
    expect($report->attachments)->toHaveCount(2)
        ->and($report->attachments->pluck('filename')->all())->toBe(['site-plan.pdf', 'area.jpg']);
    Storage::disk('public')->assertExists($report->attachments[0]->filepath);

    $this->actingAs($this->engineer)->post(route('hub.ems.send', [$this->project, $report]), ['mepeo_rep_id' => $this->rep->id]);
    $this->actingAs($this->pmdAsst)->patch(route('ems.approve', $report));
    $this->actingAs($this->pmdDept)->patch(route('ems.approve', $report));

    // The representative adds a survey photo and drops the engineer's plan.
    $plan = $report->attachments[0];
    $this->actingAs($this->rep)->patch(route('mepeo.reports.update', $report), [
        ...emsFinding(),
        'attachments'         => [UploadedFile::fake()->image('survey.png')],
        'removed_attachments' => [$plan->id],
    ])->assertSessionHasNoErrors();

    $files = $report->fresh()->attachments;
    expect($files->pluck('filename')->all())->toBe(['area.jpg', 'survey.png']);
    Storage::disk('public')->assertMissing($plan->filepath);

    $this->actingAs($this->rep)
        ->get(route('mepeo.reports.show', $report))
        ->assertInertia(fn (Assert $page) => $page
            ->has('report.attachments', 2)
            ->where('report.attachments.1.filename', 'survey.png')
            ->where('report.attachments.1.uploaded_by', $this->rep->name));
});

it('refuses files that are not documents or images, saving nothing', function () {
    Storage::fake('public');

    $this->actingAs($this->engineer)->post(route('hub.ems.store', $this->project), [
        ...$this->header,
        'attachments' => [UploadedFile::fake()->create('run.exe', 10)],
    ])->assertSessionHasErrors('attachments.0');

    expect(EmsSurveyReport::count())->toBe(0);
});

it('only removes documents that belong to the report', function () {
    Storage::fake('public');

    foreach ([1, 2] as $_) {
        $this->actingAs($this->engineer)->post(route('hub.ems.store', $this->project), [
            ...$this->header, 'attachments' => [UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf')],
        ]);
    }
    [$first, $second] = EmsSurveyReport::orderBy('id')->get()->all();

    $this->actingAs($this->engineer)->patch(route('hub.ems.update', [$this->project, $first]), [
        ...$this->header, 'removed_attachments' => [$second->attachments[0]->id],
    ])->assertSessionHasNoErrors();

    expect($second->fresh()->attachments)->toHaveCount(1);
});

it('deletes the documents with the report', function () {
    Storage::fake('public');

    $this->actingAs($this->engineer)->post(route('hub.ems.store', $this->project), [
        ...$this->header, 'attachments' => [UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf')],
    ]);
    $report = EmsSurveyReport::sole();
    $path = $report->attachments[0]->filepath;

    $this->actingAs($this->engineer)->delete(route('hub.ems.destroy', [$this->project, $report]))->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($path);
    expect(\App\Models\Attachment::where('reference_type', EmsSurveyReport::class)->count())->toBe(0);
});

// ── The printed form ─────────────────────────────────────────────────────────

it('prints the MEPEO Manager\'s comments under Remarks/Comments, and not PMD\'s', function () {
    $report = emsSubmitted($this);

    $this->actingAs($this->pmdAsst)->patch(route('ems.approve', $report), ['remarks' => 'Reviewed without changes.']);
    $this->actingAs($this->pmdDept)->patch(route('ems.approve', $report));
    $this->actingAs($this->rep)->post(route('mepeo.reports.submit', $report), emsFinding());
    $this->actingAs($this->mepeoManager)->patch(route('ems.approve', $report), ['remarks' => 'Keep the silt fence up.']);

    expect($this->actingAs($this->engineer)->get(route('ems.print', $report))->assertOk()->getContent())
        ->toContain('Cleared.')
        ->toContain('MEPEO Manager:</b> Keep the silt fence up.')
        ->not->toContain('Reviewed without changes.');
});

it('prints FRM-19 with the ticks and the signatures given so far', function () {
    $report = emsSubmitted($this);
    $this->actingAs($this->pmdAsst)->patch(route('ems.approve', $report));

    $html = $this->actingAs($this->engineer)->get(route('ems.print', $report))->assertOk()->getContent();

    expect($html)->toContain('PMD-PRJ-FRM-19')
        ->toContain('ENVIRONMENTAL IMPACT ASSESSMENT')
        ->toContain($report->control_no)
        ->toContain('Acme Builders')
        ->toContain('Water spraying twice a day.')
        ->toContain('PREPARED')
        ->toContain('REVIEWED')
        ->not->toContain('NOTED')
        ->not->toContain('VERIFIED');

    $this->actingAs($this->pmdDept)->patch(route('ems.approve', $report));
    $this->actingAs($this->rep)->post(route('mepeo.reports.submit', $report), emsFinding());

    expect($this->actingAs($this->engineer)->get(route('ems.print', $report))->getContent())
        ->toContain('NOTED')
        ->toContain('VERIFIED');

    // MEPEO prints the reports it works; a representative not on it cannot.
    $this->actingAs($this->rep)->get(route('ems.print', $report))->assertOk();
    $this->actingAs(emsUser(User::ROLE_MEPEO_REP))->get(route('ems.print', $report))->assertForbidden();
});

// ── Reports under way when the order changed ────────────────────────────────

it('moves reports under way onto the new sign-off order', function () {
    $migration = require database_path('migrations/2026_11_12_000002_reorder_ems_report_signoff.php');
    $make = fn (string $status, array $attributes = []) => $this->project->emsSurveyReports()->create([
        'control_no' => EmsSurveyReport::nextControlNo(), 'status' => $status, 'date_requested' => '2026-10-30',
        'created_by' => $this->engineer->id, 'mepeo_rep_id' => $this->rep->id, ...$attributes,
    ]);
    $step = fn (EmsSurveyReport $r, string $role, int $sequence, string $status) => $r->approvals()->create([
        'role' => $role, 'sequence' => $sequence, 'status' => $status,
        'user_id' => $status === 'approved' ? $this->mepeoManager->id : null, 'acted_at' => $status === 'approved' ? now() : null,
    ]);

    // With the representative, not yet filled out: back to the engineer.
    $withRep = $make('with_mepeo', ['sent_at' => '2026-11-01 08:00:00']);

    // MEPEO Manager approved, waiting on the PMD Assistant Manager (old order).
    $inSignoff = $make('for_approval', [
        'major_activities' => ['construction'], 'aspects' => ['dust'], 'mepeo_status' => 'proceed',
        'sent_at' => '2026-11-01 08:00:00', 'submitted_at' => '2026-11-02 09:00:00',
    ]);
    $step($inSignoff, User::ROLE_MEPEO_MANAGER, 1, 'approved');
    $step($inSignoff, User::ROLE_PMD_ASST_MANAGER, 2, 'pending');
    $step($inSignoff, User::ROLE_PMD_DEPT_MANAGER, 3, 'pending');

    $migration->up();

    $withRep->refresh();
    expect($withRep->status)->toBe(EmsSurveyReport::STATUS_DRAFT)
        ->and($withRep->sent_at)->toBeNull()
        ->and($withRep->return_remarks)->toContain('PMD now reviews');

    $inSignoff->refresh();
    expect($inSignoff->submitted_at->toDateTimeString())->toBe('2026-11-01 08:00:00')
        ->and($inSignoff->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER)
        ->and(collect($inSignoff->approvalTimeline())->pluck('role')->all())->toBe([
            User::ROLE_PMD_ASST_MANAGER, User::ROLE_PMD_DEPT_MANAGER, User::ROLE_MEPEO_REP, User::ROLE_MEPEO_MANAGER,
        ]);

    // PMD's two signatures complete it — MEPEO already signed under the old order.
    $this->actingAs($this->pmdAsst)->patch(route('ems.approve', $inSignoff))->assertRedirect();
    $this->actingAs($this->pmdDept)->patch(route('ems.approve', $inSignoff))->assertRedirect();
    expect($inSignoff->fresh()->status)->toBe(EmsSurveyReport::STATUS_APPROVED);

    // Running it again changes nothing.
    $migration->up();
    expect(DB::table('approval_steps')->where('approvable_id', $inSignoff->id)->count())->toBe(4);
});
