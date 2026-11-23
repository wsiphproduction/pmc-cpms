<?php

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectBilling;
use App\Models\ProjectCompletion;
use App\Models\User;
use App\Support\AssessmentForm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function paUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function paProject(User $engineer, array $attributes = []): Project
{
    return Project::create([
        'project_no'           => 'PRJ-PA-' . uniqid(),
        'title'                => 'Assessed Project',
        'project_manager_id'   => $engineer->id,
        'project_manager_name' => $engineer->name,
        'site'                 => 'Main Plant',
        'asset_id'             => 'A1',
        'class_name'           => 'Minor',
        'priority'             => '1',
        'status_key'           => 'ONGOING',
        'work_force'           => 'Contractor',
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

function paSafetyData(): array
{
    return [
        'work_activity'   => 'Excavation for footing F-3',
        'work_location'   => 'Mill area',
        'assessment_date' => '2026-11-20',
        'hazards' => [
            ['task' => 'Excavate', 'hazard' => 'Trench collapse', 'likelihood' => '4', 'severity' => '5', 'controls' => 'Shoring; barricades',
             'residual_likelihood' => '2', 'residual_severity' => '5', 'risk' => ['score' => 1, 'rating' => 'Low']],
            ['task' => '', 'hazard' => ''], // blank row — dropped
        ],
    ];
}

it('saves a safety risk assessment draft and works out each risk itself', function () {
    Storage::fake('public');
    $engineer = paUser(User::ROLE_ENGINEER);
    $project  = paProject($engineer);

    $this->actingAs($engineer)->post(route('hub.assessments.store', [$project, 'safety-risk']), [
        'data'  => paSafetyData(),
        'files' => [UploadedFile::fake()->create('jha.pdf', 20, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $assessment = ProjectAssessment::firstOrFail();
    $row = $assessment->data['hazards'][0];

    expect($assessment->control_no)->toStartWith('SRA-')
        ->and($assessment->status)->toBe(ProjectAssessment::STATUS_DRAFT)
        ->and($assessment->title)->toBe('Excavation for footing F-3')
        ->and($assessment->data['hazards'])->toHaveCount(1)
        // The browser's "risk" is ignored: 4 × 5 = 20, Extreme; residual 2 × 5 = 10, High.
        ->and($row['risk'])->toBe(['score' => 20, 'rating' => 'Extreme'])
        ->and($row['residual_risk'])->toBe(['score' => 10, 'rating' => 'High'])
        ->and($assessment->attachments()->count())->toBe(1);

    $this->actingAs($engineer)->get(route('projects.hub.safety-risk', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('hub_data.definition.label', 'Safety Risk Assessment')
            ->where('hub_data.assessments.0.control_no', $assessment->control_no)
            ->where('hub_counts.safety-risk', 1));
});

it('rejects values of the wrong kind but saves an incomplete draft', function () {
    $engineer = paUser(User::ROLE_ENGINEER);
    $project  = paProject($engineer);

    $this->actingAs($engineer)->post(route('hub.assessments.store', [$project, 'geological']), [
        'data' => ['assessment_type' => 'volcanic', 'assessment_date' => 'yesterday'],
    ])->assertSessionHasErrors(['data.assessment_type', 'data.assessment_date']);
    expect(ProjectAssessment::count())->toBe(0); // a rejected save leaves no empty draft

    $this->actingAs($engineer)->post(route('hub.assessments.store', [$project, 'geological']), [
        'data' => ['site_location' => 'North slope'],
    ])->assertSessionHasNoErrors();

    $assessment = ProjectAssessment::firstOrFail();
    $this->actingAs($engineer)->post(route('hub.assessments.submit', [$project, $assessment]))
        ->assertSessionHasErrors('submit');
});

it('is reviewed and approved in order, and can be returned for revision', function () {
    $engineer = paUser(User::ROLE_ENGINEER);
    $asst     = paUser(User::ROLE_PMD_ASST_MANAGER);
    $manager  = paUser(User::ROLE_PMD_DEPT_MANAGER);
    $project  = paProject($engineer);

    $this->actingAs($engineer)->post(route('hub.assessments.store', [$project, 'safety-risk']), ['data' => paSafetyData()]);
    $assessment = ProjectAssessment::firstOrFail();

    $this->actingAs($engineer)->post(route('hub.assessments.submit', [$project, $assessment]))->assertSessionHasNoErrors();
    expect($assessment->fresh()->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER);

    $this->actingAs($manager)->patch(route('assessments.approve', $assessment))->assertForbidden();

    $this->actingAs($asst)->patch(route('assessments.return', $assessment), ['remarks' => 'Add the confined-space step.'])->assertRedirect();
    expect($assessment->fresh()->status)->toBe(ProjectAssessment::STATUS_DRAFT);

    // Locked while in sign-off, editable again once returned.
    $this->actingAs($engineer)->post(route('hub.assessments.update', [$project, $assessment]), ['data' => paSafetyData()])->assertSessionHasNoErrors();
    $this->actingAs($engineer)->post(route('hub.assessments.submit', [$project, $assessment]));
    $this->actingAs($engineer)->post(route('hub.assessments.update', [$project, $assessment]), ['data' => paSafetyData()])->assertStatus(422);

    $this->actingAs($asst)->patch(route('assessments.approve', $assessment))->assertRedirect();
    $this->actingAs($manager)->patch(route('assessments.approve', $assessment))->assertRedirect();

    $assessment->refresh();
    expect($assessment->status)->toBe(ProjectAssessment::STATUS_APPROVED)
        ->and($assessment->approvals()->where('status', 'approved')->count())->toBe(2);
});

it('validates a billing against the project’s own billings', function () {
    $engineer = paUser(User::ROLE_ENGINEER);
    $project  = paProject($engineer);
    $other    = paProject($engineer);
    $billing  = ProjectBilling::create(['project_id' => $project->id, 'stmt_no' => 'SOA-001', 'billing_type' => 'progress', 'amount' => 500000, 'status' => 'pending']);
    $foreign  = ProjectBilling::create(['project_id' => $other->id, 'stmt_no' => 'SOA-999', 'billing_type' => 'progress', 'amount' => 1, 'status' => 'pending']);

    $this->actingAs($engineer)->post(route('hub.assessments.store', [$project, 'billing-validation']), [
        'data' => ['billing' => $foreign->id],
    ])->assertSessionHasErrors('data.billing');

    $this->actingAs($engineer)->post(route('hub.assessments.store', [$project, 'billing-validation']), [
        'data' => [
            'billing' => $billing->id, 'validation_date' => '2026-11-20',
            'claimed_progress' => 40, 'verified_progress' => 35,
            'claimed_amount' => 500000, 'validated_amount' => 437500,
            'checks' => collect(config('project_assessments.types.billing-validation.fields'))->firstWhere('key', 'checks')['items']
                ? array_map(fn () => ['answer' => 'yes'], collect(config('project_assessments.types.billing-validation.fields'))->firstWhere('key', 'checks')['items'])
                : [],
            'recommendation' => 'adjust',
        ],
    ])->assertSessionHasNoErrors();

    $assessment = ProjectAssessment::firstOrFail();
    expect($assessment->title)->toBe('Billing SOA-001');

    $this->actingAs($engineer)->post(route('hub.assessments.submit', [$project, $assessment]))->assertSessionHasNoErrors();

    $this->actingAs($engineer)->get(route('projects.hub.billing-validation', $project))
        ->assertInertia(fn (Assert $page) => $page->where('hub_data.billings.0.value', (string) $billing->id));
});

it('sends a completion certificate for e-approval only once the details are filled, through the division manager', function () {
    $engineer = paUser(User::ROLE_ENGINEER);
    $asst     = paUser(User::ROLE_PMD_ASST_MANAGER);
    $manager  = paUser(User::ROLE_PMD_DEPT_MANAGER);
    $division = paUser(User::ROLE_DIVISION_MANAGER);
    $project  = paProject($engineer, ['status_key' => 'COMPLETED']);

    $this->actingAs($engineer)->post(route('hub.assessments.store', [$project, 'acceptance']))->assertStatus(422);

    ProjectCompletion::create(['project_id' => $project->id, 'reference_no' => 'PCA-REF-1']);

    $this->actingAs($engineer)->post(route('hub.assessments.store', [$project, 'acceptance']))->assertSessionHasNoErrors();
    $assessment = ProjectAssessment::firstOrFail();
    expect($assessment->status)->toBe(ProjectAssessment::STATUS_FOR_APPROVAL)
        ->and($assessment->approvalChainRoles())->toBe([User::ROLE_PMD_ASST_MANAGER, User::ROLE_PMD_DEPT_MANAGER, User::ROLE_DIVISION_MANAGER]);

    // Not twice.
    $this->actingAs($engineer)->post(route('hub.assessments.store', [$project, 'acceptance']))->assertStatus(422);

    foreach ([$asst, $manager, $division] as $signer) {
        $this->actingAs($signer)->patch(route('assessments.approve', $assessment))->assertRedirect();
    }

    expect($assessment->fresh()->status)->toBe(ProjectAssessment::STATUS_APPROVED);

    $this->actingAs($engineer)->get(route('projects.hub.acceptance', $project))
        ->assertInertia(fn (Assert $page) => $page->where('hub_data.assessments.0.status', 'approved'));
});

it('rates risk on the 5 x 5 matrix bands', function () {
    expect(AssessmentForm::riskRating(4))->toBe('Low')
        ->and(AssessmentForm::riskRating(5))->toBe('Medium')
        ->and(AssessmentForm::riskRating(12))->toBe('High')
        ->and(AssessmentForm::riskRating(25))->toBe('Extreme');
});
