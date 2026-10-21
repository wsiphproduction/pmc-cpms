<?php

use App\Models\Project;
use App\Models\ProjectMeetingMinute;
use App\Models\ProjectPhoto;
use App\Models\ProjectPhotoAlbum;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function momUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function () {
    Storage::fake('public');

    $this->engineer = momUser(User::ROLE_ENGINEER);

    $this->project = Project::create([
        'project_no' => 'PRJ-MOM-' . uniqid(), 'title' => 'Minutes Project', 'site' => 'Mine',
        'asset_id' => 'A1', 'class_name' => 'Minor', 'priority' => '1', 'status_key' => 'PLANNING',
        'work_force' => 'In-House', 'wr_no' => 'WR-1', 'wr_date' => now(), 'dept_owner' => 'MET',
        'cost_code' => 'CC-001', 'category' => 'General', 'service_type' => 'Repair',
        'deadline' => now()->addDays(30), 'created_by' => $this->engineer->id,
        'project_manager_id' => $this->engineer->id,
    ]);
});

// ── Minutes of the Meeting ────────────────────────────────────────────────────

it('files minutes under their meeting date and lists them in the hub', function () {
    $this->actingAs($this->engineer)
        ->post(route('hub.mom.store', $this->project), [
            'meeting_date' => '2026-10-20',
            'file'         => UploadedFile::fake()->create('kickoff-minutes.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertRedirect()->assertSessionHasNoErrors();

    $minutes = ProjectMeetingMinute::sole();
    expect($minutes->meeting_date->toDateString())->toBe('2026-10-20')
        ->and($minutes->filename)->toBe('kickoff-minutes.docx')
        ->and($minutes->versionsOf())->toHaveCount(1);
    Storage::disk('public')->assertExists($minutes->file_path);

    $this->actingAs($this->engineer)
        ->get(route('projects.hub.mom', $this->project))
        ->assertInertia(fn ($page) => $page
            ->where('active_section', 'mom')
            ->has('hub_data.minutes', 1)
            ->where('hub_data.minutes.0.meeting_date', 'Oct 20, 2026')
            ->where('hub_data.minutes.0.filename', 'kickoff-minutes.docx')
            ->where('hub_counts.mom', 1));
});

it('needs a meeting date and a document file', function () {
    $this->actingAs($this->engineer)
        ->post(route('hub.mom.store', $this->project), [
            'file' => UploadedFile::fake()->create('minutes.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('meeting_date');

    $this->actingAs($this->engineer)
        ->post(route('hub.mom.store', $this->project), [
            'meeting_date' => '2026-10-20',
            'file'         => UploadedFile::fake()->create('page.html', 1, 'text/html'),
        ])->assertSessionHasErrors('file');

    expect(ProjectMeetingMinute::count())->toBe(0);
});

it('replaces a minutes file and keeps the earlier version', function () {
    $this->actingAs($this->engineer)->post(route('hub.mom.store', $this->project), [
        'meeting_date' => '2026-10-20',
        'file'         => UploadedFile::fake()->create('draft.pdf', 10, 'application/pdf'),
    ]);
    $minutes = ProjectMeetingMinute::sole();

    $this->actingAs($this->engineer)
        ->post(route('files.replace', [$this->project->id, 'mom', $minutes->id]), [
            'file' => UploadedFile::fake()->create('final.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

    expect($minutes->refresh()->filename)->toBe('final.pdf')
        ->and($minutes->versionsOf())->toHaveCount(2);
});

it('deletes minutes and their file', function () {
    $this->actingAs($this->engineer)->post(route('hub.mom.store', $this->project), [
        'meeting_date' => '2026-10-20',
        'file'         => UploadedFile::fake()->create('minutes.pdf', 10, 'application/pdf'),
    ]);
    $minutes = ProjectMeetingMinute::sole();

    $this->actingAs($this->engineer)
        ->delete(route('hub.mom.destroy', [$this->project, $minutes]))
        ->assertRedirect();

    expect(ProjectMeetingMinute::count())->toBe(0);
    Storage::disk('public')->assertMissing($minutes->file_path);
});

// ── Photos ────────────────────────────────────────────────────────────────────

it('groups uploaded photos into one album per date', function () {
    $this->actingAs($this->engineer)
        ->post(route('hub.photos.store', $this->project), [
            'photo_date' => '2026-10-20',
            'photos'     => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();

    // A second upload on the same date joins the first; the page sends big
    // selections this way, in batches, asking for JSON.
    $this->actingAs($this->engineer)
        ->postJson(route('hub.photos.store', $this->project), [
            'photo_date' => '2026-10-20',
            'photos'     => [UploadedFile::fake()->image('c.png')],
        ])->assertOk()->assertJson(['uploaded' => 1]);

    $this->actingAs($this->engineer)->post(route('hub.photos.store', $this->project), [
        'photo_date' => '2026-10-21',
        'photos'     => [UploadedFile::fake()->image('d.jpg')],
    ]);

    expect(ProjectPhotoAlbum::count())->toBe(2)
        ->and(ProjectPhotoAlbum::whereDate('photo_date', '2026-10-20')->sole()->photos)->toHaveCount(3);

    $this->actingAs($this->engineer)
        ->get(route('projects.hub.photos', $this->project))
        ->assertInertia(fn ($page) => $page
            ->has('hub_data.albums', 2)
            // Newest date first.
            ->where('hub_data.albums.0.photo_date', 'Oct 21, 2026')
            ->has('hub_data.albums.1.photos', 3)
            ->where('hub_data.albums.1.photos.0.filename', 'a.jpg')
            ->where('hub_counts.photos', 2));
});

it('only accepts images as photos', function () {
    $this->actingAs($this->engineer)
        ->postJson(route('hub.photos.store', $this->project), [
            'photo_date' => '2026-10-20',
            'photos'     => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')],
        ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');

    $this->actingAs($this->engineer)
        ->postJson(route('hub.photos.store', $this->project), [
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertJsonValidationErrors('photo_date');

    expect(ProjectPhotoAlbum::count())->toBe(0);
});

it('deletes a single photo, and the album with its last photo', function () {
    $this->actingAs($this->engineer)->post(route('hub.photos.store', $this->project), [
        'photo_date' => '2026-10-20',
        'photos'     => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ]);
    $album = ProjectPhotoAlbum::sole();
    [$first, $second] = $album->photos->all();

    $this->actingAs($this->engineer)
        ->delete(route('hub.photos.destroy-photo', [$this->project, $album, $first]))
        ->assertRedirect();

    Storage::disk('public')->assertMissing($first->file_path);
    expect(ProjectPhotoAlbum::count())->toBe(1)->and(ProjectPhoto::count())->toBe(1);

    $this->actingAs($this->engineer)
        ->delete(route('hub.photos.destroy-photo', [$this->project, $album, $second]))
        ->assertRedirect();

    expect(ProjectPhotoAlbum::count())->toBe(0)->and(ProjectPhoto::count())->toBe(0);
});

it('deletes a whole date of photos with their files', function () {
    $this->actingAs($this->engineer)->post(route('hub.photos.store', $this->project), [
        'photo_date' => '2026-10-20',
        'photos'     => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ]);
    $album = ProjectPhotoAlbum::sole();
    $paths = $album->photos->pluck('file_path')->all();

    $this->actingAs($this->engineer)
        ->delete(route('hub.photos.destroy', [$this->project, $album]))
        ->assertRedirect();

    expect(ProjectPhotoAlbum::count())->toBe(0)->and(ProjectPhoto::count())->toBe(0);
    foreach ($paths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

it('will not delete photos through another project', function () {
    $this->actingAs($this->engineer)->post(route('hub.photos.store', $this->project), [
        'photo_date' => '2026-10-20',
        'photos'     => [UploadedFile::fake()->image('a.jpg')],
    ]);
    $album = ProjectPhotoAlbum::sole();

    $other = $this->project->replicate(['project_no']);
    $other->project_no = 'PRJ-OTHER-' . uniqid();
    $other->save();

    $this->actingAs($this->engineer)
        ->delete(route('hub.photos.destroy', [$other, $album]))
        ->assertForbidden();

    expect(ProjectPhotoAlbum::count())->toBe(1);
});

it('keeps uploads to people who can edit the project', function () {
    $outsider = momUser(User::ROLE_ENGINEER);

    $this->actingAs($outsider)->post(route('hub.mom.store', $this->project), [
        'meeting_date' => '2026-10-20',
        'file'         => UploadedFile::fake()->create('minutes.pdf', 10, 'application/pdf'),
    ])->assertForbidden();

    $this->actingAs($outsider)->post(route('hub.photos.store', $this->project), [
        'photo_date' => '2026-10-20',
        'photos'     => [UploadedFile::fake()->image('a.jpg')],
    ])->assertForbidden();

    expect(ProjectMeetingMinute::count())->toBe(0)->and(ProjectPhotoAlbum::count())->toBe(0);
});
