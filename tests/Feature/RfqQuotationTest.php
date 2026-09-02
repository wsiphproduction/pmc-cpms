<?php

use App\Mail\RfqDispatched;
use App\Models\Project;
use App\Models\ProjectRfq;
use App\Models\ProjectRfqItem;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

function makeApproverForQuotation(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function makeProjectForQuotation(User $creator): Project
{
    return Project::create([
        'project_no'  => 'PRJ-QTN-' . uniqid(),
        'title'       => 'Quotation Test Project',
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
        'created_by'  => $creator->id,
    ]);
}

/** The quotation form the hub posts, with the fields under test overridable. */
function quotationForm(array $overrides = []): array
{
    return array_merge([
        'scope_of_work'    => 'Structural works',
        'due_date'         => '2026-12-01',
        'duration_days'    => 45,
        'terms_conditions' => 'Net 30',
        'inclusions'       => 'Labor',
        'exclusions'       => 'Permits',
        'items'            => [
            ['description' => 'Excavation', 'qty' => 2, 'unit' => 'lot', 'unit_cost' => 1000, 'total_cost' => 2000],
        ],
    ], $overrides);
}

beforeEach(function () {
    Mail::fake();

    $this->approver = makeApproverForQuotation();
    $this->project  = makeProjectForQuotation($this->approver);

    $this->actingAs($this->approver)
        ->post(route('hub.rfq.store', $this->project), [
            'contractor_name' => 'Acme Builders',
            'recipient_email' => 'vendor@example.com',
        ])->assertRedirect();

    $this->rfq = ProjectRfq::where('project_id', $this->project->id)->firstOrFail();
});

it('opens every rfq with a single final quotation', function () {
    expect($this->rfq->quotations()->count())->toBe(1)
        ->and($this->rfq->finalQuotation()->first()->is_final)->toBeTrue();
});

it('links the supplier portal from the dispatch email', function () {
    Mail::assertSent(RfqDispatched::class, fn ($mail) => str_contains(
        $mail->render(),
        $this->rfq->portalUrl(),
    ));
});

it('mirrors whichever quotation is final onto the rfq row', function () {
    $first = $this->rfq->finalQuotation()->first();

    $this->actingAs($this->approver)
        ->patch(route('hub.rfq.quotations.update', [$this->project, $this->rfq, $first]), quotationForm())
        ->assertRedirect();

    expect($this->rfq->fresh()->scope_of_work)->toBe('Structural works')
        ->and((float) $this->rfq->fresh()->items->sum('total_cost'))->toBe(2000.0);

    // A revised offer, seeded from the first so only the changes need retyping.
    $this->actingAs($this->approver)
        ->post(route('hub.rfq.quotations.store', [$this->project, $this->rfq]), [
            'label'     => 'Revised offer',
            'copy_from' => $first->id,
        ])->assertRedirect();

    $second = $this->rfq->quotations()->where('seq', 2)->firstOrFail();
    expect($second->scope_of_work)->toBe('Structural works')
        ->and($second->items()->count())->toBe(1)
        ->and($second->is_final)->toBeFalse();

    $this->actingAs($this->approver)
        ->patch(route('hub.rfq.quotations.update', [$this->project, $this->rfq, $second]), quotationForm([
            'scope_of_work' => 'Structural works (revised)',
            'duration_days' => 60,
            'items' => [['description' => 'Excavation', 'qty' => 3, 'unit' => 'lot', 'unit_cost' => 1000, 'total_cost' => 3000]],
        ]))->assertRedirect();

    // The row still reports the first offer while that one holds the final flag.
    expect((float) $this->rfq->fresh()->items->sum('total_cost'))->toBe(2000.0);

    $this->actingAs($this->approver)
        ->patch(route('hub.rfq.quotations.final', [$this->project, $this->rfq, $second]))
        ->assertRedirect();

    $rfq = $this->rfq->fresh();
    expect($rfq->scope_of_work)->toBe('Structural works (revised)')
        ->and((int) $rfq->duration_days)->toBe(60)
        ->and($rfq->items)->toHaveCount(1)
        ->and((float) $rfq->items->sum('total_cost'))->toBe(3000.0);
});

it('hands the final flag on when the final quotation is deleted', function () {
    $first = $this->rfq->finalQuotation()->first();

    $this->actingAs($this->approver)
        ->patch(route('hub.rfq.quotations.update', [$this->project, $this->rfq, $first]), quotationForm())
        ->assertRedirect();

    $this->actingAs($this->approver)
        ->post(route('hub.rfq.quotations.store', [$this->project, $this->rfq]), ['copy_from' => $first->id])
        ->assertRedirect();

    $second = $this->rfq->quotations()->where('seq', 2)->firstOrFail();

    $this->actingAs($this->approver)
        ->delete(route('hub.rfq.quotations.destroy', [$this->project, $this->rfq, $first]))
        ->assertRedirect();

    expect($this->rfq->quotations()->count())->toBe(1)
        ->and($second->fresh()->is_final)->toBeTrue()
        ->and(ProjectRfqItem::where('project_rfq_quotation_id', $first->id)->count())->toBe(0);
});

it('refuses to delete the only quotation an rfq has', function () {
    $only = $this->rfq->finalQuotation()->first();

    $this->actingAs($this->approver)
        ->delete(route('hub.rfq.quotations.destroy', [$this->project, $this->rfq, $only]))
        ->assertSessionHas('error');

    expect($this->rfq->quotations()->count())->toBe(1);
});

it('re-sends the rfq email to a corrected address', function () {
    $this->actingAs($this->approver)
        ->post(route('hub.rfq.resend', [$this->project, $this->rfq]), [
            'recipient_email'       => 'newvendor@example.com',
            'additional_recipients' => ['purchasing@example.com'],
            'cc_self'               => true,
        ])->assertRedirect();

    expect($this->rfq->fresh()->recipient_email)->toBe('newvendor@example.com');

    Mail::assertSent(RfqDispatched::class, 2);
    Mail::assertSent(RfqDispatched::class, fn ($mail) => $mail->hasTo('newvendor@example.com')
        && $mail->hasCc('purchasing@example.com')
        && $mail->hasCc($this->approver->email));
});
