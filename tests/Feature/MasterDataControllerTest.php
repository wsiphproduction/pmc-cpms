<?php

use App\Models\CostCode;
use App\Models\JobLocation;
use App\Models\JobType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// -------------------------------------------------
// Helpers
// -------------------------------------------------

function makeMasterDataUser(): User
{
    return User::factory()->create();
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

        $this->actingAs(makeMasterDataUser())
            ->get(route('master.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master-data/index')
                ->has('jobTypes', 1)
                ->has('jobLocations', 1)
                ->has('costCodes', 1)
                ->where('jobTypes.0.name', 'Civil')
                ->where('jobLocations.0.name', 'Site A')
                ->where('costCodes.0.name', 'CC-001')
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
