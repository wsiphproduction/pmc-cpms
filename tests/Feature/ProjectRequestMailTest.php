<?php

use App\Mail\ProjectRequestActivity;
use App\Models\Notification;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function requestMailUser(string $role, ?string $department = null): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create(['department' => $department]);
    $user->assignRole($role);

    return $user;
}

function requestMailPayload(): array
{
    return [
        'title'         => 'Replace roof sheets',
        'job_type'      => 'civil',
        'description'   => 'Leaks over the warehouse.',
        'job_location'  => 'Site A',
        'costcode'      => 'CC-001',
        'opex'          => false,
        'capex'         => false,
        'for_budgeting' => true,
    ];
}

/** The mails queued to one user, as [headline, …]. */
function requestMailsTo(User $user): array
{
    return Mail::queued(ProjectRequestActivity::class, fn ($mail) => $mail->hasTo($user->email))
        ->map(fn (ProjectRequestActivity $mail) => $mail->headline)
        ->values()
        ->all();
}

beforeEach(function () {
    Mail::fake();

    $this->supervisor = requestMailUser(User::ROLE_DEPT_SUPERVISOR, 'Mill');
    $this->manager    = requestMailUser(User::ROLE_REQUESTOR, 'Mill');
    $this->engineer   = requestMailUser(User::ROLE_ENGINEER);
    $this->pmdAsst    = requestMailUser(User::ROLE_PMD_ASST_MANAGER);
    $this->pmdManager = requestMailUser(User::ROLE_PMD_DEPT_MANAGER);
});

it('mails every bell notification of a request, through the whole flow', function () {
    $this->actingAs($this->supervisor)->post(route('requests.store'), requestMailPayload());
    $pr = ProjectRequest::latest('id')->firstOrFail();

    // Raised: the department manager is called on.
    expect(requestMailsTo($this->manager))->toHaveCount(1);

    $this->actingAs($this->manager)->patch(route('requests.update', $pr), ['status' => 'approved']);

    expect(requestMailsTo($this->supervisor))->toHaveCount(1)
        ->and(requestMailsTo($this->engineer))->toHaveCount(1)
        ->and(requestMailsTo($this->pmdManager)[0])->toContain('You can now assign a project engineer');

    $this->actingAs($this->engineer)->patch(route('requests.update', $pr), ['status' => 'approved']);
    expect(requestMailsTo($this->pmdAsst)[0])->toContain('is awaiting your approval');

    $this->actingAs($this->pmdAsst)->patch(route('approvals.requests.approve', $pr));
    $this->actingAs($this->pmdManager)->patch(route('approvals.requests.approve', $pr));

    expect(requestMailsTo($this->supervisor))->toContain("Project Request #{$pr->request_no} has been fully approved.");

    // One mail for every bell notification the flow raised.
    expect(Mail::queued(ProjectRequestActivity::class)->count())
        ->toBe(Notification::count());
});

it('mails the hold and the rejection to the requester', function () {
    $this->actingAs($this->supervisor)->post(route('requests.store'), requestMailPayload());
    $pr = ProjectRequest::latest('id')->firstOrFail();

    $this->actingAs($this->manager)->postJson(route('comments.store', $pr), ['content' => 'Attach photos.']);
    $this->actingAs($this->manager)->patch(route('requests.update', $pr), ['status' => 'rejected']);

    expect(requestMailsTo($this->supervisor))
        ->toContain("Project Request #{$pr->request_no} was put ON HOLD after a comment from the Department Manager.")
        ->toContain("Project Request #{$pr->request_no} was rejected by the Department Manager.");
});

it('mails an engineer assigned ahead of the final signature', function () {
    $pr = ProjectRequest::factory()->create(['status' => 'pending']);
    $pr->startApprovalChain();

    $this->actingAs($this->pmdManager)
        ->patch(route('requests.assign-engineer', $pr), ['engineer_id' => $this->engineer->id]);

    expect(requestMailsTo($this->engineer))
        ->toBe(["You were assigned as project engineer for Project Request #{$pr->request_no}."]);
});

it('writes the mail with the request details and a link back to it', function () {
    $pr = ProjectRequest::factory()->create(['status' => 'pending', 'requester_id' => $this->supervisor->id, 'title' => 'Fix the gate']);

    $body = (new ProjectRequestActivity($pr, $this->manager, 'Something happened.', url('/requests/' . $pr->id)))->render();

    expect($body)
        ->toContain($pr->request_no)
        ->toContain('FIX THE GATE')
        ->toContain('Something happened.')
        ->toContain('(Mill)')
        ->toContain('For Approval')
        ->toContain(url('/requests/' . $pr->id));
});

it('still raises the bell when a mail cannot be queued', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    $pr = ProjectRequest::factory()->create(['status' => 'pending']);

    Notification::notifyAboutRequest($pr, $this->engineer->id, 'Hello.');

    expect(Notification::where('recipient', $this->engineer->id)->count())->toBe(1);
});
