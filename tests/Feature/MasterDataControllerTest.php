<?php

use App\Models\Category;
use App\Models\CostCode;
use App\Models\Department;
use App\Models\JobLocation;
use App\Models\JobType;
use App\Models\MasterClass;
use App\Models\MasterStatus;
use App\Models\Priority;
use App\Models\ServiceType;
use App\Models\Site;
use App\Models\Structure;
use App\Models\User;
use App\Models\WorkForce;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// -------------------------------------------------
// Helpers
// -------------------------------------------------

function makeMasterDataUser(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function masterDataPayload(array $overrides = []): array
{
    return array_merge([
        'name'        => 'Test Master Data',
        'description' => 'A test master data record.',
    ], $overrides);
}

// -------------------------------------------------
// INDEX
// -------------------------------------------------

describe('index', function () {

    it('redirects guests to login', function () {
        $this->get(route('master.index'))
            ->assertRedirect(route('login'));
    });

    it('renders the master data page for authenticated users', function () {
        JobType::create(['name' => 'Civil', 'description' => 'Civil works']);
        JobLocation::create(['name' => 'Site A', 'description' => 'Main site']);
        CostCode::create(['name' => 'CC-001', 'description' => 'Budget code']);
        Site::create(['name' => 'Plant 1']);
        MasterClass::create(['name' => 'Class A']);
        Priority::create(['name' => 'High']);
        MasterStatus::create(['name' => 'Open']);
        Department::create(['name' => 'Maintenance']);
        Category::create(['name' => 'Repair']);
        ServiceType::create(['name' => 'Preventive']);
        WorkForce::create(['name' => 'Internal']);
        Structure::create(['name' => 'Building']);

        $this->actingAs(makeMasterDataUser())
            ->get(route('master.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/index')
                ->has('jobTypes', 1)
                ->has('jobLocations', 1)
                ->has('costCodes', 1)
                ->has('sites', 1)
                ->has('classes', 1)
                ->has('priorities', 1)
                ->has('statuses', 1)
                ->has('departments', 1)
                ->has('categories', 1)
                ->has('serviceTypes', 1)
                ->has('workForces', 1)
                ->has('structures', 1)
                ->where('jobTypes.0.name', 'Civil')
                ->where('jobLocations.0.name', 'Site A')
                ->where('costCodes.0.name', 'CC-001')
                ->where('sites.0.name', 'Plant 1')
                ->where('classes.0.name', 'Class A')
                ->where('priorities.0.name', 'High')
                ->where('statuses.0.name', 'Open')
                ->where('departments.0.name', 'Maintenance')
                ->where('categories.0.name', 'Repair')
                ->where('serviceTypes.0.name', 'Preventive')
                ->where('workForces.0.name', 'Internal')
                ->where('structures.0.name', 'Building')
            );
    });

    it('orders master data records by name', function () {
        JobType::create(['name' => 'Plumbing']);
        JobType::create(['name' => 'Civil']);
        JobLocation::create(['name' => 'Site B']);
        JobLocation::create(['name' => 'Site A']);
        CostCode::create(['name' => 'CC-200']);
        CostCode::create(['name' => 'CC-100']);

        $this->actingAs(makeMasterDataUser())
            ->get(route('master.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('jobTypes.0.name', 'Civil')
                ->where('jobTypes.1.name', 'Plumbing')
                ->where('jobLocations.0.name', 'Site A')
                ->where('jobLocations.1.name', 'Site B')
                ->where('costCodes.0.name', 'CC-100')
                ->where('costCodes.1.name', 'CC-200')
            );
    });

});

// -------------------------------------------------
// JOB TYPES
// -------------------------------------------------

describe('job types', function () {

    it('redirects guests to login when storing', function () {
        $this->post(route('master.job-types.store'), masterDataPayload())
            ->assertRedirect(route('login'));
    });

    it('stores a job type', function () {
        $this->actingAs(makeMasterDataUser())
            ->post(route('master.job-types.store'), masterDataPayload([
                'name'        => 'Electrical',
                'description' => 'Electrical works',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Job type added.');

        $this->assertDatabaseHas('job_types', [
            'name'        => 'Electrical',
            'description' => 'Electrical works',
        ]);
    });

    it('fails validation when job type name is missing', function () {
        $this->actingAs(makeMasterDataUser())
            ->post(route('master.job-types.store'), masterDataPayload(['name' => '']))
            ->assertSessionHasErrors(['name']);
    });

    it('fails validation when job type name is duplicate', function () {
        JobType::create(['name' => 'Civil']);

        $this->actingAs(makeMasterDataUser())
            ->post(route('master.job-types.store'), masterDataPayload(['name' => 'Civil']))
            ->assertSessionHasErrors(['name']);
    });

    it('updates a job type', function () {
        $jobType = JobType::create(['name' => 'Civil', 'description' => 'Old']);

        $this->actingAs(makeMasterDataUser())
            ->put(route('master.job-types.update', $jobType), masterDataPayload([
                'name'        => 'Civil Works',
                'description' => 'Updated',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Job type updated.');

        $this->assertDatabaseHas('job_types', [
            'id'          => $jobType->id,
            'name'        => 'Civil Works',
            'description' => 'Updated',
        ]);
    });

    it('allows updating a job type without changing its own name', function () {
        $jobType = JobType::create(['name' => 'Civil', 'description' => 'Old']);

        $this->actingAs(makeMasterDataUser())
            ->put(route('master.job-types.update', $jobType), masterDataPayload([
                'name'        => 'Civil',
                'description' => 'Still valid',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_types', [
            'id'          => $jobType->id,
            'name'        => 'Civil',
            'description' => 'Still valid',
        ]);
    });

    it('fails validation when updating a job type to another existing name', function () {
        JobType::create(['name' => 'Civil']);
        $jobType = JobType::create(['name' => 'Electrical']);

        $this->actingAs(makeMasterDataUser())
            ->put(route('master.job-types.update', $jobType), masterDataPayload(['name' => 'Civil']))
            ->assertSessionHasErrors(['name']);
    });

    it('deletes a job type', function () {
        $jobType = JobType::create(['name' => 'Civil']);

        $this->actingAs(makeMasterDataUser())
            ->delete(route('master.job-types.destroy', $jobType))
            ->assertRedirect()
            ->assertSessionHas('success', 'Job type deleted.');

        $this->assertDatabaseMissing('job_types', ['id' => $jobType->id]);
    });

});

// -------------------------------------------------
// JOB LOCATIONS
// -------------------------------------------------

describe('job locations', function () {

    it('redirects guests to login when storing', function () {
        $this->post(route('master.job-locations.store'), masterDataPayload())
            ->assertRedirect(route('login'));
    });

    it('stores a job location', function () {
        $this->actingAs(makeMasterDataUser())
            ->post(route('master.job-locations.store'), masterDataPayload([
                'name'        => 'Site A',
                'description' => 'Main project site',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Job location added.');

        $this->assertDatabaseHas('job_locations', [
            'name'        => 'Site A',
            'description' => 'Main project site',
        ]);
    });

    it('fails validation when job location name is missing', function () {
        $this->actingAs(makeMasterDataUser())
            ->post(route('master.job-locations.store'), masterDataPayload(['name' => '']))
            ->assertSessionHasErrors(['name']);
    });

    it('fails validation when job location name is duplicate', function () {
        JobLocation::create(['name' => 'Site A']);

        $this->actingAs(makeMasterDataUser())
            ->post(route('master.job-locations.store'), masterDataPayload(['name' => 'Site A']))
            ->assertSessionHasErrors(['name']);
    });

    it('updates a job location', function () {
        $jobLocation = JobLocation::create(['name' => 'Site A', 'description' => 'Old']);

        $this->actingAs(makeMasterDataUser())
            ->put(route('master.job-locations.update', $jobLocation), masterDataPayload([
                'name'        => 'Site B',
                'description' => 'Updated',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Job location updated.');

        $this->assertDatabaseHas('job_locations', [
            'id'          => $jobLocation->id,
            'name'        => 'Site B',
            'description' => 'Updated',
        ]);
    });

    it('allows updating a job location without changing its own name', function () {
        $jobLocation = JobLocation::create(['name' => 'Site A', 'description' => 'Old']);

        $this->actingAs(makeMasterDataUser())
            ->put(route('master.job-locations.update', $jobLocation), masterDataPayload([
                'name'        => 'Site A',
                'description' => 'Still valid',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_locations', [
            'id'          => $jobLocation->id,
            'name'        => 'Site A',
            'description' => 'Still valid',
        ]);
    });

    it('fails validation when updating a job location to another existing name', function () {
        JobLocation::create(['name' => 'Site A']);
        $jobLocation = JobLocation::create(['name' => 'Site B']);

        $this->actingAs(makeMasterDataUser())
            ->put(route('master.job-locations.update', $jobLocation), masterDataPayload(['name' => 'Site A']))
            ->assertSessionHasErrors(['name']);
    });

    it('deletes a job location', function () {
        $jobLocation = JobLocation::create(['name' => 'Site A']);

        $this->actingAs(makeMasterDataUser())
            ->delete(route('master.job-locations.destroy', $jobLocation))
            ->assertRedirect()
            ->assertSessionHas('success', 'Job location deleted.');

        $this->assertDatabaseMissing('job_locations', ['id' => $jobLocation->id]);
    });

});

// -------------------------------------------------
// COST CODES
// -------------------------------------------------

describe('cost codes', function () {

    it('redirects guests to login when storing', function () {
        $this->post(route('master.cost-codes.store'), masterDataPayload())
            ->assertRedirect(route('login'));
    });

    it('stores a cost code', function () {
        $this->actingAs(makeMasterDataUser())
            ->post(route('master.cost-codes.store'), masterDataPayload([
                'name'        => 'CC-001',
                'description' => 'Budget code',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Cost code added.');

        $this->assertDatabaseHas('cost_codes', [
            'name'        => 'CC-001',
            'description' => 'Budget code',
        ]);
    });

    it('fails validation when cost code name is missing', function () {
        $this->actingAs(makeMasterDataUser())
            ->post(route('master.cost-codes.store'), masterDataPayload(['name' => '']))
            ->assertSessionHasErrors(['name']);
    });

    it('fails validation when cost code name is duplicate', function () {
        CostCode::create(['name' => 'CC-001']);

        $this->actingAs(makeMasterDataUser())
            ->post(route('master.cost-codes.store'), masterDataPayload(['name' => 'CC-001']))
            ->assertSessionHasErrors(['name']);
    });

    it('updates a cost code', function () {
        $costCode = CostCode::create(['name' => 'CC-001', 'description' => 'Old']);

        $this->actingAs(makeMasterDataUser())
            ->put(route('master.cost-codes.update', $costCode), masterDataPayload([
                'name'        => 'CC-002',
                'description' => 'Updated',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Cost code updated.');

        $this->assertDatabaseHas('cost_codes', [
            'id'          => $costCode->id,
            'name'        => 'CC-002',
            'description' => 'Updated',
        ]);
    });

    it('allows updating a cost code without changing its own name', function () {
        $costCode = CostCode::create(['name' => 'CC-001', 'description' => 'Old']);

        $this->actingAs(makeMasterDataUser())
            ->put(route('master.cost-codes.update', $costCode), masterDataPayload([
                'name'        => 'CC-001',
                'description' => 'Still valid',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cost_codes', [
            'id'          => $costCode->id,
            'name'        => 'CC-001',
            'description' => 'Still valid',
        ]);
    });

    it('fails validation when updating a cost code to another existing name', function () {
        CostCode::create(['name' => 'CC-001']);
        $costCode = CostCode::create(['name' => 'CC-002']);

        $this->actingAs(makeMasterDataUser())
            ->put(route('master.cost-codes.update', $costCode), masterDataPayload(['name' => 'CC-001']))
            ->assertSessionHasErrors(['name']);
    });

    it('deletes a cost code', function () {
        $costCode = CostCode::create(['name' => 'CC-001']);

        $this->actingAs(makeMasterDataUser())
            ->delete(route('master.cost-codes.destroy', $costCode))
            ->assertRedirect()
            ->assertSessionHas('success', 'Cost code deleted.');

        $this->assertDatabaseMissing('cost_codes', ['id' => $costCode->id]);
    });

});

// -------------------------------------------------
// REMAINING MASTER DATA TABS
// -------------------------------------------------

describe('remaining master data tabs', function () {

    it('stores, updates, and deletes each remaining master data type', function (
        string $routeBase,
        string $modelClass,
        string $table,
        string $addedMessage,
        string $updatedMessage,
        string $deletedMessage
    ) {
        $user = makeMasterDataUser();

        $this->actingAs($user)
            ->post(route("{$routeBase}.store"), masterDataPayload([
                'name'        => 'Original Entry',
                'description' => 'Original description',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', $addedMessage);

        $this->assertDatabaseHas($table, [
            'name'        => 'Original Entry',
            'description' => 'Original description',
        ]);

        $item = $modelClass::where('name', 'Original Entry')->firstOrFail();

        $this->actingAs($user)
            ->put(route("{$routeBase}.update", $item), masterDataPayload([
                'name'        => 'Updated Entry',
                'description' => 'Updated description',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', $updatedMessage);

        $this->assertDatabaseHas($table, [
            'id'          => $item->id,
            'name'        => 'Updated Entry',
            'description' => 'Updated description',
        ]);

        $item->refresh();

        $this->actingAs($user)
            ->delete(route("{$routeBase}.destroy", $item))
            ->assertRedirect()
            ->assertSessionHas('success', $deletedMessage);

        $this->assertDatabaseMissing($table, ['id' => $item->id]);
    })->with([
        'sites'         => ['master.sites', Site::class, 'sites', 'Site added.', 'Site updated.', 'Site deleted.'],
        'classes'       => ['master.classes', MasterClass::class, 'classes', 'Class added.', 'Class updated.', 'Class deleted.'],
        'priorities'    => ['master.priorities', Priority::class, 'priorities', 'Priority added.', 'Priority updated.', 'Priority deleted.'],
        'statuses'      => ['master.statuses', MasterStatus::class, 'statuses', 'Status added.', 'Status updated.', 'Status deleted.'],
        'departments'   => ['master.departments', Department::class, 'departments', 'Department added.', 'Department updated.', 'Department deleted.'],
        'categories'    => ['master.categories', Category::class, 'categories', 'Category added.', 'Category updated.', 'Category deleted.'],
        'service types' => ['master.service-types', ServiceType::class, 'service_types', 'Service type added.', 'Service type updated.', 'Service type deleted.'],
        'work forces'   => ['master.work-forces', WorkForce::class, 'work_forces', 'Work force added.', 'Work force updated.', 'Work force deleted.'],
        'structures'    => ['master.structures', Structure::class, 'structures', 'Structure added.', 'Structure updated.', 'Structure deleted.'],
    ]);

    it('validates duplicate names for remaining master data types', function (
        string $routeBase,
        string $modelClass
    ) {
        $modelClass::create(['name' => 'Duplicate Entry']);

        $this->actingAs(makeMasterDataUser())
            ->post(route("{$routeBase}.store"), masterDataPayload(['name' => 'Duplicate Entry']))
            ->assertSessionHasErrors(['name']);
    })->with([
        'sites'         => ['master.sites', Site::class],
        'classes'       => ['master.classes', MasterClass::class],
        'priorities'    => ['master.priorities', Priority::class],
        'statuses'      => ['master.statuses', MasterStatus::class],
        'departments'   => ['master.departments', Department::class],
        'categories'    => ['master.categories', Category::class],
        'service types' => ['master.service-types', ServiceType::class],
        'work forces'   => ['master.work-forces', WorkForce::class],
        'structures'    => ['master.structures', Structure::class],
    ]);

});

// -------------------------------------------------
// SUPPLIERS — PMD's own list vs. bulk-imported rows
// -------------------------------------------------

describe('suppliers', function () {

    it('lists only the suppliers PMD added, and says how many imports are hidden', function () {
        \App\Models\Supplier::create(['company' => 'PMD Added Co', 'source' => \App\Models\Supplier::SOURCE_PMD]);
        \App\Models\Supplier::create(['company' => 'Bulk Imported Co', 'source' => \App\Models\Supplier::SOURCE_IMPORT]);

        $this->actingAs(makeMasterDataUser())
            ->get(route('master.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('suppliers', 1)
                ->where('suppliers.0.company', 'PMD Added Co')
                ->where('importedSupplierCount', 1));
    });

    it('records who added a supplier and keeps it in PMD\'s list', function () {
        $user = makeMasterDataUser();

        $this->actingAs($user)->post(route('master.suppliers.store'), [
            'company'    => 'New Supplier Inc',
            'accredited' => true,
        ])->assertRedirect();

        $supplier = \App\Models\Supplier::first();

        expect($supplier->source)->toBe(\App\Models\Supplier::SOURCE_PMD);
        expect((int) $supplier->created_by)->toBe($user->id);

        $this->actingAs($user)->get(route('master.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('suppliers', 1)
                ->where('suppliers.0.added_by', $user->name));
    });

    it('tags CSV-imported suppliers as imports, leaving them out of the list', function () {
        $user = makeMasterDataUser();

        $path = tempnam(sys_get_temp_dir(), 'sup') . '.csv';
        file_put_contents($path, "company,email\nImported One,a@example.com\nImported Two,b@example.com\n");

        $this->actingAs($user)->post(route('master.suppliers.import'), [
            'file' => new \Illuminate\Http\UploadedFile($path, 'suppliers.csv', 'text/csv', null, true),
        ])->assertRedirect()->assertSessionHas('success');

        expect(\App\Models\Supplier::count())->toBe(2);
        expect(\App\Models\Supplier::pmd()->count())->toBe(0);

        $this->actingAs($user)->get(route('master.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('suppliers', 0)
                ->where('importedSupplierCount', 2));
    });

    it('promotes a hidden supplier onto the list instead of rejecting the name', function () {
        $user = makeMasterDataUser();

        $hidden = \App\Models\Supplier::create([
            'company'      => 'Dormant Trading Co',
            'source'       => \App\Models\Supplier::SOURCE_IMPORT,
            'email'        => 'sales@dormant.test',
            'telephone_no' => '02-1234',
        ]);

        $this->actingAs($user)->post(route('master.suppliers.store'), [
            'company'    => 'Dormant Trading Co',
            'accredited' => true,
            'mobile_no'  => '0917-000-1111',
        ])->assertRedirect()->assertSessionHas('success');

        // Promoted, not duplicated, and the contact details already on file
        // survive the blank email field on the add form.
        expect(\App\Models\Supplier::count())->toBe(1);

        $hidden->refresh();
        expect($hidden->source)->toBe(\App\Models\Supplier::SOURCE_PMD);
        expect((int) $hidden->created_by)->toBe($user->id);
        expect($hidden->email)->toBe('sales@dormant.test');
        expect($hidden->telephone_no)->toBe('02-1234');
        expect($hidden->mobile_no)->toBe('0917-000-1111');

        $this->actingAs($user)->get(route('master.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('suppliers', 1)
                ->where('suppliers.0.company', 'Dormant Trading Co')
                ->where('importedSupplierCount', 0));
    });

    it('rejects a supplier already on the list', function () {
        \App\Models\Supplier::create(['company' => 'Listed Co', 'source' => \App\Models\Supplier::SOURCE_PMD]);

        $this->actingAs(makeMasterDataUser())->post(route('master.suppliers.store'), [
            'company' => 'Listed Co',
        ])->assertSessionHasErrors('company');

        expect(\App\Models\Supplier::count())->toBe(1);
    });

    it('finds dormant and listed suppliers by name for the add-form type-ahead', function () {
        \App\Models\Supplier::create(['company' => 'Acme Dormant Supply', 'source' => \App\Models\Supplier::SOURCE_IMPORT, 'email' => 'hi@acme.test']);
        \App\Models\Supplier::create(['company' => 'Acme Listed Supply', 'source' => \App\Models\Supplier::SOURCE_PMD]);
        \App\Models\Supplier::create(['company' => 'Unrelated Traders', 'source' => \App\Models\Supplier::SOURCE_IMPORT]);

        $response = $this->actingAs(makeMasterDataUser())
            ->getJson(route('master.suppliers.search', ['q' => 'acme']))
            ->assertOk();

        $rows = collect($response->json())->keyBy('company');

        expect($rows)->toHaveCount(2);
        expect($rows['Acme Dormant Supply']['listed'])->toBeFalse();
        expect($rows['Acme Dormant Supply']['email'])->toBe('hi@acme.test');
        expect($rows['Acme Listed Supply']['listed'])->toBeTrue();
    });

    it('suggests from the first character typed', function () {
        \App\Models\Supplier::create(['company' => 'A One Supply', 'source' => \App\Models\Supplier::SOURCE_IMPORT]);
        \App\Models\Supplier::create(['company' => 'Zenith Foundry', 'source' => \App\Models\Supplier::SOURCE_IMPORT]);

        $response = $this->actingAs(makeMasterDataUser())
            ->getJson(route('master.suppliers.search', ['q' => 'a']))
            ->assertOk();

        expect(collect($response->json())->pluck('company')->all())->toBe(['A One Supply']);
    });

    it('returns nothing for an empty type-ahead query', function () {
        \App\Models\Supplier::create(['company' => 'A One Supply', 'source' => \App\Models\Supplier::SOURCE_IMPORT]);

        $this->actingAs(makeMasterDataUser())
            ->getJson(route('master.suppliers.search', ['q' => '  ']))
            ->assertOk()
            ->assertExactJson([]);
    });

    it('keeps a supplier accredited when the add form no longer asks', function () {
        $this->actingAs(makeMasterDataUser())->post(route('master.suppliers.store'), [
            'company' => 'No Toggle Co',
        ])->assertRedirect();

        expect(\App\Models\Supplier::first()->accredited)->toBeTrue();
    });

    it('offers only listed suppliers in the RFQ dropdown', function () {
        \App\Models\Supplier::create(['company' => 'Listed Accredited Co', 'source' => \App\Models\Supplier::SOURCE_PMD, 'accredited' => true]);
        \App\Models\Supplier::create(['company' => 'Hidden Accredited Co', 'source' => \App\Models\Supplier::SOURCE_IMPORT, 'accredited' => true]);

        $user = makeMasterDataUser();
        $project = \App\Models\Project::create([
            'project_no'  => 'PRJ-SUP-' . uniqid(),
            'title'       => 'Supplier Dropdown Project',
            'site'        => 'Main Plant',
            'asset_id'    => 'A1',
            'class_name'  => 'Minor',
            'priority'    => '1',
            'status_key'  => 'PLANNING',
            'work_force'  => 'In-House',
            'wr_no'       => 'WR-1',
            'wr_date'     => now(),
            'dept_owner'  => 'Engineering',
            'cost_code'   => 'CC-001',
            'category'    => 'General',
            'service_type' => 'Repair',
            'deadline'    => now()->addDays(30),
            'created_by'  => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('projects.hub.rfq', ['project' => $project->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('hub_data.suppliers', 1)
                ->where('hub_data.suppliers.0.name', 'Listed Accredited Co'));
    });
});

// -------------------------------------------------
// COST CODE dropdown labels
// -------------------------------------------------

describe('cost code option labels', function () {

    it('puts the department between the code and the description', function () {
        $code = CostCode::create([
            'name'        => '1.01.00.000.000',
            'description' => 'CURRENT ASSETS',
            'division'    => 'Administration Division',
            'cost_center' => 'Accounting Department',
        ]);

        expect($code->optionLabel())->toBe('1.01.00.000.000 — Accounting Department — CURRENT ASSETS');
    });

    it('leaves out the parts a cost code does not have', function () {
        expect(CostCode::create(['name' => 'CC-BARE'])->optionLabel())->toBe('CC-BARE');

        expect(CostCode::create(['name' => 'CC-NO-DEPT', 'description' => 'Repairs'])->optionLabel())
            ->toBe('CC-NO-DEPT — Repairs');
    });

    it('feeds the labelled options to the project request form', function () {
        CostCode::create([
            'name'        => 'CC-100',
            'description' => 'Building Repairs',
            'cost_center' => 'Maintenance Department',
        ]);

        $this->actingAs(makeMasterDataUser())
            ->get(route('requests.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('costCodes.0.name', 'CC-100')
                ->where('costCodes.0.label', 'CC-100 — Maintenance Department — Building Repairs'));
    });
});
