<?php

use App\Mail\NtpAwaitingReview;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRfq;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

function reminderUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function () {
    Mail::fake();

    $this->engineer   = reminderUser(User::ROLE_ENGINEER);
    $this->pmdAsst    = reminderUser(User::ROLE_PMD_ASST_MANAGER);
    $this->pmdManager = reminderUser(User::ROLE_PMD_DEPT_MANAGER);

    $this->project = Project::create([
        'project_no'         => 'PRJ-REM-' . uniqid(),
        'title'              => 'Reminder Test Project',
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
        'created_by'         => $this->engineer->id,
        'project_manager_id' => $this->engineer->id,
    ]);

    $this->actingAs($this->engineer)->post(route('hub.rfq.store', $this->project), [
        'contractor_name' => 'Acme Builders',
        'recipient_email' => 'vendor@example.com',
    ])->assertRedirect();

    $rfq = ProjectRfq::where('project_id', $this->project->id)->firstOrFail();

    $this->actingAs($this->engineer)->post(route('hub.ntp.store', $this->project), [
        'contractor_name' => 'Acme Builders',
        'project_rfq_id'  => $rfq->id,
        'baseline_start'  => now()->addDay()->toDateString(),
        'baseline_end'    => now()->addDays(45)->toDateString(),
        'approved_cost'   => 250000,
    ])->assertRedirect();

    $this->ntp = ProjectNtp::where('project_id', $this->project->id)->firstOrFail();
});

afterEach(fn () => $this->travelBack());

/** Mail the reminder run queued for this user, leaving out the notice the NTP first sent. */
function remindersTo(User $user)
{
    return Mail::queued(NtpAwaitingReview::class, fn ($mail) => $mail->hasTo($user->email) && $mail->waitingDays !== null);
}

it('leaves an NTP alone for its first week at a step', function () {
    $this->travel(6)->days();

    $this->artisan('ntps:remind-stalled')->assertSuccessful();

    expect(remindersTo($this->pmdAsst))->toHaveCount(0);
});

it('reminds the signer after a week, and again each week until they act', function () {
    $this->travel(7)->days();
    $this->artisan('ntps:remind-stalled')->assertSuccessful();

    expect(remindersTo($this->pmdAsst))->toHaveCount(1)
        ->and(remindersTo($this->pmdAsst)->first()->waitingDays)->toBe(7)
        ->and(remindersTo($this->pmdManager))->toHaveCount(0)
        ->and(Notification::where('recipient', $this->pmdAsst->id)->where('message', 'like', 'Reminder: NTP%')->count())->toBe(1);

    // Running again the same week sends nothing more.
    $this->travel(3)->days();
    $this->artisan('ntps:remind-stalled')->assertSuccessful();
    expect(remindersTo($this->pmdAsst))->toHaveCount(1);

    $this->travel(4)->days();
    $this->artisan('ntps:remind-stalled')->assertSuccessful();
    expect(remindersTo($this->pmdAsst))->toHaveCount(2)
        ->and(remindersTo($this->pmdAsst)->last()->waitingDays)->toBe(14);
});

it('starts the week over when the NTP moves to the next signer', function () {
    $this->travel(5)->days();
    $this->actingAs($this->pmdAsst)->patch(route('approvals.ntps.approve', $this->ntp))->assertRedirect();

    // Twelve days old, but only five days with the Department Manager.
    $this->travel(5)->days();
    $this->artisan('ntps:remind-stalled')->assertSuccessful();
    expect(remindersTo($this->pmdManager))->toHaveCount(0);

    $this->travel(2)->days();
    $this->artisan('ntps:remind-stalled')->assertSuccessful();
    expect(remindersTo($this->pmdManager))->toHaveCount(1)
        ->and(remindersTo($this->pmdAsst))->toHaveCount(0);
});

it('sends no reminders once the NTP is rejected', function () {
    $this->actingAs($this->pmdAsst)->patch(route('approvals.ntps.reject', $this->ntp), ['remarks' => 'No'])->assertRedirect();

    $this->travel(10)->days();
    $this->artisan('ntps:remind-stalled')->assertSuccessful();

    expect(Mail::queued(NtpAwaitingReview::class, fn ($mail) => $mail->waitingDays !== null))->toHaveCount(0);
});

it('searches every past decision, not only the latest', function () {
    $this->actingAs($this->pmdAsst)->patch(route('approvals.ntps.approve', $this->ntp))->assertRedirect();

    $history = fn (string $search) => collect($this->actingAs($this->pmdAsst)
        ->get(route('approvals.index', ['search' => $search]))
        ->assertOk()
        ->inertiaPage()['props']['history']);

    expect($history('acme')->pluck('label'))->toContain("{$this->ntp->ntp_no} — Acme Builders")
        ->and($history('reminder test project'))->toHaveCount(1)
        ->and($history($this->ntp->ntp_no))->toHaveCount(1)
        ->and($history('no such contractor'))->toHaveCount(0);
});
