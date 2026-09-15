<?php

use App\Mail\NtpIssuedToVendor;
use App\Models\Department;
use App\Models\Division;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRfq;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

const NTP_DEPARTMENT = 'Engineering';
const NTP_DIVISION   = 'Technical Services';

function makeUserWithRole(string $role, array $attributes = []): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

function makeProjectForNtp(User $engineer): Project
{
    return Project::create([
        'project_no'         => 'PRJ-NTP-' . uniqid(),
        'title'              => 'NTP Test Project',
        'site'               => 'Main Plant',
        'asset_id'           => 'A1',
        'class_name'         => 'Minor',
        'priority'           => '1',
        'status_key'         => 'PLANNING',
        'work_force'         => 'In-House',
        'wr_no'              => 'WR-1',
        'wr_date'            => now(),
        'dept_owner'         => NTP_DEPARTMENT,
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

    // The department sits in a division, whose manager user gives the last signature.
    Division::create(['name' => NTP_DIVISION]);
    Department::create(['name' => NTP_DEPARTMENT, 'division' => NTP_DIVISION]);

    $this->engineer     = makeUserWithRole(User::ROLE_ENGINEER);
    $this->requestor    = makeUserWithRole(User::ROLE_REQUESTOR, ['department' => NTP_DEPARTMENT]);
    $this->pmdAsst      = makeUserWithRole(User::ROLE_PMD_ASST_MANAGER);
    $this->pmdManager   = makeUserWithRole(User::ROLE_PMD_DEPT_MANAGER);
    $this->division     = makeUserWithRole(User::ROLE_DIVISION_MANAGER);
    $this->divisionUser = makeUserWithRole(User::ROLE_DIVISION_MANAGER_USER, ['division' => NTP_DIVISION]);

    $this->project = makeProjectForNtp($this->engineer);

    $this->actingAs($this->engineer)->post(route('hub.rfq.store', $this->project), [
        'contractor_name' => 'Acme Builders',
        'recipient_email' => 'vendor@example.com',
    ])->assertRedirect();

    $this->rfq = ProjectRfq::where('project_id', $this->project->id)->firstOrFail();

    $this->actingAs($this->engineer)->post(route('hub.ntp.store', $this->project), [
        'contractor_name' => 'Acme Builders',
        'project_rfq_id'  => $this->rfq->id,
        'baseline_start'  => now()->addDay()->toDateString(),
        'baseline_end'    => now()->addDays(45)->toDateString(),
        'approved_cost'   => 250000,
    ])->assertRedirect();

    $this->ntp = ProjectNtp::where('project_id', $this->project->id)->firstOrFail();
});

/** The three PMD offices sign from the approvals portal. */
function approvePmdSteps($test): void
{
    foreach ([$test->pmdAsst, $test->pmdManager, $test->division] as $approver) {
        $test->actingAs($approver)
            ->patch(route('approvals.ntps.approve', $test->ntp))->assertRedirect();
    }
}

/** Walk the whole chain: the PMD offices, then the department and its division. */
function approveWholeChain($test): void
{
    approvePmdSteps($test);

    foreach ([$test->requestor, $test->divisionUser] as $reviewer) {
        $test->actingAs($reviewer)
            ->patch(route('ntp-reviews.approve', $test->ntp))->assertRedirect();
    }
}

it('signs the ntp in order: pmd assistant, pmd manager, division manager, department, division manager user', function () {
    expect($this->ntp->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER);

    $this->actingAs($this->pmdAsst)->patch(route('approvals.ntps.approve', $this->ntp))->assertRedirect();
    expect($this->ntp->fresh()->currentApprovalRole())->toBe(User::ROLE_PMD_DEPT_MANAGER);

    $this->actingAs($this->pmdManager)->patch(route('approvals.ntps.approve', $this->ntp))->assertRedirect();
    expect($this->ntp->fresh()->currentApprovalRole())->toBe(User::ROLE_DIVISION_MANAGER);

    $this->actingAs($this->division)->patch(route('approvals.ntps.approve', $this->ntp))->assertRedirect();
    expect($this->ntp->fresh()->currentApprovalRole())->toBe(User::ROLE_REQUESTOR)
        ->and($this->ntp->fresh()->status)->toBe('pending_review');

    $this->actingAs($this->requestor)->patch(route('ntp-reviews.approve', $this->ntp))->assertRedirect();
    expect($this->ntp->fresh()->currentApprovalRole())->toBe(User::ROLE_DIVISION_MANAGER_USER)
        ->and($this->ntp->fresh()->status)->toBe('pending_review');

    $this->actingAs($this->divisionUser)->patch(route('ntp-reviews.approve', $this->ntp))->assertRedirect();

    $ntp = $this->ntp->fresh();
    expect($ntp->currentApprovalRole())->toBeNull()
        ->and($ntp->status)->toBe('issued')
        ->and($ntp->approvalChainComplete())->toBeTrue();
});

it('refuses an approver who is not the one being waited on', function () {
    // The department cannot jump ahead of PMD.
    $this->actingAs($this->requestor)
        ->patch(route('ntp-reviews.approve', $this->ntp))
        ->assertForbidden();

    // Nor can the Division Manager skip the Assistant Manager.
    $this->actingAs($this->division)
        ->patch(route('approvals.ntps.approve', $this->ntp))
        ->assertForbidden();

    expect($this->ntp->fresh()->status)->toBe('pending_review');
});

it('keeps the final step to the manager user of the department\'s own division', function () {
    approvePmdSteps($this);
    $this->actingAs($this->requestor)->patch(route('ntp-reviews.approve', $this->ntp))->assertRedirect();

    // Same role, different division: not the one being waited on.
    $otherDivision = makeUserWithRole(User::ROLE_DIVISION_MANAGER_USER, ['division' => 'Mining']);
    $this->actingAs($otherDivision)
        ->patch(route('ntp-reviews.approve', $this->ntp))
        ->assertForbidden();

    // And the Division Manager office, which already signed, cannot sign for it.
    $this->actingAs($this->division)
        ->patch(route('approvals.ntps.approve', $this->ntp))
        ->assertForbidden();

    expect($this->ntp->fresh()->status)->toBe('pending_review');

    $this->actingAs($this->divisionUser)->patch(route('ntp-reviews.approve', $this->ntp))->assertRedirect();
    expect($this->ntp->fresh()->status)->toBe('issued');
});

it('lists the ntp on the division manager user\'s review page once it is their turn', function () {
    $listed = fn () => collect($this->actingAs($this->divisionUser)
        ->get(route('ntp-reviews.index'))
        ->assertOk()
        ->inertiaPage()['props']['ntps']);

    // Visible throughout, but not actionable until the department has signed.
    expect($listed()->pluck('id'))->toContain($this->ntp->id)
        ->and($listed()->firstWhere('id', $this->ntp->id)['can_act'])->toBeFalse();

    approvePmdSteps($this);
    $this->actingAs($this->requestor)->patch(route('ntp-reviews.approve', $this->ntp))->assertRedirect();

    expect($listed()->firstWhere('id', $this->ntp->id)['can_act'])->toBeTrue();

    // A division manager user elsewhere never sees it.
    $outsider = makeUserWithRole(User::ROLE_DIVISION_MANAGER_USER, ['division' => 'Mining']);
    $others = collect($this->actingAs($outsider)->get(route('ntp-reviews.index'))->inertiaPage()['props']['ntps']);
    expect($others->pluck('id'))->not->toContain($this->ntp->id);
});

it('records who signed each step, for the printed form to stamp', function () {
    approveWholeChain($this);

    $timeline = collect($this->ntp->fresh()->approvalTimeline())->keyBy('role');

    expect($timeline)->toHaveCount(5);

    foreach ([
        User::ROLE_PMD_ASST_MANAGER      => $this->pmdAsst,
        User::ROLE_PMD_DEPT_MANAGER      => $this->pmdManager,
        User::ROLE_DIVISION_MANAGER      => $this->division,
        User::ROLE_REQUESTOR             => $this->requestor,
        User::ROLE_DIVISION_MANAGER_USER => $this->divisionUser,
    ] as $role => $signer) {
        expect($timeline[$role]['status'])->toBe('approved')
            ->and($timeline[$role]['actor'])->toBe($signer->name)
            ->and($timeline[$role]['acted_at'])->not->toBeNull();
    }
});

it('will not send an ntp to the vendor before the chain completes', function () {
    $this->actingAs($this->engineer)
        ->post(route('hub.ntp.send', [$this->project, $this->ntp]), [
            'recipient_email' => 'vendor@example.com',
        ])
        ->assertSessionHas('error');

    Mail::assertNotSent(NtpIssuedToVendor::class);
    expect($this->ntp->fresh()->vendor_notified_at)->toBeNull();
});

it('sends the issued ntp to the vendor with copies', function () {
    approveWholeChain($this);

    $this->actingAs($this->engineer)
        ->post(route('hub.ntp.send', [$this->project, $this->ntp]), [
            'recipient_email'       => 'newvendor@example.com',
            'additional_recipients' => ['purchasing@example.com'],
            'cc_self'               => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->ntp->fresh()->vendor_notified_at)->not->toBeNull();

    Mail::assertQueued(NtpIssuedToVendor::class, fn ($mail) => $mail->hasTo('newvendor@example.com')
        && $mail->hasCc('purchasing@example.com')
        && $mail->hasCc($this->engineer->email));
});

it('names every signatory and their date in the vendor email', function () {
    approveWholeChain($this);

    $this->actingAs($this->engineer)
        ->post(route('hub.ntp.send', [$this->project, $this->ntp]), [
            'recipient_email' => 'vendor@example.com',
        ])->assertRedirect();

    Mail::assertQueued(NtpIssuedToVendor::class, function ($mail) {
        $body = $mail->render();

        return str_contains($body, $this->ntp->ntp_no)
            && str_contains($body, 'Acme Builders')
            && str_contains($body, $this->division->name)
            && str_contains($body, $this->pmdManager->name);
    });
});

it('rejects a malformed vendor address', function () {
    approveWholeChain($this);

    $this->actingAs($this->engineer)
        ->post(route('hub.ntp.send', [$this->project, $this->ntp]), [
            'recipient_email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('recipient_email');

    Mail::assertNotSent(NtpIssuedToVendor::class);
});

it('links the vendor to the approved form and says when the link expires', function () {
    approveWholeChain($this);

    $this->actingAs($this->engineer)
        ->post(route('hub.ntp.send', [$this->project, $this->ntp]), [
            'recipient_email' => 'vendor@example.com',
        ])->assertRedirect();

    Mail::assertQueued(NtpIssuedToVendor::class, function (NtpIssuedToVendor $mail) {
        $body = $mail->render();

        return str_contains($body, 'View Approved NTP')
            && str_contains($body, e($mail->documentUrl))
            && str_contains($body, 'valid for 5 days')
            && str_contains($mail->documentUrl, 'signature=')
            && $mail->documentExpiresAt->isSameDay(now()->addDays(5));
    });
});

it('opens the approved form from the mailed link without signing in', function () {
    approveWholeChain($this);

    $mail = new NtpIssuedToVendor($this->ntp->fresh(), $this->project);

    $this->get($mail->documentUrl)
        ->assertOk()
        ->assertSee('NOTICE TO PROCEED')
        ->assertSee($this->ntp->ntp_no)
        ->assertSee('APPROVED');
});

it('refuses the vendor link once it has expired or been tampered with', function () {
    approveWholeChain($this);

    $mail = new NtpIssuedToVendor($this->ntp->fresh(), $this->project);

    $this->get($mail->documentUrl . 'x')->assertForbidden();

    $this->travel(6)->days();
    $this->get($mail->documentUrl)->assertForbidden();
});

it('never shows an unissued ntp through the vendor link', function () {
    // Only the PMD Assistant Manager has signed — the form would be half-stamped.
    $this->actingAs($this->pmdAsst)
        ->patch(route('approvals.ntps.approve', $this->ntp))->assertRedirect();

    $mail = new NtpIssuedToVendor($this->ntp->fresh(), $this->project);

    $this->get($mail->documentUrl)->assertNotFound();
});
