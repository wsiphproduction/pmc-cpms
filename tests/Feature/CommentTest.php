<?php

use App\Models\Comment;
use App\Models\ProjectRequest;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeUserWithRoleForComments(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets PMD roles other than the engineer comment on a request', function () {
    $manager = makeUserWithRoleForComments(User::ROLE_PMD_DEPT_MANAGER);
    $pr = ProjectRequest::factory()->create(['status' => 'pending']);

    $this->actingAs($manager)
        ->postJson(route('comments.store', $pr), ['content' => 'Waiting for the CAPEX form.'])
        ->assertOk()
        ->assertJson(['content' => 'Waiting for the CAPEX form.', 'can_delete' => true]);

    expect(Comment::where('reference_id', $pr->id)->count())->toBe(1);
    // Only a Project Engineer's comment puts the request on hold.
    expect($pr->fresh()->status)->toBe('pending');
});

it('puts a pending request on hold when the engineer comments', function () {
    $engineer = makeUserWithRoleForComments(User::ROLE_ENGINEER);
    $pr = ProjectRequest::factory()->create(['status' => 'pending']);

    $this->actingAs($engineer)
        ->postJson(route('comments.store', $pr), ['content' => 'Please attach photos.'])
        ->assertOk();

    expect($pr->fresh())
        ->status->toBe('hold')
        ->status_before_hold->toBe('pending');
});

it('leaves a fully approved request alone when the engineer comments', function (string $status) {
    $engineer = makeUserWithRoleForComments(User::ROLE_ENGINEER);
    $pr = ProjectRequest::factory()->create(['status' => $status]);

    $this->actingAs($engineer)
        ->postJson(route('comments.store', $pr), ['content' => 'Noted for the site visit.'])
        ->assertOk();

    expect($pr->fresh())
        ->status->toBe($status)
        ->status_before_hold->toBeNull();
})->with(['approved', 'ongoing']);

it('lets the requester comment on their own request', function () {
    $requestor = makeUserWithRoleForComments(User::ROLE_REQUESTOR);
    $pr = ProjectRequest::factory()->create(['requester_id' => $requestor->id]);

    $this->actingAs($requestor)
        ->postJson(route('comments.store', $pr), ['content' => 'Any update?'])
        ->assertOk();
});

it('stops a department user commenting on or reading someone else\'s request', function () {
    $requestor = makeUserWithRoleForComments(User::ROLE_REQUESTOR);
    $pr = ProjectRequest::factory()->create();

    $this->actingAs($requestor)
        ->postJson(route('comments.store', $pr), ['content' => 'Hello'])
        ->assertForbidden();

    $this->actingAs($requestor)
        ->getJson(route('comments.index', $pr))
        ->assertForbidden();

    expect(Comment::count())->toBe(0);
});

it('only offers delete on comments the viewer may remove', function () {
    $manager = makeUserWithRoleForComments(User::ROLE_PMD_DEPT_MANAGER);
    $other = makeUserWithRoleForComments(User::ROLE_PMD_ASST_MANAGER);
    $pr = ProjectRequest::factory()->create();

    $this->actingAs($other)->postJson(route('comments.store', $pr), ['content' => 'Mine']);

    $this->actingAs($manager)
        ->getJson(route('comments.index', $pr))
        ->assertOk()
        ->assertJsonPath('0.can_delete', false);
});
