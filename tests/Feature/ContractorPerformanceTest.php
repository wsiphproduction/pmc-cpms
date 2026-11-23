<?php

use App\Models\ContractorEvaluation;
use App\Models\Project;
use App\Models\ProjectNtp;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ContractorEvaluationScoring as Scoring;
use App\Support\ContractorPerformance;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function cpUser(string $role): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function cpProject(array $attributes = []): Project
{
    return Project::create([
        'project_no'           => 'PRJ-CP-' . uniqid(),
        'title'                => 'Consolidated Project',
        'project_manager_name' => 'Engineer',
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
        'project_type'         => 'minor',
        'deadline'             => now()->addDays(30),
        ...$attributes,
    ]);
}

function cpNtp(Project $project, string $contractor, float $cost = 100000, string $status = 'issued'): ProjectNtp
{
    return ProjectNtp::create([
        'project_id'      => $project->id,
        'ntp_no'          => 'NTP-' . uniqid(),
        'contractor_name' => $contractor,
        'baseline_start'  => '2026-01-01',
        'baseline_end'    => '2026-06-01',
        'approved_cost'   => $cost,
        'status'          => $status,
    ]);
}

/**
 * An evaluation with every category rated `$rating` (so its score is
 * $rating × 20), in the given status.
 */
function cpEvaluation(Project $project, string $contractor, int $rating, array $attributes = [], string $status = ContractorEvaluation::STATUS_APPROVED): ContractorEvaluation
{
    $evaluation = ContractorEvaluation::create([
        'project_id'      => $project->id,
        'control_no'      => 'CPE-T-' . uniqid(),
        'period_type'     => 'monthly',
        'period_label'    => 'Test period',
        'contractor_name' => $contractor,
        'supplier_id'     => ContractorEvaluation::supplierIdFor($contractor),
        'status'          => $status,
        'approved_at'     => $status === ContractorEvaluation::STATUS_APPROVED ? now() : null,
        ...$attributes,
    ]);

    foreach (array_keys(Scoring::categories()) as $category) {
        $ratings = array_fill_keys(Scoring::criterionKeys($category), $rating);
        $evaluation->categories()->create([
            'category' => $category,
            'ratings'  => $ratings,
            'score'    => Scoring::categoryScore($category, $ratings),
        ]);
    }
    $evaluation->refreshTotal();

    return $evaluation->fresh();
}

it('averages each project once, by its latest approved evaluation', function () {
    Supplier::create(['company' => 'Acme Builders', 'is_active' => true]);
    $one = cpProject();
    $two = cpProject();
    cpNtp($one, 'Acme Builders');
    cpNtp($two, 'ACME builders');

    cpEvaluation($one, 'Acme Builders', 3, ['period_end' => '2026-08-31']);   // 60, superseded
    cpEvaluation($one, 'Acme Builders', 5, ['period_end' => '2026-09-30']);   // 100, latest
    cpEvaluation($two, 'Acme Builders', 4, ['period_end' => '2026-09-30']);   // 80
    cpEvaluation($two, 'Acme Builders', 1, [], ContractorEvaluation::STATUS_FOR_APPROVAL); // not approved — ignored

    $ranking = (new ContractorPerformance)->ranking();

    expect($ranking)->toHaveCount(1)
        ->and($ranking[0]['name'])->toBe('Acme Builders')
        ->and($ranking[0]['projects'])->toBe(2)
        ->and($ranking[0]['evaluated'])->toBe(2)
        ->and($ranking[0]['evaluations'])->toBe(3)
        ->and($ranking[0]['score'])->toBe(90.0)
        ->and($ranking[0]['classification'])->toBe('Excellent');
});

it('leaves projects without an approved evaluation out of the average, as Not Yet Evaluated', function () {
    $done = cpProject();
    $open = cpProject();
    cpNtp($done, 'Beta Construction');
    cpNtp($open, 'Beta Construction');
    cpEvaluation($done, 'Beta Construction', 4);

    $row = (new ContractorPerformance)->ranking()[0];
    $stats = (new ContractorPerformance)->stats();

    expect($row['score'])->toBe(80.0)
        ->and($row['pending'])->toBe(1)
        ->and($stats['projects'])->toBe(2)
        ->and($stats['projects_evaluated'])->toBe(1)
        ->and($stats['pending_evaluation'])->toBe(1);
});

it('ranks contractors best first, with unevaluated ones last', function () {
    cpNtp(cpProject(), 'Unrated Co');
    cpEvaluation($p = cpProject(), 'Gamma Works', 3);
    cpNtp($p, 'Gamma Works');
    cpEvaluation($q = cpProject(), 'Delta Builders', 5);
    cpNtp($q, 'Delta Builders');

    $ranking = (new ContractorPerformance)->ranking();

    expect(array_column($ranking, 'name'))->toBe(['Delta Builders', 'Gamma Works', 'Unrated Co'])
        ->and(array_column($ranking, 'rank'))->toBe([1, 2, null]);
});

it('weights projects by contract value when asked', function () {
    $big = cpProject();
    $small = cpProject();
    cpNtp($big, 'Epsilon Corp', 900000);
    cpNtp($small, 'Epsilon Corp', 100000);
    cpEvaluation($big, 'Epsilon Corp', 5);    // 100
    cpEvaluation($small, 'Epsilon Corp', 1);  // 20

    expect((new ContractorPerformance)->ranking()[0]['score'])->toBe(60.0)
        ->and((new ContractorPerformance(['basis' => 'value']))->ranking()[0]['score'])->toBe(92.0);
});

it('flags a low rating and a deficiency recurring across evaluations', function () {
    $p = cpProject();
    cpNtp($p, 'Zeta Contractors');
    cpEvaluation($p, 'Zeta Contractors', 2, ['period_end' => '2026-08-31']);
    cpEvaluation($p, 'Zeta Contractors', 2, ['period_end' => '2026-09-30']);

    $flags = collect((new ContractorPerformance)->ranking()[0]['flags']);

    expect($flags->pluck('type'))->toContain('low_rating')
        ->and($flags->where('type', 'recurring')->count())->toBe(8);
});

it('filters by evaluation window, project and rating', function () {
    $a = cpProject(['dept_owner' => 'Mill']);
    $b = cpProject(['dept_owner' => 'Engineering']);
    cpNtp($a, 'Eta Builders');
    cpNtp($b, 'Theta Builders');
    cpEvaluation($a, 'Eta Builders', 5, ['period_end' => '2026-03-31']);
    cpEvaluation($b, 'Theta Builders', 3, ['period_end' => '2026-09-30']);

    $names = fn (array $filters) => array_column((new ContractorPerformance($filters))->ranking(), 'name');

    expect($names(['department' => 'Mill']))->toBe(['Eta Builders'])
        // 3 across the board is 60 — Needs Improvement.
        ->and($names(['rating' => 'Needs Improvement']))->toBe(['Theta Builders'])
        ->and($names(['contractor' => 'theta']))->toBe(['Theta Builders']);

    // Outside the window the evaluation does not count, but the NTP still assigns the project.
    $row = collect((new ContractorPerformance(['period' => 'custom', 'from' => '2026-06-01', 'to' => '2026-12-31']))->ranking())
        ->firstWhere('name', 'Eta Builders');
    expect($row['score'])->toBeNull()->and($row['pending'])->toBe(1);
});

it('shows the dashboard and a contractor profile to PMD, not to department users', function () {
    $supplier = Supplier::create(['company' => 'Iota Builders', 'is_active' => true]);
    $p = cpProject();
    cpNtp($p, 'Iota Builders');
    cpEvaluation($p, 'Iota Builders', 4);

    $manager = cpUser(User::ROLE_PMD_DEPT_MANAGER);

    $this->actingAs($manager)->get(route('contractor-performance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contractor-performance/index')
            ->where('stats.contractors', 1)
            ->where('contractors.0.key', 's' . $supplier->id));

    $this->actingAs($manager)->get(route('contractor-performance.show', ['c' => 's' . $supplier->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.name', 'Iota Builders')
            ->where('profile.score', 80)
            ->where('profile.rank', 1));

    $this->actingAs($manager)->get(route('contractor-performance.show', ['c' => 's999999']))->assertNotFound();

    $this->actingAs(cpUser(User::ROLE_REQUESTOR))->get(route('contractor-performance.index'))->assertForbidden();
});

it('downloads the three contractor performance reports as Excel', function () {
    $supplier = Supplier::create(['company' => 'Kappa Builders', 'is_active' => true]);
    $p = cpProject();
    cpNtp($p, 'Kappa Builders');
    cpEvaluation($p, 'Kappa Builders', 4);
    $manager = cpUser(User::ROLE_PMD_DEPT_MANAGER);

    foreach ([[], ['report' => 'evaluations'], ['report' => 'contractor', 'c' => 's' . $supplier->id]] as $query) {
        $response = $this->actingAs($manager)->get(route('contractor-performance.export', $query));
        $response->assertOk();
        expect($response->headers->get('Content-Type'))->toContain('spreadsheetml')
            ->and(substr($response->getContent(), 0, 2))->toBe('PK');
    }
});
