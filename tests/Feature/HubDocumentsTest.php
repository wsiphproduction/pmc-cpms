<?php

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function docsUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function docsProject(User $engineer, array $attributes = []): Project
{
    return Project::create([
        'project_no'           => 'PRJ-DOC-' . uniqid(),
        'title'                => 'Documents Project',
        'project_manager_id'   => $engineer->id,
        'project_manager_name' => $engineer->name,
        'site'                 => 'Main Plant',
        'asset_id'             => 'A1',
        'class_name'           => 'Minor',
        'priority'             => '1',
        'status_key'           => 'ONGOING',
        'work_force'           => 'In-House',
        'wr_no'                => 'WR-1',
        'wr_date'              => now(),
        'dept_owner'           => 'Engineering',
        'cost_code'            => 'CC-001',
        'category'             => 'General',
        'service_type'         => 'Repair',
        'deadline'             => now()->addDays(30),
        'created_by'           => $engineer->id,
        ...$attributes,
    ]);
}

it('registers a hub page for every document section and the new module sections', function () {
    $engineer = docsUser(User::ROLE_ENGINEER);
    $project  = docsProject($engineer);

    foreach ([...array_keys(ProjectDocument::SECTIONS), 'proposal', 'acceptance', 'completion-summary'] as $section) {
        $this->actingAs($engineer)->get(route("projects.hub.{$section}", $project))->assertOk();
    }
});

it('files a document under its section and lists it only there', function () {
    Storage::fake('public');
    $engineer = docsUser(User::ROLE_ENGINEER);
    $project  = docsProject($engineer);

    $this->actingAs($engineer)->post(route('hub.docs.store', [$project, 'boq']), [
        'description' => 'BOQ rev. 2',
        'file'        => UploadedFile::fake()->create('boq.xlsx', 40),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $document = ProjectDocument::firstOrFail();
    expect($document->section)->toBe('boq');
    Storage::disk('public')->assertExists($document->file_path);

    $this->actingAs($engineer)
        ->get(route('projects.hub.boq', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hub_data.documents.0.description', 'BOQ rev. 2')
            ->where('hub_counts.boq', 1)
            ->where('hub_counts.sow', 0));

    $this->actingAs($engineer)
        ->get(route('projects.hub.sow', $project))
        ->assertInertia(fn (Assert $page) => $page->has('hub_data.documents', 0));

    $this->actingAs($engineer)->delete(route('hub.docs.destroy', [$project, $document]))->assertRedirect();
    expect(ProjectDocument::count())->toBe(0);
});

it('refuses a document filed under an unknown section', function () {
    Storage::fake('public');
    $engineer = docsUser(User::ROLE_ENGINEER);
    $project  = docsProject($engineer);

    $this->actingAs($engineer)->post(route('hub.docs.store', [$project, 'not-a-section']), [
        'description' => 'Nope',
        'file'        => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
    ])->assertNotFound();
});

it('needs a description for a document', function () {
    Storage::fake('public');
    $engineer = docsUser(User::ROLE_ENGINEER);
    $project  = docsProject($engineer);

    $this->actingAs($engineer)->post(route('hub.docs.store', [$project, 'sow']), [
        'file' => UploadedFile::fake()->create('sow.pdf', 40, 'application/pdf'),
    ])->assertSessionHasErrors('description');
});

it('does not delete a document through another project', function () {
    Storage::fake('public');
    $engineer = docsUser(User::ROLE_ENGINEER);
    $project  = docsProject($engineer);
    $other    = docsProject($engineer);

    $this->actingAs($engineer)->post(route('hub.docs.store', [$project, 'as-built']), [
        'description' => 'As-built set',
        'file'        => UploadedFile::fake()->create('asbuilt.pdf', 40, 'application/pdf'),
    ]);

    $this->actingAs($engineer)
        ->delete(route('hub.docs.destroy', [$other, ProjectDocument::firstOrFail()]))
        ->assertForbidden();
    expect(ProjectDocument::count())->toBe(1);
});

it('replaces a document file as a new version', function () {
    Storage::fake('public');
    $engineer = docsUser(User::ROLE_ENGINEER);
    $project  = docsProject($engineer);

    $this->actingAs($engineer)->post(route('hub.docs.store', [$project, 'drawings']), [
        'description' => 'Civil drawings',
        'file'        => UploadedFile::fake()->create('civil-v1.pdf', 40, 'application/pdf'),
    ]);
    $document = ProjectDocument::firstOrFail();

    $this->actingAs($engineer)->post(route('files.replace', [$project, 'doc', $document->id]), [
        'file' => UploadedFile::fake()->create('civil-v2.pdf', 40, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($document->fresh()->filename)->toBe('civil-v2.pdf')
        ->and($document->fileVersions()->count())->toBe(2);
});

it('shows a sub-project its parent proposal when it has none of its own', function () {
    Storage::fake('public');
    $engineer = docsUser(User::ROLE_ENGINEER);
    $parent   = docsProject($engineer, ['proposal_document' => 'proposals/parent.pdf']);
    $child    = docsProject($engineer, ['parent_id' => $parent->id]);

    $this->actingAs($engineer)
        ->get(route('projects.hub.proposal', $child))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hub_data.proposal.inherited_from.id', $parent->id)
            ->where('hub_data.proposal.filename', 'parent.pdf'));

    $this->actingAs($engineer)
        ->get(route('projects.hub.proposal', $parent))
        ->assertInertia(fn (Assert $page) => $page->where('hub_data.proposal.inherited_from', null));
});
