<?php

use App\Models\Category;
use App\Models\CostCode;
use App\Models\Department;
use App\Models\MasterClass;
use App\Models\MasterStatus;
use App\Models\Notification;
use App\Models\Priority;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\ServiceType;
use App\Models\Site;
use App\Models\Structure;
use App\Models\User;
use App\Models\WorkForce;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeAdminUserForNotifications(): User
{
    Role::firstOrCreate(['name' => 'admin']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function seedProjectMasterDataForNotifications(): void
{
    Site::create(['name' => 'Main Plant']);
    Structure::create(['name' => 'Asset A']);
    Department::create(['name' => 'Engineering']);
    MasterClass::create(['name' => 'Major']);
    Priority::create(['name' => 'P1 - Urgent']);
    MasterStatus::create(['name' => 'For Planning']);
    WorkForce::create(['name' => 'In-house Team']);
    CostCode::create(['name' => 'CC-001']);
    Category::create(['name' => 'Renovation']);
    ServiceType::create(['name' => 'Design-Build']);
}

function projectPayloadForNotifications(User $manager, array $overrides = []): array
{
    return array_merge([
        'project_type' => 'minor',
        'title' => 'Cooling Tower Upgrade',
        'project_manager' => (string) $manager->id,
        'site' => 'Main Plant',
        'asset_id' => 'Asset A',
        'cls' => 'Major',
        'priority' => 'P1 - Urgent',
        'status' => 'PLANNING',
        'work_force' => 'In-house Team',
        'wr_no' => '1234',
        'wr_date' => '2026-05-04',
        'dept_owner' => 'Engineering',
        'cost_code' => 'CC-001',
        'category' => 'Renovation',
        'service_type' => 'Design-Build',
        'deadline' => '2026-06-04',
        'owner_email' => 'owner@example.com',
        'structure_type' => 'Asset A',
        'jip' => true,
        'need_civil' => true,
        'need_electrical' => false,
        'need_mechanical' => true,
        'notes' => 'Initial project notes.',
    ], $overrides);
}

function makeRequestorUser(): User
{
    Role::firstOrCreate(['name' => 'requestor']);

    $user = User::factory()->create();
    $user->assignRole('requestor');

    return $user;
}

function makeApproverUserForNotifications(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function notificationRequestPayload(array $overrides = []): array
{
    Storage::fake('public');
    $file = UploadedFile::fake()->create('drawing.pdf', 500, 'application/pdf');

    return array_merge([
        'title'         => 'Notification Test Request',
        'job_type'      => 'civil',
        'description'   => 'Some description.',
        'job_location'  => 'Site A',
        'costcode'      => 'CC-001',
        'opex'          => false,
        'capex'         => false,
        'for_budgeting' => true,
        'attachments'   => [['file' => $file, 'type' => 'drawing']],
    ], $overrides);
}

it('notifies all approvers when a request is created', function () {
    $requestor = makeRequestorUser();
    $approverA = makeApproverUserForNotifications();
    $approverB = makeApproverUserForNotifications();

    $this->actingAs($requestor)
        ->post(route('requests.store'), notificationRequestPayload())
        ->assertRedirect(route('requests.index'));

    $projectRequest = ProjectRequest::firstOrFail();

    expect(Notification::where('recipient', $approverA->id)->count())->toBe(1);
    expect(Notification::where('recipient', $approverB->id)->count())->toBe(1);
    expect(Notification::where('recipient', $requestor->id)->count())->toBe(0);

    $notification = Notification::where('recipient', $approverA->id)->first();
    expect($notification->message)->toBe("New Project Request from {$requestor->name}");
    expect($notification->link)->toBe(route('requests.show', $projectRequest->id, absolute: false));
    expect($notification->is_read)->toBeFalse();
});

it('does not throw when creating a request and no approvers exist yet', function () {
    $requestor = makeRequestorUser();

    $this->actingAs($requestor)
        ->post(route('requests.store'), notificationRequestPayload())
        ->assertRedirect(route('requests.index'));

    expect(Notification::count())->toBe(0);
    expect(ProjectRequest::count())->toBe(1);
});

it('notifies all approvers when a request is edited', function () {
    $requestor = makeRequestorUser();
    $approver = makeApproverUserForNotifications();
    $pr = ProjectRequest::factory()->create(['requester_id' => $requestor->id]);

    Storage::fake('public');
    $file = UploadedFile::fake()->create('drawing.pdf', 500, 'application/pdf');

    $this->actingAs($requestor)
        ->put(route('requests.update', $pr), [
            'title'         => 'Updated Title',
            'job_type'      => $pr->job_type,
            'description'   => $pr->description,
            'job_location'  => $pr->job_location,
            'costcode'      => $pr->costcode,
            'opex'          => false,
            'capex'         => false,
            'for_budgeting' => true,
            'attachments'   => [['file' => $file, 'type' => 'drawing']],
        ])
        ->assertRedirect(route('requests.index'));

    $notification = Notification::where('recipient', $approver->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->message)->toBe("Project Request #{$pr->request_no} has been updated");
});

it('notifies the requester when a request status changes', function () {
    $requestor = makeRequestorUser();
    $approver = makeApproverUserForNotifications();
    $pr = ProjectRequest::factory()->create(['requester_id' => $requestor->id, 'status' => 'pending']);

    $this->actingAs($approver)
        ->patch(route('requests.update', $pr), ['status' => 'approved'])
        ->assertRedirect();

    // The engineer's approval is the first signature of the chain, so the
    // requester is told who it moved on to rather than that it is settled.
    $notification = Notification::where('recipient', $requestor->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->message)->toBe(
        "Project Request #{$pr->request_no} was approved by the Project Engineer and is now with the PMD Assistant Manager."
    );
    expect(Notification::where('recipient', $approver->id)->count())->toBe(0);
});

it('notifies approvers when the requester comments on their own request', function () {
    $requestor = makeRequestorUser();
    $approver = makeApproverUserForNotifications();
    $pr = ProjectRequest::factory()->create(['requester_id' => $requestor->id]);

    $this->actingAs($requestor)
        ->post(route('comments.store', $pr), ['content' => 'Any update on this?'])
        ->assertOk();

    $notification = Notification::where('recipient', $approver->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->message)->toBe("New Comment was added to project request #{$pr->request_no}");
    expect(Notification::where('recipient', $requestor->id)->count())->toBe(0);
});

it('notifies the requester when an approver comments on their request', function () {
    $requestor = makeRequestorUser();
    $approver = makeApproverUserForNotifications();
    $pr = ProjectRequest::factory()->create(['requester_id' => $requestor->id]);

    $this->actingAs($approver)
        ->post(route('comments.store', $pr), ['content' => 'Please clarify scope.'])
        ->assertOk();

    $notification = Notification::where('recipient', $requestor->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->message)->toBe("New Comment was added to project request #{$pr->request_no}");
    expect(Notification::where('recipient', $approver->id)->count())->toBe(0);
});

it('marks a notification read and redirects to its link when opened', function () {
    $user = makeRequestorUser();
    $notification = Notification::create([
        'recipient' => $user->id,
        'message' => 'Test message',
        'link' => '/requests',
        'is_read' => false,
    ]);

    $this->actingAs($user)
        ->get(route('notifications.open', $notification->id))
        ->assertRedirect('/requests');

    expect($notification->fresh()->is_read)->toBeTrue();
});

it('redirects to the dashboard when a notification has no link', function () {
    $user = makeRequestorUser();
    $notification = Notification::create([
        'recipient' => $user->id,
        'message' => 'Test message',
        'link' => null,
        'is_read' => false,
    ]);

    $this->actingAs($user)
        ->get(route('notifications.open', $notification->id))
        ->assertRedirect(route('dashboard'));
});

it('forbids opening another user\'s notification', function () {
    $owner = makeRequestorUser();
    $intruder = makeRequestorUser();
    $notification = Notification::create([
        'recipient' => $owner->id,
        'message' => 'Test message',
        'link' => '/requests',
        'is_read' => false,
    ]);

    $this->actingAs($intruder)
        ->get(route('notifications.open', $notification->id))
        ->assertForbidden();

    expect($notification->fresh()->is_read)->toBeFalse();
});

it('marks all of the current user\'s notifications as read', function () {
    $user = makeRequestorUser();
    $other = makeRequestorUser();

    Notification::create(['recipient' => $user->id, 'message' => 'One', 'is_read' => false]);
    Notification::create(['recipient' => $user->id, 'message' => 'Two', 'is_read' => false]);
    Notification::create(['recipient' => $other->id, 'message' => 'Not mine', 'is_read' => false]);

    $this->actingAs($user)
        ->patch(route('notifications.read-all'))
        ->assertRedirect();

    expect(Notification::where('recipient', $user->id)->where('is_read', false)->count())->toBe(0);
    expect(Notification::where('recipient', $other->id)->where('is_read', false)->count())->toBe(1);
});

it('notifies the request owner when a project is created from their request', function () {
    $requestor = makeRequestorUser();
    $approver = makeApproverUserForNotifications();
    $manager = User::factory()->create();
    $pr = ProjectRequest::factory()->create(['requester_id' => $requestor->id, 'status' => 'approved']);
    seedProjectMasterDataForNotifications();

    $this->actingAs($approver)
        ->post(route('projects.store'), projectPayloadForNotifications($manager, ['project_request_id' => $pr->id]))
        ->assertRedirect();

    $project = Project::firstOrFail();
    $notification = Notification::where('recipient', $requestor->id)->first();

    expect($notification)->not->toBeNull();
    expect($notification->message)->toBe("A Project has been created from your Project Request #{$pr->request_no}");
    expect($notification->link)->toBe(route('projects.show', $project->id, absolute: false));
});

it('notifies the request owner when project details are edited', function () {
    $requestor = makeRequestorUser();
    $approver = makeApproverUserForNotifications();
    $manager = User::factory()->create();
    $pr = ProjectRequest::factory()->create(['requester_id' => $requestor->id, 'status' => 'approved']);
    seedProjectMasterDataForNotifications();

    $this->actingAs($approver)->post(route('projects.store'), projectPayloadForNotifications($manager, ['project_request_id' => $pr->id]));
    $project = Project::firstOrFail();
    Notification::query()->delete();

    $this->actingAs($approver)
        ->put(route('projects.update', $project), projectPayloadForNotifications($manager, ['title' => 'Updated Title']))
        ->assertRedirect();

    $notification = Notification::where('recipient', $requestor->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->message)->toBe('Project details updated');
});

it('notifies the request owner when a hub item is added', function () {
    $requestor = makeRequestorUser();
    $approver = makeApproverUserForNotifications();
    $manager = User::factory()->create();
    $pr = ProjectRequest::factory()->create(['requester_id' => $requestor->id, 'status' => 'approved']);
    seedProjectMasterDataForNotifications();

    $this->actingAs($approver)->post(route('projects.store'), projectPayloadForNotifications($manager, ['project_request_id' => $pr->id]));
    $project = Project::firstOrFail();
    Notification::query()->delete();

    $this->actingAs($approver)
        ->post(route('hub.todo.store', $project), ['task_name' => 'Inspect site', 'target_date' => '2026-08-01'])
        ->assertRedirect();

    $notification = Notification::where('recipient', $requestor->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->message)->toBe('Task added: Inspect site');
});

it('shares the current user\'s notifications and unread count on every inertia page', function () {
    $user = makeRequestorUser();
    Notification::create(['recipient' => $user->id, 'message' => 'Unread one', 'is_read' => false]);
    Notification::create(['recipient' => $user->id, 'message' => 'Already read', 'is_read' => true]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('unread_notifications_count', 1)
        ->has('notifications', 2)
    );
});

it('does not notify a user of their own project changes', function () {
    $admin = makeAdminUserForNotifications();
    $manager = User::factory()->create();
    $pr = ProjectRequest::factory()->create(['requester_id' => $admin->id, 'status' => 'approved']);
    seedProjectMasterDataForNotifications();

    $this->actingAs($admin)->post(route('projects.store'), projectPayloadForNotifications($manager, ['project_request_id' => $pr->id]));
    $project = Project::firstOrFail();
    Notification::query()->delete();

    $this->actingAs($admin)
        ->put(route('projects.update', $project), projectPayloadForNotifications($manager, ['title' => 'Self Update']))
        ->assertRedirect();

    expect(Notification::where('recipient', $admin->id)->count())->toBe(0);
});
