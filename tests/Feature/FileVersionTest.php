<?php

use App\Models\Attachment;
use App\Models\FileVersion;
use App\Models\Project;
use App\Models\ProjectQualityDoc;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeVersionEngineer(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function makeVersionProject(User $engineer): Project
{
    return Project::create([
        'project_no'           => 'PRJ-VER-' . uniqid(),
        'title'                => 'Versioning Project',
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
    ]);
}

// ── Project request attachments ──────────────────────────────────────────

it('records the first upload of an attachment as v1', function () {
    Storage::fake('public');

    $requester = User::factory()->create();

    $this->actingAs($requester)->post(route('requests.store'), [
        'title'         => 'Versioned Request',
        'job_type'      => 'civil',
        'description'   => 'Needs a drawing.',
        'job_location'  => 'Site A',
        'costcode'      => 'CC-001',
        'opex'          => false,
        'capex'         => false,
        'for_budgeting' => true,
        'attachments'   => [
            ['file' => UploadedFile::fake()->create('plan-a.pdf', 40, 'application/pdf'), 'type' => 'drawing', 'description' => 'Layout'],
        ],
    ])->assertRedirect();

    $attachment = Attachment::firstOrFail();
    $versions   = $attachment->versionsOf();

    expect($versions)->toHaveCount(1)
        ->and($versions->first()->version)->toBe(1)
        ->and($versions->first()->filename)->toBe('plan-a.pdf')
        ->and($versions->first()->filepath)->toBe($attachment->filepath);
});

it('bumps an attachment to v2 on replace and keeps the file it replaced', function () {
    Storage::fake('public');

    $requester   = User::factory()->create();
    $projectRequest = ProjectRequest::factory()->create(['requester_id' => $requester->id, 'status' => 'pending']);

    $attachment = Attachment::create([
        'filename'       => 'first.pdf',
        'filepath'       => UploadedFile::fake()->create('first.pdf', 20, 'application/pdf')->store('requests/1/drawings', 'public'),
        'type'           => 'drawing',
        'reference_id'   => $projectRequest->id,
        'reference_type' => ProjectRequest::class,
    ]);
    $attachment->recordFileVersion($attachment->filepath, 'first.pdf');

    $oldPath = $attachment->filepath;

    $this->actingAs($requester)
        ->post(route('requests.attachments.replace', [$projectRequest->id, $attachment->id]), [
            'file' => UploadedFile::fake()->create('second.pdf', 30, 'application/pdf'),
        ])->assertRedirect();

    $attachment->refresh();

    expect($attachment->filename)->toBe('second.pdf')
        ->and($attachment->versionsOf()->pluck('version')->all())->toBe([2, 1]);

    // The whole point of versioning: the superseded file is still there.
    Storage::disk('public')->assertExists($oldPath);
    Storage::disk('public')->assertExists($attachment->filepath);
    expect($attachment->filepath)->not->toBe($oldPath);
});

it('keeps numbering upwards across several replacements', function () {
    Storage::fake('public');

    $requester      = User::factory()->create();
    $projectRequest = ProjectRequest::factory()->create(['requester_id' => $requester->id, 'status' => 'pending']);

    $attachment = Attachment::create([
        'filename'       => 'v1.pdf',
        'filepath'       => UploadedFile::fake()->create('v1.pdf', 10, 'application/pdf')->store('requests/1/reports', 'public'),
        'type'           => 'report',
        'reference_id'   => $projectRequest->id,
        'reference_type' => ProjectRequest::class,
    ]);
    $attachment->recordFileVersion($attachment->filepath, 'v1.pdf');

    foreach (['v2.pdf', 'v3.pdf'] as $name) {
        $this->actingAs($requester)
            ->post(route('requests.attachments.replace', [$projectRequest->id, $attachment->id]), [
                'file' => UploadedFile::fake()->create($name, 10, 'application/pdf'),
            ])->assertRedirect();
    }

    expect($attachment->fresh()->versionsOf()->pluck('filename')->all())
        ->toBe(['v3.pdf', 'v2.pdf', 'v1.pdf'])
        ->and($attachment->fresh()->latestFileVersion()->label)->toBe('v3');
});

it('will not replace an attachment through a request that does not own it', function () {
    Storage::fake('public');

    $requester = User::factory()->create();
    $mine      = ProjectRequest::factory()->create(['requester_id' => $requester->id, 'status' => 'pending']);
    $theirs    = ProjectRequest::factory()->create(['requester_id' => $requester->id, 'status' => 'pending']);

    $attachment = Attachment::create([
        'filename'       => 'theirs.pdf',
        'filepath'       => 'requests/9/reports/theirs.pdf',
        'type'           => 'report',
        'reference_id'   => $theirs->id,
        'reference_type' => ProjectRequest::class,
    ]);

    $this->actingAs($requester)
        ->post(route('requests.attachments.replace', [$mine->id, $attachment->id]), [
            'file' => UploadedFile::fake()->create('sneaky.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
});

it('drops the whole history when an attachment is deleted', function () {
    Storage::fake('public');

    $requester      = User::factory()->create();
    $projectRequest = ProjectRequest::factory()->create(['requester_id' => $requester->id, 'status' => 'pending']);

    $attachment = Attachment::create([
        'filename'       => 'gone.pdf',
        'filepath'       => UploadedFile::fake()->create('gone.pdf', 10, 'application/pdf')->store('requests/1/reports', 'public'),
        'type'           => 'report',
        'reference_id'   => $projectRequest->id,
        'reference_type' => ProjectRequest::class,
    ]);
    $attachment->recordFileVersion($attachment->filepath, 'gone.pdf');

    $this->actingAs($requester)
        ->put(route('requests.update', $projectRequest->id), [
            'title'               => $projectRequest->title,
            'job_type'            => $projectRequest->job_type,
            'description'         => $projectRequest->description,
            'job_location'        => $projectRequest->job_location,
            'costcode'            => 'CC-001',
            'opex'                => false,
            'capex'               => false,
            'for_budgeting'       => true,
            'deleted_attachments' => [$attachment->id],
        ])->assertRedirect();

    expect(FileVersion::where('versionable_id', $attachment->id)
        ->where('versionable_type', Attachment::class)->count())->toBe(0);
});

// ── Project hub documents ────────────────────────────────────────────────

it('versions a quality document through the shared replace endpoint', function () {
    Storage::fake('public');

    $engineer = makeVersionEngineer();
    $project  = makeVersionProject($engineer);

    $this->actingAs($engineer)->post(route('hub.qpp.store', $project->id), [
        'label'    => 'Concrete ITP',
        'doc_type' => 'Inspection & Test Plan (ITP)',
        'file'     => UploadedFile::fake()->create('itp-a.pdf', 20, 'application/pdf'),
    ])->assertRedirect();

    $doc     = ProjectQualityDoc::firstOrFail();
    $oldPath = $doc->file_path;

    expect($doc->versionsOf())->toHaveCount(1);

    $this->actingAs($engineer)
        ->post(route('files.replace', [$project->id, 'qpp', $doc->id]), [
            'file' => UploadedFile::fake()->create('itp-b.pdf', 25, 'application/pdf'),
        ])->assertRedirect();

    $doc->refresh();

    expect($doc->filename)->toBe('itp-b.pdf')
        ->and($doc->latestFileVersion()->version)->toBe(2)
        ->and($doc->file_path)->toBe($doc->latestFileVersion()->filepath);

    Storage::disk('public')->assertExists($oldPath);
});

it('refuses a slot that is not on the whitelist', function () {
    $engineer = makeVersionEngineer();
    $project  = makeVersionProject($engineer);

    $this->actingAs($engineer)
        ->post(route('files.replace', [$project->id, 'users', 1]), [
            'file' => UploadedFile::fake()->create('nope.pdf', 10, 'application/pdf'),
        ])->assertNotFound();
});

it('will not replace a document belonging to another project', function () {
    Storage::fake('public');

    $engineer = makeVersionEngineer();
    $project  = makeVersionProject($engineer);
    $other    = makeVersionProject($engineer);

    $this->actingAs($engineer)->post(route('hub.qpp.store', $other->id), [
        'label'    => 'Other project ITP',
        'doc_type' => 'Method Statement',
        'file'     => UploadedFile::fake()->create('other.pdf', 20, 'application/pdf'),
    ])->assertRedirect();

    $doc = ProjectQualityDoc::firstOrFail();

    $this->actingAs($engineer)
        ->post(route('files.replace', [$project->id, 'qpp', $doc->id]), [
            'file' => UploadedFile::fake()->create('sneaky.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
});
