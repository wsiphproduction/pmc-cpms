<?php

use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\ProjectRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// -------------------------------------------------
// Helpers
// -------------------------------------------------

function makeNtpRequestor(): User
{
    Role::firstOrCreate(['name' => 'requestor']);

    $user = User::factory()->create();
    $user->assignRole('requestor');

    return $user;
}

/** A project the given requestor owns, so its NTPs land in their review list. */
function makeNtpReviewProject(User $requestor): Project
{
    static $sequence = 0;
    $sequence++;

    $project = Project::create([
        'project_no'   => sprintf('PRJ-NTP-%04d', $sequence),
        'title'        => 'NTP Review Project ' . $sequence,
        'site'         => 'Main Plant',
        'asset_id'     => 'Asset A',
        'class_name'   => 'Minor',
        'priority'     => 'P1 - Urgent',
        'status_key'   => 'ONGOING',
        'work_force'   => 'Contractor',
        'wr_no'        => 'WR-' . $sequence,
        'wr_date'      => now(),
        'dept_owner'   => 'Engineering',
        'cost_code'    => 'CC-001',
        'category'     => 'General',
        'service_type' => 'Repair',
        'deadline'     => now()->addDays(30),
        'created_by'   => $requestor->id,
    ]);

    $request = ProjectRequest::factory()->create([
        'requester_id' => $requestor->id,
        'status'       => 'approved',
    ]);
    $project->update(['project_request_id' => $request->id]);

    return $project;
}

function makeNtp(Project $project, string $status, string $no, $createdAt = null): ProjectNtp
{
    $ntp = ProjectNtp::create([
        'project_id'      => $project->id,
        'ntp_no'          => $no,
        'contractor_name' => 'ABC Builders',
        'baseline_start'  => '2026-01-01',
        'baseline_end'    => '2026-06-01',
        'approved_cost'   => 250000,
        'status'          => $status,
    ]);

    if ($createdAt) {
        $ntp->forceFill(['created_at' => $createdAt])->save();
    }

    return $ntp;
}

// -------------------------------------------------
// Review list ordering
// -------------------------------------------------

describe('ntp review list', function () {

    it('puts NTPs still for review ahead of settled ones, however recent those are', function () {
        $requestor = makeNtpRequestor();
        $project   = makeNtpReviewProject($requestor);

        // The issued and rejected rows are newer, so recency alone would float
        // them to the top — the review-state ordering has to win.
        makeNtp($project, 'issued', 'NTP-ISSUED', now());
        makeNtp($project, 'rejected', 'NTP-REJECTED', now()->subDay());
        makeNtp($project, 'pending_review', 'NTP-PENDING', now()->subWeek());

        $this->actingAs($requestor)
            ->get(route('ntp-reviews.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('ntps', 3)
                ->where('ntps.0.ntp_no', 'NTP-PENDING'));
    });

    it('keeps recency order among the NTPs already reviewed', function () {
        $requestor = makeNtpRequestor();
        $project   = makeNtpReviewProject($requestor);

        makeNtp($project, 'issued', 'NTP-OLDER', now()->subWeek());
        makeNtp($project, 'issued', 'NTP-NEWER', now());

        $this->actingAs($requestor)
            ->get(route('ntp-reviews.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ntps.0.ntp_no', 'NTP-NEWER')
                ->where('ntps.1.ntp_no', 'NTP-OLDER'));
    });

    it('only lists NTPs on the projects the requestor asked for', function () {
        $requestor = makeNtpRequestor();
        $stranger  = makeNtpRequestor();

        makeNtp(makeNtpReviewProject($requestor), 'pending_review', 'NTP-MINE');
        makeNtp(makeNtpReviewProject($stranger), 'pending_review', 'NTP-THEIRS');

        $this->actingAs($requestor)
            ->get(route('ntp-reviews.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('ntps', 1)
                ->where('ntps.0.ntp_no', 'NTP-MINE'));
    });
});

// -------------------------------------------------
// Department user's project view
// -------------------------------------------------

describe('dept project view', function () {

    it('shows the NTPs raised on the project, with the sub-project each one spawned', function () {
        $requestor = makeNtpRequestor();
        $project   = makeNtpReviewProject($requestor);

        $issued = makeNtp($project, 'issued', 'NTP-ISSUED');
        makeNtp($project, 'pending_review', 'NTP-PENDING');

        // The sub-project created from the issued NTP.
        $sub = makeNtpReviewProject($requestor);
        $sub->update(['parent_id' => $project->id, 'source_ntp_id' => $issued->id]);

        $this->actingAs($requestor)
            ->get(route('projects.show', $project->id))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($sub) {
                $page->where('is_dept_view', true)
                    // The RFQ list a dept user already had stays alongside it.
                    ->has('hub_data.rfqs')
                    ->has('hub_data.ntps', 2);

                $ntps = collect($page->toArray()['props']['hub_data']['ntps'])->keyBy('ntp_no');

                expect($ntps['NTP-ISSUED']['spawned_sub_id'])->toBe($sub->id);
                expect($ntps['NTP-ISSUED']['spawned_sub_no'])->toBe($sub->project_no);
                // Nothing was spawned from the one still awaiting review.
                expect($ntps['NTP-PENDING']['spawned_sub_id'])->toBeNull();
            });
    });

    it('leaves the rest of the operations hub out of the dept view', function () {
        $requestor = makeNtpRequestor();
        $project   = makeNtpReviewProject($requestor);

        $this->actingAs($requestor)
            ->get(route('projects.show', $project->id))
            ->assertInertia(fn (Assert $page) => $page
                ->has('hub_data.rfqs')
                ->has('hub_data.ntps')
                ->missing('hub_data.permits')
                ->missing('hub_data.vofs'));
    });
});

// -------------------------------------------------
// Sub-project access for the requesting department user
// -------------------------------------------------

describe('dept sub-project access', function () {

    it('lets the requester view a sub-project of the project they asked for', function () {
        $requestor = makeNtpRequestor();
        $parent    = makeNtpReviewProject($requestor);

        // Spawned from an NTP, so it carries no project request of its own.
        $sub = Project::create([
            'parent_id'    => $parent->id,
            'project_no'   => $parent->project_no . '-A',
            'title'        => 'Sub of ' . $parent->title,
            'site'         => 'Main Plant',
            'asset_id'     => 'Asset A',
            'class_name'   => 'Minor',
            'priority'     => 'P1 - Urgent',
            'status_key'   => 'ONGOING',
            'work_force'   => 'Contractor',
            'wr_no'        => 'WR-SUB',
            'wr_date'      => now(),
            'dept_owner'   => 'Engineering',
            'cost_code'    => 'CC-001',
            'category'     => 'General',
            'service_type' => 'Repair',
            'deadline'     => now()->addDays(30),
        ]);

        expect($sub->project_request_id)->toBeNull();

        $this->actingAs($requestor)
            ->get(route('projects.show', $sub->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('is_dept_view', true)
                // Viewing only: no edit or delete inside the sub-project.
                ->where('project.can.update', false)
                ->where('project.can.delete', false)
                ->where('project.parent.project_no', $parent->project_no));
    });

    it('still refuses a sub-project belonging to another requester', function () {
        $requestor = makeNtpRequestor();
        $stranger  = makeNtpRequestor();
        $parent    = makeNtpReviewProject($stranger);

        $sub = Project::create([
            'parent_id'    => $parent->id,
            'project_no'   => $parent->project_no . '-A',
            'title'        => 'Not yours',
            'site'         => 'Main Plant',
            'asset_id'     => 'Asset A',
            'class_name'   => 'Minor',
            'priority'     => 'P1 - Urgent',
            'status_key'   => 'ONGOING',
            'work_force'   => 'Contractor',
            'wr_no'        => 'WR-SUB',
            'wr_date'      => now(),
            'dept_owner'   => 'Engineering',
            'cost_code'    => 'CC-001',
            'category'     => 'General',
            'service_type' => 'Repair',
            'deadline'     => now()->addDays(30),
        ]);

        $this->actingAs($requestor)->get(route('projects.show', $sub->id))->assertForbidden();
    });

    it('refuses the requester any action inside a sub-project they can view', function () {
        $requestor = makeNtpRequestor();
        $parent    = makeNtpReviewProject($requestor);

        $sub = Project::create([
            'parent_id'    => $parent->id,
            'project_no'   => $parent->project_no . '-A',
            'title'        => 'View only',
            'site'         => 'Main Plant',
            'asset_id'     => 'Asset A',
            'class_name'   => 'Minor',
            'priority'     => 'P1 - Urgent',
            'status_key'   => 'ONGOING',
            'work_force'   => 'Contractor',
            'wr_no'        => 'WR-SUB',
            'wr_date'      => now(),
            'dept_owner'   => 'Engineering',
            'cost_code'    => 'CC-001',
            'category'     => 'General',
            'service_type' => 'Repair',
            'deadline'     => now()->addDays(30),
        ]);

        $this->actingAs($requestor)
            ->patch(route('projects.update-status', $sub->id), ['status_key' => 'COMPLETED'])
            ->assertForbidden();

        $this->actingAs($requestor)->get(route('projects.edit', $sub->id))->assertForbidden();

        expect($sub->fresh()->status_key)->toBe('ONGOING');
    });
});
