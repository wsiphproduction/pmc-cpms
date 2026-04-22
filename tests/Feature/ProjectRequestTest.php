<?php

use App\Models\User;
use App\Models\ProjectRequest;
use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────

function makeUser(): User
{
    return User::factory()->create();
}

function makeRequest(array $overrides = []): ProjectRequest
{
    return ProjectRequest::factory()->create($overrides);
}

// Valid base payload for store/update — costcode required (NOT NULL in DB)
function basePayload(array $overrides = []): array
{
    return array_merge([
        'title'         => 'Test Project Request',
        'job_type'      => 'civil',
        'description'   => 'Some description here.',
        'job_location'  => 'Site A',
        'costcode'      => 'CC-001',
        'opex'          => false,
        'capex'         => false,
        'for_budgeting' => false,
    ], $overrides);
}

// ─────────────────────────────────────────────
// INDEX
// ─────────────────────────────────────────────

describe('index', function () {

    it('redirects guests to login', function () {
        $this->get(route('requests.index'))
            ->assertRedirect(route('login'));
    });

    it('renders the index page for authenticated users', function () {
        $this->actingAs(makeUser())
            ->get(route('requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/index')
                ->has('requests')
                ->has('filters')
            );
    });

    it('returns paginated requests', function () {
        $user = makeUser();
        ProjectRequest::factory()->count(20)->create();

        $this->actingAs($user)
            ->get(route('requests.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/index')
                ->has('requests.data', 15)
            );
    });

    it('filters by search keyword', function () {
        $user = makeUser();
        makeRequest(['title' => 'XQZTEST_Electrical_XQZTEST']);
        makeRequest(['title' => 'XQZTEST_Plumbing_XQZTEST']);

        $this->actingAs($user)
            ->get(route('requests.index', ['search' => 'XQZTEST_Electrical_XQZTEST']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/index')
                ->has('requests.data', 1)
                ->where('requests.data.0.title', 'XQZTEST_Electrical_XQZTEST')
            );
    });

    it('filters by job_type', function () {
        $user = makeUser();
        makeRequest(['job_type' => 'civil']);
        makeRequest(['job_type' => 'electrical']);

        $this->actingAs($user)
            ->get(route('requests.index', ['job_type' => 'civil']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/index')
                ->has('requests.data', 1)
                ->where('requests.data.0.job_type', 'civil')
            );
    });

    it('filters by status array', function () {
        $user = makeUser();
        makeRequest(['status' => 'pending']);
        makeRequest(['status' => 'approved']);
        makeRequest(['status' => 'rejected']);

        $this->actingAs($user)
            ->get(route('requests.index', ['status' => ['pending', 'approved']]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/index')
                ->has('requests.data', 2)
            );
    });

    it('passes filters back to the view', function () {
        $this->actingAs(makeUser())
            ->get(route('requests.index', ['search' => 'test', 'job_type' => 'civil']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'test')
                ->where('filters.job_type', 'civil')
            );
    });

});

// ─────────────────────────────────────────────
// CREATE
// ─────────────────────────────────────────────

describe('create', function () {

    it('redirects guests to login', function () {
        $this->get(route('requests.create'))
            ->assertRedirect(route('login'));
    });

    it('renders the create page', function () {
        $this->actingAs(makeUser())
            ->get(route('requests.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/create')
            );
    });

});

// ─────────────────────────────────────────────
// STORE
// ─────────────────────────────────────────────

describe('store', function () {

    it('redirects guests to login', function () {
        $this->post(route('requests.store'), [])
            ->assertRedirect(route('login'));
    });

    it('stores a valid project request', function () {
        $user = makeUser();

        $this->actingAs($user)
            ->post(route('requests.store'), basePayload([
                'title'    => 'New Road Construction',
                'job_type' => 'civil',
                'capex'    => true,
            ]))
            ->assertRedirect(route('requests.index'));

        $this->assertDatabaseHas('project_requests', [
            'title'        => 'New Road Construction',
            'job_type'     => 'civil',
            'requester_id' => $user->id,
            'status'       => 'pending',
        ]);
    });

    it('sets status to pending on creation', function () {
        $user = makeUser();

        $this->actingAs($user)
            ->post(route('requests.store'), basePayload([
                'title' => 'StatusPendingTest',
            ]))
            ->assertRedirect(route('requests.index'));

        $this->assertDatabaseHas('project_requests', [
            'title'  => 'StatusPendingTest',
            'status' => 'pending',
        ]);
    });

    it('assigns the authenticated user as requester', function () {
        $user = makeUser();

        $this->actingAs($user)
            ->post(route('requests.store'), basePayload());

        $this->assertDatabaseHas('project_requests', [
            'title'        => 'Test Project Request',
            'requester_id' => $user->id,
        ]);
    });

    it('fails validation when required fields are missing', function () {
        $this->actingAs(makeUser())
            ->post(route('requests.store'), [])
            ->assertSessionHasErrors(['title', 'job_type', 'description', 'job_location']);
    });

    it('fails validation when title exceeds max length', function () {
        $this->actingAs(makeUser())
            ->post(route('requests.store'), basePayload([
                'title' => str_repeat('a', 256),
            ]))
            ->assertSessionHasErrors(['title']);
    });

    it('stores attachments with the request', function () {
        Storage::fake('public');
        $user = makeUser();
        $file = UploadedFile::fake()->create('drawing.pdf', 500, 'application/pdf');

        $this->actingAs($user)
            ->post(route('requests.store'), basePayload([
                'title'       => 'AttachmentTest',
                'attachments' => [
                    ['file' => $file, 'type' => 'drawing', 'description' => 'Site drawing'],
                ],
            ]))
            ->assertRedirect(route('requests.index'));

        $projectRequest = ProjectRequest::where('title', 'AttachmentTest')->first();

        expect($projectRequest)->not->toBeNull();

        $this->assertDatabaseHas('attachments', [
            'reference_id'   => $projectRequest->id,
            'reference_type' => ProjectRequest::class,
            'filename'       => 'drawing.pdf',
            // description is stored from nested input — assert filename only
        ]);
    });

    it('fails validation when attachment type is invalid', function () {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('file.pdf', 100);

        $this->actingAs(makeUser())
            ->post(route('requests.store'), basePayload([
                'attachments' => [
                    ['file' => $file, 'type' => 'invalid_type'],
                ],
            ]))
            ->assertSessionHasErrors(['attachments.0.type']);
    });

});

// ─────────────────────────────────────────────
// SHOW
// ─────────────────────────────────────────────

describe('show', function () {

    it('redirects guests to login', function () {
        $pr = makeRequest();
        $this->get(route('requests.show', $pr))
            ->assertRedirect(route('login'));
    });

    it('renders the show page with project request data', function () {
        $user = makeUser();
        $pr   = makeRequest(['requester_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('requests.show', $pr))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/show')
                ->has('projectRequest')
                ->where('projectRequest.id', $pr->id)
                ->where('projectRequest.title', $pr->title)
            );
    });

    it('loads requester and attachments relationships', function () {
        $user = makeUser();
        $pr   = makeRequest(['requester_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('requests.show', $pr))
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/show')
                ->has('projectRequest.requester')
                ->has('projectRequest.attachments')
            );
    });

});

// ─────────────────────────────────────────────
// EDIT
// ─────────────────────────────────────────────

describe('edit', function () {

    it('redirects guests to login', function () {
        $pr = makeRequest();
        $this->get(route('requests.edit', $pr))
            ->assertRedirect(route('login'));
    });

    it('renders the edit page', function () {
        $user = makeUser();
        $pr   = makeRequest(['requester_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('requests.edit', $pr))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('requests/edit')
                ->has('projectRequest')
                ->where('projectRequest.id', $pr->id)
            );
    });

});

// ─────────────────────────────────────────────
// UPDATE
// ─────────────────────────────────────────────

describe('update', function () {

    it('redirects guests to login', function () {
        $pr = makeRequest();
        $this->put(route('requests.update', $pr), [])
            ->assertRedirect(route('login'));
    });

    it('updates all fields from the edit form', function () {
        $user = makeUser();
        $pr   = makeRequest(['requester_id' => $user->id]);

        $this->actingAs($user)
            ->put(route('requests.update', $pr), basePayload([
                'title'         => 'Updated Title',
                'job_type'      => 'electrical',
                'description'   => 'Updated description.',
                'job_location'  => 'Site B',
                'costcode'      => 'CC-999',
                'opex'          => true,
                'for_budgeting' => true,
            ]))
            ->assertRedirect(route('requests.index'));

        $this->assertDatabaseHas('project_requests', [
            'id'           => $pr->id,
            'title'        => 'Updated Title',
            'job_type'     => 'electrical',
            'job_location' => 'Site B',
            'costcode'     => 'CC-999',
        ]);
    });

    it('can update status only (approve/reject patch)', function () {
        $user = makeUser();
        $pr   = makeRequest(['status' => 'pending', 'requester_id' => $user->id]);

        $this->actingAs($user)
            ->patch(route('requests.update', $pr), ['status' => 'approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('project_requests', [
            'id'     => $pr->id,
            'status' => 'approved',
        ]);
    });

    it('can reject a request via status patch', function () {
        $user = makeUser();
        $pr   = makeRequest(['status' => 'pending', 'requester_id' => $user->id]);

        $this->actingAs($user)
            ->patch(route('requests.update', $pr), ['status' => 'rejected'])
            ->assertRedirect();

        $this->assertDatabaseHas('project_requests', [
            'id'     => $pr->id,
            'status' => 'rejected',
        ]);
    });

    it('fails validation when status is invalid', function () {
        $user = makeUser();
        $pr   = makeRequest(['requester_id' => $user->id]);

        $this->actingAs($user)
            ->patch(route('requests.update', $pr), ['status' => 'invalid_status'])
            ->assertSessionHasErrors(['status']);
    });

    it('deletes specified attachments on update', function () {
        Storage::fake('public');
        $user     = makeUser();
        $pr       = makeRequest(['requester_id' => $user->id]);
        $filepath = "requests/{$pr->id}/drawings/file.pdf";

        $attachment = Attachment::factory()->create([
            'reference_id'   => $pr->id,
            'reference_type' => ProjectRequest::class,
            'filepath'       => $filepath,
        ]);

        Storage::disk('public')->put($filepath, 'dummy content');
        Storage::disk('public')->assertExists($filepath);

        $this->actingAs($user)
            ->put(route('requests.update', $pr), basePayload([
                'title'               => $pr->title,
                'job_type'            => $pr->job_type,
                'description'         => $pr->description,
                'job_location'        => $pr->job_location,
                'costcode'            => $pr->costcode,
                'deleted_attachments' => [$attachment->id],
            ]));

        // Attachment uses SoftDeletes
        $this->assertSoftDeleted('attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing($filepath);
    });

    it('fails full update validation when required fields are missing', function () {
        $user = makeUser();
        $pr   = makeRequest(['requester_id' => $user->id]);

        $this->actingAs($user)
            ->put(route('requests.update', $pr), ['title' => '', 'job_type' => ''])
            ->assertSessionHasErrors(['title', 'job_type', 'description', 'job_location']);
    });

});

// ─────────────────────────────────────────────
// DESTROY
// ─────────────────────────────────────────────

describe('destroy', function () {

    it('redirects guests to login', function () {
        $pr = makeRequest();
        $this->delete(route('requests.destroy', $pr))
            ->assertRedirect(route('login'));
    });

    it('soft deletes the project request', function () {
        $user = makeUser();
        $pr   = makeRequest(['requester_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('requests.destroy', $pr))
            ->assertRedirect(route('requests.index'));

        // ProjectRequest uses SoftDeletes
        $this->assertSoftDeleted('project_requests', ['id' => $pr->id]);
    });

    it('deletes physical attachment files on destroy', function () {
        Storage::fake('public');
        $user     = makeUser();
        $pr       = makeRequest(['requester_id' => $user->id]);
        $filepath = "requests/{$pr->id}/drawings/file.pdf";

        $attachment = Attachment::factory()->create([
            'reference_id'   => $pr->id,
            'reference_type' => ProjectRequest::class,
            'filepath'       => $filepath,
        ]);

        Storage::disk('public')->put($filepath, 'dummy content');
        Storage::disk('public')->assertExists($filepath);

        $this->actingAs($user)
            ->delete(route('requests.destroy', $pr))
            ->assertRedirect(route('requests.index'));

        // File should be physically deleted
        Storage::disk('public')->assertMissing($filepath);

        // Attachment record soft deleted
        $this->assertSoftDeleted('attachments', ['id' => $attachment->id]);
    });

});