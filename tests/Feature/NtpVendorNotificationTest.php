<?php

use App\Mail\NtpIssuedToVendor;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRfq;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

const NTP_DEPARTMENT = 'Engineering';

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

    $this->engineer   = makeUserWithRole(User::ROLE_ENGINEER);
    $this->requestor  = makeUserWithRole(User::ROLE_REQUESTOR, ['department' => NTP_DEPARTMENT]);
    $this->pmdAsst    = makeUserWithRole(User::ROLE_PMD_ASST_MANAGER);
    $this->pmdManager = makeUserWithRole(User::ROLE_PMD_DEPT_MANAGER);
    $this->division   = makeUserWithRole(User::ROLE_DIVISION_MANAGER);

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

/** Walk the whole chain: department, then the three PMD offices. */
function approveWholeChain($test): void
{
    $test->actingAs($test->requestor)
        ->patch(route('ntp-reviews.approve', $test->ntp))->assertRedirect();

    foreach ([$test->pmdAsst, $test->pmdManager, $test->division] as $approver) {
        $test->actingAs($approver)
            ->patch(route('approvals.ntps.approve', $test->ntp))->assertRedirect();
    }
}

it('signs the ntp in order: department, pmd assistant, pmd manager, division manager', function () {
    expect($this->ntp->currentApprovalRole())->toBe(User::ROLE_REQUESTOR);

    $this->actingAs($this->requestor)->patch(route('ntp-reviews.approve', $this->ntp))->assertRedirect();
    expect($this->ntp->fresh()->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER);

    $this->actingAs($this->pmdAsst)->patch(route('approvals.ntps.approve', $this->ntp))->assertRedirect();
    expect($this->ntp->fresh()->currentApprovalRole())->toBe(User::ROLE_PMD_DEPT_MANAGER);

    $this->actingAs($this->pmdManager)->patch(route('approvals.ntps.approve', $this->ntp))->assertRedirect();
    expect($this->ntp->fresh()->currentApprovalRole())->toBe(User::ROLE_DIVISION_MANAGER);

    $this->actingAs($this->division)->patch(route('approvals.ntps.approve', $this->ntp))->assertRedirect();

    $ntp = $this->ntp->fresh();
    expect($ntp->currentApprovalRole())->toBeNull()
        ->and($ntp->status)->toBe('issued')
        ->and($ntp->approvalChainComplete())->toBeTrue();
});

it('refuses an approver who is not the one being waited on', function () {
    // The Division Manager cannot jump ahead of the department step.
    $this->actingAs($this->division)
        ->patch(route('approvals.ntps.approve', $this->ntp))
        ->assertForbidden();

    expect($this->ntp->fresh()->status)->toBe('pending_review');
});

it('records who signed each step, for the printed form to stamp', function () {
    approveWholeChain($this);

    $timeline = collect($this->ntp->fresh()->approvalTimeline())->keyBy('role');

    expect($timeline)->toHaveCount(4);

    foreach ([
        User::ROLE_REQUESTOR        => $this->requestor,
        User::ROLE_PMD_ASST_MANAGER => $this->pmdAsst,
        User::ROLE_PMD_DEPT_MANAGER => $this->pmdManager,
        User::ROLE_DIVISION_MANAGER => $this->division,
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

    Mail::assertSent(NtpIssuedToVendor::class, fn ($mail) => $mail->hasTo('newvendor@example.com')
        && $mail->hasCc('purchasing@example.com')
        && $mail->hasCc($this->engineer->email));
});

it('names every signatory and their date in the vendor email', function () {
    approveWholeChain($this);

    $this->actingAs($this->engineer)
        ->post(route('hub.ntp.send', [$this->project, $this->ntp]), [
            'recipient_email' => 'vendor@example.com',
        ])->assertRedirect();

    Mail::assertSent(NtpIssuedToVendor::class, function ($mail) {
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
