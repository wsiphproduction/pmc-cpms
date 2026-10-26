<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRfq;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function sigUser(string $role, array $attributes = []): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

beforeEach(function () {
    Division::create(['name' => 'MILL']);
    Department::create(['name' => 'MET', 'division' => 'MILL']);

    $this->engineer     = sigUser(User::ROLE_ENGINEER);
    $this->requestor    = sigUser(User::ROLE_REQUESTOR, ['name' => 'Dina Dept', 'department' => 'MET']);
    $this->pmdAsst      = sigUser(User::ROLE_PMD_ASST_MANAGER, ['name' => 'Ana Assistant']);
    $this->pmdAsst2     = sigUser(User::ROLE_PMD_ASST_MANAGER, ['name' => 'Ben Assistant']);
    $this->pmdManager   = sigUser(User::ROLE_PMD_DEPT_MANAGER, ['name' => 'Pia Manager']);
    $this->division     = sigUser(User::ROLE_DIVISION_MANAGER, ['name' => 'Rupert Division']);
    $this->millManager  = sigUser(User::ROLE_DIVISION_MANAGER_USER, ['name' => 'Jon Mill', 'division' => 'MILL']);
    $this->mineManager  = sigUser(User::ROLE_DIVISION_MANAGER_USER, ['name' => 'Earl Mine', 'division' => 'MINE']);

    $this->project = Project::create([
        'project_no' => 'PRJ-SIG-' . uniqid(), 'title' => 'Signatories Project', 'site' => 'Mine',
        'asset_id' => 'A1', 'class_name' => 'Minor', 'priority' => '1', 'status_key' => 'PLANNING',
        'work_force' => 'In-House', 'wr_no' => 'WR-1', 'wr_date' => now(), 'dept_owner' => 'MET',
        'cost_code' => 'CC-001', 'category' => 'General', 'service_type' => 'Repair',
        'deadline' => now()->addDays(30), 'created_by' => $this->engineer->id,
        'project_manager_id' => $this->engineer->id,
    ]);

    $this->actingAs($this->engineer)->post(route('hub.rfq.store', $this->project), [
        'contractor_name' => 'Acme Builders',
    ])->assertRedirect();

    $this->rfq = ProjectRfq::where('project_id', $this->project->id)->firstOrFail();
});

function sigRaiseNtp($test): ProjectNtp
{
    $test->actingAs($test->engineer)->post(route('hub.ntp.store', $test->project), [
        'contractor_name' => 'Acme Builders',
        'project_rfq_id'  => $test->rfq->id,
        'baseline_start'  => now()->addDay()->toDateString(),
        'baseline_end'    => now()->addDays(45)->toDateString(),
        'approved_cost'   => 250000,
    ])->assertRedirect();

    return ProjectNtp::where('project_id', $test->project->id)->latest('id')->firstOrFail();
}

function sigSave($test, array $named, ?User $as = null)
{
    $payload = array_fill_keys(ProjectNtp::APPROVAL_CHAIN, null);

    return $test->actingAs($as ?? $test->engineer)
        ->put(route('hub.signatories.update', $test->project), ['signatories' => array_merge($payload, $named)]);
}

function sigAwaiting(User $user, ProjectNtp $ntp): bool
{
    return Notification::where('recipient', $user->id)
        ->where('message', 'like', "%{$ntp->ntp_no}%awaiting your approval%")
        ->exists();
}

function sigPortal($test, User $user)
{
    return collect($test->actingAs($user)->get(route('approvals.index'))->assertOk()->inertiaPage()['props']['ntps']);
}

it('lists every step with its default signatories', function () {
    $steps = collect($this->actingAs($this->engineer)
        ->get(route('projects.hub.signatories', $this->project))
        ->assertOk()
        ->inertiaPage()['props']['hub_data']['steps'])->keyBy('role');

    expect($steps)->toHaveCount(5)
        ->and($steps[User::ROLE_PMD_ASST_MANAGER]['default_names'])->toBe(['Ana Assistant', 'Ben Assistant'])
        ->and($steps[User::ROLE_PMD_DEPT_MANAGER]['default_names'])->toBe(['Pia Manager'])
        ->and($steps[User::ROLE_DIVISION_MANAGER]['default_names'])->toBe(['Rupert Division'])
        ->and($steps[User::ROLE_REQUESTOR]['default_names'])->toBe(['Dina Dept'])
        ->and($steps[User::ROLE_DIVISION_MANAGER_USER]['default_names'])->toBe(['Jon Mill'])
        ->and($steps[User::ROLE_DIVISION_MANAGER]['user_id'])->toBeNull()
        // The division step can be handed to a division manager user.
        ->and(collect($steps[User::ROLE_DIVISION_MANAGER]['candidates'])->pluck('id'))->toContain($this->mineManager->id);
});

it('gives a step to the person named for it and no one else', function () {
    sigSave($this, [User::ROLE_PMD_ASST_MANAGER => $this->pmdAsst2->id])->assertSessionHasNoErrors();
    $ntp = sigRaiseNtp($this);

    expect(sigAwaiting($this->pmdAsst2, $ntp))->toBeTrue()
        ->and(sigAwaiting($this->pmdAsst, $ntp))->toBeFalse()
        ->and(sigPortal($this, $this->pmdAsst)->pluck('id'))->not->toContain($ntp->id)
        ->and(sigPortal($this, $this->pmdAsst2)->pluck('id'))->toContain($ntp->id);

    $this->actingAs($this->pmdAsst)->patch(route('approvals.ntps.approve', $ntp))->assertForbidden();
    $this->actingAs($this->pmdAsst2)->patch(route('approvals.ntps.approve', $ntp))->assertRedirect();

    expect($ntp->fresh()->currentApprovalRole())->toBe(User::ROLE_PMD_DEPT_MANAGER);
});

it('hands the division manager step to a division manager user, who signs from ntp reviews', function () {
    sigSave($this, [User::ROLE_DIVISION_MANAGER => $this->mineManager->id])->assertSessionHasNoErrors();
    $ntp = sigRaiseNtp($this);

    foreach ([$this->pmdAsst, $this->pmdManager] as $approver) {
        $this->actingAs($approver)->patch(route('approvals.ntps.approve', $ntp))->assertRedirect();
    }

    expect(sigAwaiting($this->mineManager, $ntp))->toBeTrue()
        ->and(Notification::where('recipient', $this->mineManager->id)->latest('id')->value('link'))->toContain('ntp-reviews')
        ->and(sigAwaiting($this->division, $ntp))->toBeFalse()
        ->and(sigPortal($this, $this->division)->pluck('id'))->not->toContain($ntp->id)
        ->and(ProjectNtp::awaitingReviewFrom($this->mineManager)->pluck('id')->all())->toBe([$ntp->id]);

    $listed = collect($this->actingAs($this->mineManager)->get(route('ntp-reviews.index'))->inertiaPage()['props']['ntps']);
    expect($listed->firstWhere('id', $ntp->id)['can_act'])->toBeTrue();

    $this->actingAs($this->mineManager)->patch(route('ntp-reviews.approve', $ntp))->assertRedirect();

    $ntp = $ntp->fresh();
    expect($ntp->currentApprovalRole())->toBe(User::ROLE_REQUESTOR)
        ->and(collect($ntp->approvalTimeline())->firstWhere('role', User::ROLE_DIVISION_MANAGER)['actor'])->toBe('Earl Mine');
});

it('tells the newly named signatory about an ntp already waiting on that step', function () {
    $ntp = sigRaiseNtp($this);
    Notification::query()->delete();

    sigSave($this, [User::ROLE_PMD_ASST_MANAGER => $this->pmdAsst2->id])->assertSessionHasNoErrors();

    expect(sigAwaiting($this->pmdAsst2, $ntp))->toBeTrue()
        ->and(sigAwaiting($this->pmdAsst, $ntp))->toBeFalse();
});

it('puts a step back to its default when cleared', function () {
    sigSave($this, [User::ROLE_PMD_ASST_MANAGER => $this->pmdAsst2->id]);
    sigSave($this, [User::ROLE_PMD_ASST_MANAGER => null])->assertSessionHasNoErrors();

    expect($this->project->ntpSignatories()->count())->toBe(0);

    $ntp = sigRaiseNtp($this);
    $this->actingAs($this->pmdAsst)->patch(route('approvals.ntps.approve', $ntp))->assertRedirect();
});

it('refuses someone who cannot hold the step', function () {
    sigSave($this, [User::ROLE_PMD_ASST_MANAGER => $this->requestor->id])
        ->assertSessionHasErrors('signatories.' . User::ROLE_PMD_ASST_MANAGER);

    expect($this->project->ntpSignatories()->count())->toBe(0);
});

it('leaves the signatories to those who can edit the project', function () {
    sigSave($this, [User::ROLE_PMD_ASST_MANAGER => $this->pmdAsst2->id], $this->requestor)->assertForbidden();
});

// ── The Division Manager step on a site a division manager user has taken ──

it('gives the division manager step to the manager user who has taken the site, who also signs the last step', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $this->mineManager->update(['sites' => ['Mine', 'Sinug-ang']]);
    $ntp = sigRaiseNtp($this);

    foreach ([$this->pmdAsst, $this->pmdManager] as $approver) {
        $this->actingAs($approver)->patch(route('approvals.ntps.approve', $ntp))->assertRedirect();
    }

    expect(sigAwaiting($this->mineManager, $ntp))->toBeTrue()
        ->and(Notification::where('recipient', $this->mineManager->id)->latest('id')->value('link'))->toContain('ntp-reviews')
        ->and(sigAwaiting($this->division, $ntp))->toBeFalse()
        ->and(sigPortal($this, $this->division)->pluck('id'))->not->toContain($ntp->id)
        // Not his step (the Division Manager keeps only the admin override every admin has).
        ->and($ntp->fresh()->awaitingApprovalFrom($this->division))->toBeFalse()
        ->and(ProjectNtp::awaitingReviewFrom($this->mineManager)->pluck('id')->all())->toBe([$ntp->id]);

    $this->actingAs($this->mineManager)->patch(route('ntp-reviews.approve', $ntp))->assertRedirect();
    $this->actingAs($this->requestor)->patch(route('ntp-reviews.approve', $ntp))->assertRedirect();

    // The last step is his too, as the manager who has taken the site.
    expect($ntp->fresh()->awaitingApprovalFrom($this->mineManager))->toBeTrue();
    $this->actingAs($this->mineManager)->patch(route('ntp-reviews.approve', $ntp))->assertRedirect();

    $timeline = collect($ntp->fresh()->approvalTimeline())->keyBy('role');
    expect($ntp->fresh()->status)->toBe('issued')
        ->and($timeline[User::ROLE_DIVISION_MANAGER]['actor'])->toBe('Earl Mine')
        ->and($timeline[User::ROLE_DIVISION_MANAGER_USER]['actor'])->toBe('Earl Mine');
});

it('matches the site whatever its case', function () {
    $this->mineManager->update(['sites' => ['MINE']]);
    $ntp = sigRaiseNtp($this);

    foreach ([$this->pmdAsst, $this->pmdManager] as $approver) {
        $this->actingAs($approver)->patch(route('approvals.ntps.approve', $ntp));
    }

    expect($ntp->fresh()->awaitingApprovalFrom($this->mineManager))->toBeTrue()
        ->and($ntp->fresh()->awaitingApprovalFrom($this->division))->toBeFalse();
});

it('leaves the division manager step with the office holder on a site nobody has taken', function () {
    $this->mineManager->update(['sites' => ['Sinug-ang']]);
    $ntp = sigRaiseNtp($this);

    foreach ([$this->pmdAsst, $this->pmdManager] as $approver) {
        $this->actingAs($approver)->patch(route('approvals.ntps.approve', $ntp))->assertRedirect();
    }

    expect(sigAwaiting($this->division, $ntp))->toBeTrue()
        ->and(sigPortal($this, $this->division)->pluck('id'))->toContain($ntp->id)
        ->and($ntp->fresh()->awaitingApprovalFrom($this->mineManager))->toBeFalse();

    $this->actingAs($this->division)->patch(route('approvals.ntps.approve', $ntp))->assertRedirect();
    expect($ntp->fresh()->currentApprovalRole())->toBe(User::ROLE_REQUESTOR);
});

it('lists and prints the site manager as the division manager step default', function () {
    $this->mineManager->update(['sites' => ['Mine', 'Sinug-ang']]);

    $steps = collect($this->actingAs($this->engineer)
        ->get(route('projects.hub.signatories', $this->project))
        ->inertiaPage()['props']['hub_data']['steps'])->keyBy('role');

    // A default, not a choice the project made.
    expect($steps[User::ROLE_DIVISION_MANAGER]['default_names'])->toBe(['Earl Mine'])
        ->and($steps[User::ROLE_DIVISION_MANAGER]['user_id'])->toBeNull()
        ->and($steps[User::ROLE_DIVISION_MANAGER_USER]['default_names'])->toBe(['Earl Mine']);

    $ntp = sigRaiseNtp($this);
    $this->actingAs($this->engineer)
        ->get(route('print.ntp', [$this->project, $ntp]))
        ->assertOk()
        ->assertSee('Earl Mine');
});

it('still lets a project name someone else over the site manager', function () {
    $this->mineManager->update(['sites' => ['Mine', 'Sinug-ang']]);
    sigSave($this, [User::ROLE_DIVISION_MANAGER => $this->millManager->id])->assertSessionHasNoErrors();
    $ntp = sigRaiseNtp($this);

    foreach ([$this->pmdAsst, $this->pmdManager] as $approver) {
        $this->actingAs($approver)->patch(route('approvals.ntps.approve', $ntp));
    }

    expect($ntp->fresh()->awaitingApprovalFrom($this->millManager))->toBeTrue()
        ->and($ntp->fresh()->awaitingApprovalFrom($this->mineManager))->toBeFalse();
});

it('prints the named signatory on the ntp form', function () {
    sigSave($this, [User::ROLE_DIVISION_MANAGER => $this->mineManager->id]);
    $ntp = sigRaiseNtp($this);

    $this->actingAs($this->engineer)
        ->get(route('print.ntp', [$this->project, $ntp]))
        ->assertOk()
        ->assertSee('Earl Mine');
});

it('emails the department user once the ntp reaches their step, and no one else in the department', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $colleague = User::factory()->create(['department' => 'MET']);
    $ntp = sigRaiseNtp($this);

    foreach ([$this->pmdAsst, $this->pmdManager] as $approver) {
        $this->actingAs($approver)->patch(route('approvals.ntps.approve', $ntp))->assertRedirect();
    }

    // The PMD steps are signed from the approvals portal and stay bell-only.
    \Illuminate\Support\Facades\Mail::assertNothingQueued();

    $this->actingAs($this->division)->patch(route('approvals.ntps.approve', $ntp))->assertRedirect();

    \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\NtpAwaitingReview::class, 1);
    \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\NtpAwaitingReview::class, fn ($mail) => $mail->hasTo($this->requestor->email)
        && $mail->stepLabel === 'Department User'
        && str_contains($mail->reviewUrl, 'ntp-reviews'));
    \Illuminate\Support\Facades\Mail::assertNotQueued(\App\Mail\NtpAwaitingReview::class, fn ($mail) => $mail->hasTo($colleague->email));
});

it('emails the division manager user over the department once the department user signs', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $ntp = sigRaiseNtp($this);

    foreach ([$this->pmdAsst, $this->pmdManager, $this->division] as $approver) {
        $this->actingAs($approver)->patch(route('approvals.ntps.approve', $ntp))->assertRedirect();
    }
    $this->actingAs($this->requestor)->patch(route('ntp-reviews.approve', $ntp))->assertRedirect();

    \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\NtpAwaitingReview::class, fn ($mail) => $mail->hasTo($this->millManager->email)
        && $mail->stepLabel === 'Division Manager User');
    \Illuminate\Support\Facades\Mail::assertNotQueued(\App\Mail\NtpAwaitingReview::class, fn ($mail) => $mail->hasTo($this->mineManager->email));
});

it('emails the site manager when the division manager step falls to them', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $this->mineManager->update(['sites' => ['Mine']]);
    $ntp = sigRaiseNtp($this);

    foreach ([$this->pmdAsst, $this->pmdManager] as $approver) {
        $this->actingAs($approver)->patch(route('approvals.ntps.approve', $ntp))->assertRedirect();
    }

    \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\NtpAwaitingReview::class, fn ($mail) => $mail->hasTo($this->mineManager->email));
    \Illuminate\Support\Facades\Mail::assertNotQueued(\App\Mail\NtpAwaitingReview::class, fn ($mail) => $mail->hasTo($this->division->email));
});

it('renders the review email with the ntp details and a link to ntp reviews', function () {
    $ntp = sigRaiseNtp($this);
    foreach ([$this->pmdAsst, $this->pmdManager, $this->division] as $approver) {
        $this->actingAs($approver)->patch(route('approvals.ntps.approve', $ntp));
    }

    $html = (new \App\Mail\NtpAwaitingReview($ntp->fresh(), $this->requestor))->render();

    expect($html)->toContain($ntp->ntp_no)
        ->toContain('Acme Builders')
        ->toContain($this->project->project_no)
        ->toContain('Dina Dept')
        ->toContain('ntp-reviews');
});
