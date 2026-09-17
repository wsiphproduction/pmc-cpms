<?php

use App\Models\User;
use App\Support\ManualContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** A user holding exactly one role, the way the system issues accounts. */
function manualUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('turns visitors away', function () {
    $this->get(route('manual.index'))->assertRedirect(route('login'));
});

it('offers every role a booklet of its own plus the complete edition', function () {
    foreach (array_keys(User::ROLE_LABELS) as $role) {
        if ($role === User::ROLE_ADMIN) {
            continue; // covered on its own below
        }

        $user = manualUser($role);

        $this->actingAs($user)
            ->get(route('manual.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('manuals/index')
                ->where('mine', ManualContent::slugForRole($role))
                ->has('manuals', 2)
                // Exactly one of the two is flagged as belonging to this role.
                ->where('manuals.0.is_mine', true)
                ->where('manuals.1.is_mine', false));
    }
});

it('shows the administrator every booklet, since they support everybody', function () {
    $this->actingAs(manualUser(User::ROLE_ADMIN))
        ->get(route('manual.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('manuals/index')
            ->where('mine', 'admin')
            ->has('manuals', count(ManualContent::manuals())));
});

it('publishes a booklet for every role the system issues', function () {
    $covered = collect(ManualContent::manuals())
        ->pluck('role')
        ->filter()
        ->all();

    expect($covered)->toEqualCanonicalizing(array_keys(User::ROLE_LABELS));
});

it('builds every manual from sections that actually exist', function () {
    $library = array_keys(ManualContent::sections());

    foreach (ManualContent::manuals() as $slug => $manual) {
        $unknown = array_diff($manual['sections'], $library);

        expect($unknown)->toBe([], "{$slug} lists sections that are not in the library: ".implode(', ', $unknown));
        expect(ManualContent::sectionsFor($slug))->toHaveCount(count($manual['sections']));
    }
});

it('leaves no section in the library unused', function () {
    $used = collect(ManualContent::manuals())->flatMap(fn ($m) => $m['sections'])->unique();
    $orphans = array_diff(array_keys(ManualContent::sections()), $used->all());

    expect($orphans)->toBe([], 'Unused manual sections: '.implode(', ', $orphans));
});

it('reports a manual as unavailable rather than linking to a file that is not there', function () {
    $missing = public_path('manuals/complete.pdf');
    $backup = null;

    // The PDFs are built artefacts and may or may not be present on the machine
    // running the suite, so the absent case is staged rather than assumed.
    if (is_file($missing)) {
        $backup = $missing.'.testbak';
        rename($missing, $backup);
    }

    try {
        $this->actingAs(manualUser(User::ROLE_PMD_DEPT_MANAGER))
            ->get(route('manual.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('manuals.1.slug', 'complete')
                ->where('manuals.1.available', false)
                ->where('manuals.1.url', null));
    } finally {
        if ($backup !== null) {
            rename($backup, $missing);
        }
    }
});
