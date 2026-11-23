<?php

use App\Models\ContractorEvaluation;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ContractorEvaluationScoring as Scoring;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function cpeUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function cpeProject(User $engineer): Project
{
    return Project::create([
        'project_no'           => 'PRJ-CPE-' . uniqid(),
        'title'                => 'Evaluated Project',
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
    ]);
}

/** Every criterion of a category given the same rating. */
function cpeRatings(string $category, int $rating): array
{
    return array_fill_keys(Scoring::criterionKeys($category), $rating);
}

function cpeCreate($test, User $engineer, Project $project, array $overrides = []): ContractorEvaluation
{
    $test->actingAs($engineer)->post(route('hub.evaluations.store', $project), [
        'period_type'     => 'monthly',
        'period_label'    => 'October 2026',
        'contractor_name' => 'Acme Builders',
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return ContractorEvaluation::latest('id')->firstOrFail();
}

/** Rate every category at the given rating, each with one piece of evidence. */
function cpeRateAll($test, User $engineer, Project $project, ContractorEvaluation $evaluation, int $rating = 4): void
{
    foreach (array_keys(Scoring::categories()) as $category) {
        $test->actingAs($engineer)->post(route('hub.evaluations.category', [$project, $evaluation, $category]), [
            'ratings'  => cpeRatings($category, $rating),
            'evidence' => [UploadedFile::fake()->create("{$category}.pdf", 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();
    }
}

// ── Scoring ──────────────────────────────────────────────────────────────

it('defines eight categories whose criterion weights add up to 100', function () {
    $categories = Scoring::categories();

    expect($categories)->toHaveCount(8);

    $total = 0;
    foreach ($categories as $category) {
        $sum = array_sum(array_column($category['criteria'], 'weight'));
        expect(round($sum, 2))->toBe(round((float) $category['weight'], 2));
        $total += $sum;
    }

    expect(round($total, 2))->toBe(100.0);
});

it('scores a category as (rating / 5) x weight and leaves a partly rated one unscored', function () {
    // The framework's worked example: a 4 across Schedule (20%) is 16 points.
    expect(Scoring::categoryScore('schedule', cpeRatings('schedule', 4)))->toBe(16.0);

    $partial = cpeRatings('schedule', 5);
    array_pop($partial);
    expect(Scoring::categoryScore('schedule', $partial))->toBeNull();
});

it('classifies a project score by the framework bands', function () {
    expect(Scoring::classify(94)['label'])->toBe('Excellent')
        ->and(Scoring::classify(89.99)['label'])->toBe('Very Good')
        ->and(Scoring::classify(70)['label'])->toBe('Satisfactory')
        ->and(Scoring::classify(65)['label'])->toBe('Needs Improvement')
        ->and(Scoring::classify(59.99)['label'])->toBe('Unsatisfactory')
        ->and(Scoring::classify(null))->toBeNull();
});

// ── Raising and rating ───────────────────────────────────────────────────

it('creates an evaluation with all eight categories and links the contractor on master data', function () {
    $engineer = cpeUser(User::ROLE_ENGINEER);
    $project  = cpeProject($engineer);
    $supplier = Supplier::create(['company' => 'Acme Builders', 'is_active' => true]);

    $evaluation = cpeCreate($this, $engineer, $project, ['contractor_name' => '  acme builders ']);

    expect($evaluation->control_no)->toStartWith('CPE-')
        ->and($evaluation->status)->toBe(ContractorEvaluation::STATUS_DRAFT)
        ->and($evaluation->supplier_id)->toBe($supplier->id)
        ->and($evaluation->categories()->count())->toBe(8);
});

it('saves a category, scores it, and totals the evaluation once every category is rated', function () {
    Storage::fake('public');
    $engineer = cpeUser(User::ROLE_ENGINEER);
    $project  = cpeProject($engineer);
    $evaluation = cpeCreate($this, $engineer, $project);

    $this->actingAs($engineer)->post(route('hub.evaluations.category', [$project, $evaluation, 'schedule']), [
        'ratings'  => [...cpeRatings('schedule', 5), 'not_a_criterion' => 5],
        'comments' => 'Mobilized two days early.',
        'form'     => UploadedFile::fake()->create('form-a.pdf', 20, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $row = $evaluation->categories()->where('category', 'schedule')->first();
    expect($row->score)->toBe(20.0)
        ->and($row->ratings)->not->toHaveKey('not_a_criterion')
        ->and($row->attachments()->count())->toBe(1)
        ->and($evaluation->fresh()->total_score)->toBeNull();

    cpeRateAll($this, $engineer, $project, $evaluation, 4);

    expect($evaluation->fresh()->total_score)->toBe(80.0);
});

it('rejects a rating outside 1 to 5', function () {
    $engineer = cpeUser(User::ROLE_ENGINEER);
    $project  = cpeProject($engineer);
    $evaluation = cpeCreate($this, $engineer, $project);

    $this->actingAs($engineer)->post(route('hub.evaluations.category', [$project, $evaluation, 'schedule']), [
        'ratings' => ['ntp_compliance' => 6],
    ])->assertSessionHasErrors('ratings.ntp_compliance');
});

it('lists the evaluations on every evaluation page of the hub', function () {
    $engineer = cpeUser(User::ROLE_ENGINEER);
    $project  = cpeProject($engineer);
    cpeCreate($this, $engineer, $project);

    foreach (['eval-summary', 'eval-schedule', 'eval-coordination'] as $section) {
        $this->actingAs($engineer)->get(route("projects.hub.{$section}", $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hub_data.evaluations.0.contractor_name', 'Acme Builders')
                ->has('hub_data.definition.categories', 8)
                ->where('hub_counts.eval-summary', 1));
    }
});

// ── Submitting and signing ───────────────────────────────────────────────

it('will not submit until every category is rated and has evidence', function () {
    $engineer = cpeUser(User::ROLE_ENGINEER);
    $project  = cpeProject($engineer);
    $evaluation = cpeCreate($this, $engineer, $project);

    $this->actingAs($engineer)->post(route('hub.evaluations.category', [$project, $evaluation, 'schedule']), [
        'ratings' => cpeRatings('schedule', 4),
    ]);

    $this->actingAs($engineer)->post(route('hub.evaluations.submit', [$project, $evaluation]))
        ->assertSessionHasErrors('submit');

    expect($evaluation->fresh()->status)->toBe(ContractorEvaluation::STATUS_DRAFT);
});

it('is reviewed by the PMD Assistant Manager and approved by the PMD Department Manager', function () {
    Storage::fake('public');
    $engineer = cpeUser(User::ROLE_ENGINEER);
    $asst     = cpeUser(User::ROLE_PMD_ASST_MANAGER);
    $manager  = cpeUser(User::ROLE_PMD_DEPT_MANAGER);
    $project  = cpeProject($engineer);
    $evaluation = cpeCreate($this, $engineer, $project);
    cpeRateAll($this, $engineer, $project, $evaluation, 5);

    $this->actingAs($engineer)->post(route('hub.evaluations.submit', [$project, $evaluation]))->assertSessionHasNoErrors();
    $evaluation->refresh();
    expect($evaluation->status)->toBe(ContractorEvaluation::STATUS_FOR_APPROVAL)
        ->and($evaluation->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER);

    // Out of turn: the manager cannot approve before the review.
    $this->actingAs($manager)->patch(route('evaluations.approve', $evaluation))->assertForbidden();

    $this->actingAs($asst)->patch(route('evaluations.approve', $evaluation))->assertRedirect();
    expect($evaluation->fresh()->currentApprovalRole())->toBe(User::ROLE_PMD_DEPT_MANAGER);

    $this->actingAs($manager)->patch(route('evaluations.approve', $evaluation))->assertRedirect();
    $evaluation->refresh();
    expect($evaluation->status)->toBe(ContractorEvaluation::STATUS_APPROVED)
        ->and($evaluation->approved_at)->not->toBeNull()
        ->and($evaluation->total_score)->toBe(100.0);

    // Signed off, it can no longer be changed.
    $this->actingAs($engineer)->post(route('hub.evaluations.category', [$project, $evaluation, 'schedule']), [
        'ratings' => cpeRatings('schedule', 1),
    ])->assertStatus(422);
});

it('goes back to the engineer when returned, and restarts the sign-off on resubmission', function () {
    Storage::fake('public');
    $engineer = cpeUser(User::ROLE_ENGINEER);
    $asst     = cpeUser(User::ROLE_PMD_ASST_MANAGER);
    $project  = cpeProject($engineer);
    $evaluation = cpeCreate($this, $engineer, $project);
    cpeRateAll($this, $engineer, $project, $evaluation);
    $this->actingAs($engineer)->post(route('hub.evaluations.submit', [$project, $evaluation]));

    $this->actingAs($asst)->patch(route('evaluations.return', $evaluation), [])->assertSessionHasErrors('remarks');
    $this->actingAs($asst)->patch(route('evaluations.return', $evaluation), ['remarks' => 'Attach the HSE inspection log.'])->assertRedirect();

    $evaluation->refresh();
    expect($evaluation->status)->toBe(ContractorEvaluation::STATUS_DRAFT)
        ->and($evaluation->return_remarks)->toBe('Attach the HSE inspection log.');

    $this->actingAs($engineer)->post(route('hub.evaluations.submit', [$project, $evaluation]))->assertSessionHasNoErrors();
    $evaluation->refresh();
    expect($evaluation->currentApprovalRole())->toBe(User::ROLE_PMD_ASST_MANAGER)
        ->and($evaluation->approvals()->count())->toBe(2)
        ->and($evaluation->return_remarks)->toBeNull();
});

it('deletes a draft evaluation but not one in sign-off', function () {
    Storage::fake('public');
    $engineer = cpeUser(User::ROLE_ENGINEER);
    $project  = cpeProject($engineer);

    $draft = cpeCreate($this, $engineer, $project);
    $this->actingAs($engineer)->delete(route('hub.evaluations.destroy', [$project, $draft]))->assertRedirect();
    expect(ContractorEvaluation::find($draft->id))->toBeNull();

    $submitted = cpeCreate($this, $engineer, $project, ['period_label' => 'November 2026']);
    cpeRateAll($this, $engineer, $project, $submitted);
    $this->actingAs($engineer)->post(route('hub.evaluations.submit', [$project, $submitted]));
    $this->actingAs($engineer)->delete(route('hub.evaluations.destroy', [$project, $submitted]))->assertStatus(422);
});

it('does not let someone without edit rights raise an evaluation', function () {
    $engineer = cpeUser(User::ROLE_ENGINEER);
    $other    = cpeUser(User::ROLE_ENGINEER);
    $project  = cpeProject($engineer);

    $this->actingAs($other)->post(route('hub.evaluations.store', $project), [
        'period_type'     => 'monthly',
        'period_label'    => 'October 2026',
        'contractor_name' => 'Acme Builders',
    ])->assertForbidden();
});
