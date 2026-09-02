<?php

use App\Mail\QuotationSubmitted;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectRfq;
use App\Models\ProjectRfqQuotation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

function makeEngineerForPortal(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function makeProjectForPortal(User $engineer, ?User $manager = null): Project
{
    return Project::create([
        'project_no'         => 'PRJ-PORTAL-' . uniqid(),
        'title'              => 'Portal Test Project',
        'site'               => 'Main Plant',
        'asset_id'           => 'A1',
        'class_name'         => 'Minor',
        'priority'           => '1',
        'status_key'         => 'PLANNING',
        'work_force'         => 'In-House',
        'wr_no'              => 'WR-1',
        'wr_date'            => now(),
        'dept_owner'         => 'Engineering',
        'cost_code'          => 'CC-001',
        'category'           => 'General',
        'service_type'       => 'Repair',
        'deadline'           => now()->addDays(30),
        'created_by'         => $engineer->id,
        'project_manager_id' => ($manager ?? $engineer)->id,
    ]);
}

/** The payload the supplier's form posts. */
function supplierForm(array $overrides = []): array
{
    return array_merge([
        'scope_of_work'    => 'Supply and install perimeter fencing',
        'due_date'         => '2026-12-01',
        'duration_days'    => 45,
        'terms_conditions' => '50% down payment',
        'inclusions'       => 'Labor and materials',
        'exclusions'       => 'Permits',
        'items'            => [
            ['description' => 'Site works', 'qty' => 2, 'unit' => 'lot', 'unit_cost' => 5000, 'total_cost' => 10000],
        ],
    ], $overrides);
}

beforeEach(function () {
    Mail::fake();

    $this->engineer = makeEngineerForPortal();
    $this->project  = makeProjectForPortal($this->engineer);

    $this->actingAs($this->engineer)
        ->post(route('hub.rfq.store', $this->project), [
            'contractor_name' => 'Acme Builders',
            'recipient_email' => 'vendor@example.com',
        ])->assertRedirect();

    $this->rfq = ProjectRfq::where('project_id', $this->project->id)->firstOrFail();
    $this->app['auth']->logout();
});

it('gives every rfq an unguessable portal token', function () {
    expect($this->rfq->portal_token)->toBeString()
        ->and(strlen($this->rfq->portal_token))->toBe(48);
});

it('opens the portal for a supplier with no account', function () {
    $this->get(route('supplier-quote.show', $this->rfq->portal_token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('supplier-quote/index')
            ->where('rfq.contractor', 'Acme Builders')
            ->where('project.project_no', $this->project->project_no));
});

it('refuses an unknown token', function () {
    $this->get(route('supplier-quote.show', 'not-a-real-token'))->assertNotFound();
});

it('saves a partial draft without notifying anyone', function () {
    $notificationsBefore = Notification::count();

    $this->post(route('supplier-quote.store', $this->rfq->portal_token), [
        'terms_conditions' => 'Still working on this',
    ])->assertRedirect();

    $quotation = $this->rfq->quotations()->where('origin', ProjectRfqQuotation::ORIGIN_SUPPLIER)->firstOrFail();

    expect($quotation->status)->toBe(ProjectRfqQuotation::STATUS_DRAFT)
        ->and($quotation->submitted_at)->toBeNull();

    // A draft is the supplier's own workspace — nothing leaves the system yet.
    Mail::assertNotSent(QuotationSubmitted::class);
    expect(Notification::count())->toBe($notificationsBefore);
});

it('carries every field of the quotation form through to the record', function () {
    $this->post(route('supplier-quote.store', $this->rfq->portal_token), [
        ...supplierForm(['label' => 'Best and final']),
        'send' => 1,
    ])->assertRedirect();

    $quotation = $this->rfq->quotations()->where('origin', ProjectRfqQuotation::ORIGIN_SUPPLIER)->firstOrFail();

    expect($quotation->label)->toBe('Best and final')
        ->and($quotation->scope_of_work)->toBe('Supply and install perimeter fencing')
        ->and(optional($quotation->due_date)->format('Y-m-d'))->toBe('2026-12-01')
        ->and((int) $quotation->duration_days)->toBe(45)
        ->and($quotation->terms_conditions)->toBe('50% down payment')
        ->and($quotation->inclusions)->toBe('Labor and materials')
        ->and($quotation->exclusions)->toBe('Permits');

    $item = $quotation->items()->firstOrFail();
    expect($item->description)->toBe('Site works')
        ->and((float) $item->qty)->toBe(2.0)
        ->and($item->unit)->toBe('lot')
        ->and((float) $item->unit_cost)->toBe(5000.0)
        ->and((float) $item->total_cost)->toBe(10000.0);
});

it('will not let a quotation be sent without a scope of work', function () {
    $form = supplierForm();
    unset($form['scope_of_work']);

    $this->post(route('supplier-quote.store', $this->rfq->portal_token), [...$form, 'send' => 1])
        ->assertSessionHasErrors('scope_of_work');
});

it('demands the full form before a quotation can be sent', function () {
    $this->post(route('supplier-quote.store', $this->rfq->portal_token), [
        'send'             => 1,
        'terms_conditions' => 'Incomplete',
    ])->assertSessionHasErrors();

    expect($this->rfq->quotations()->where('origin', ProjectRfqQuotation::ORIGIN_SUPPLIER)->count())->toBe(0);
});

it('notifies the project team once when the creator is also the manager', function () {
    $this->post(route('supplier-quote.store', $this->rfq->portal_token), [
        ...supplierForm(),
        'send' => 1,
    ])->assertRedirect();

    $quotation = $this->rfq->quotations()->where('origin', ProjectRfqQuotation::ORIGIN_SUPPLIER)->firstOrFail();

    expect($quotation->status)->toBe(ProjectRfqQuotation::STATUS_SUBMITTED)
        ->and($quotation->submitted_at)->not->toBeNull()
        ->and((float) $quotation->items()->sum('total_cost'))->toBe(10000.0);

    // Creator and manager are the same engineer, so exactly one of each.
    expect(Notification::where('recipient', $this->engineer->id)->count())->toBe(1);
    Mail::assertSent(QuotationSubmitted::class, 1);
    Mail::assertSent(QuotationSubmitted::class, fn ($mail) => $mail->hasTo($this->engineer->email));
});

it('notifies both when the manager is a different engineer', function () {
    $manager = makeEngineerForPortal();
    $project = makeProjectForPortal($this->engineer, $manager);

    $this->actingAs($this->engineer)->post(route('hub.rfq.store', $project), [
        'contractor_name' => 'Beta Builders',
    ])->assertRedirect();
    $this->app['auth']->logout();

    $rfq = ProjectRfq::where('project_id', $project->id)->firstOrFail();

    $this->post(route('supplier-quote.store', $rfq->portal_token), [
        ...supplierForm(),
        'send' => 1,
    ])->assertRedirect();

    expect(Notification::where('recipient', $this->engineer->id)->count())->toBe(1)
        ->and(Notification::where('recipient', $manager->id)->count())->toBe(1);
    Mail::assertSent(QuotationSubmitted::class, 2);
});

it('lets the supplier keep editing until the team marks it received', function () {
    $this->post(route('supplier-quote.store', $this->rfq->portal_token), [
        ...supplierForm(),
        'send' => 1,
    ])->assertRedirect();

    $quotation = $this->rfq->quotations()->where('origin', ProjectRfqQuotation::ORIGIN_SUPPLIER)->firstOrFail();

    // Still open for revision while the team has not acknowledged it.
    $this->patch(route('supplier-quote.update', [$this->rfq->portal_token, $quotation]), [
        ...supplierForm(['duration_days' => 60]),
        'send' => 1,
    ])->assertRedirect();

    expect((int) $quotation->fresh()->duration_days)->toBe(60);

    $this->actingAs($this->engineer)
        ->patch(route('hub.rfq.quotations.received', [$this->project, $this->rfq, $quotation]))
        ->assertRedirect();
    $this->app['auth']->logout();

    $quotation->refresh();
    expect($quotation->status)->toBe(ProjectRfqQuotation::STATUS_RECEIVED)
        ->and($quotation->received_at)->not->toBeNull()
        ->and($quotation->isEditableBySupplier())->toBeFalse();

    // And now the supplier is locked out of it.
    $this->patch(route('supplier-quote.update', [$this->rfq->portal_token, $quotation]), [
        ...supplierForm(['duration_days' => 90]),
    ])->assertForbidden();

    expect((int) $quotation->fresh()->duration_days)->toBe(60);
});

it('keeps the project team\'s own quotations out of the supplier portal', function () {
    // The placeholder created with the RFQ is staff-owned and must stay internal.
    $this->get(route('supplier-quote.show', $this->rfq->portal_token))
        ->assertInertia(fn ($page) => $page->has('quotations', 0));

    expect($this->rfq->quotations()->count())->toBe(1);
});

it('refuses to make an unsent draft the final quotation', function () {
    $this->post(route('supplier-quote.store', $this->rfq->portal_token), [
        'terms_conditions' => 'Draft only',
    ])->assertRedirect();

    $draft = $this->rfq->quotations()->where('origin', ProjectRfqQuotation::ORIGIN_SUPPLIER)->firstOrFail();

    $this->actingAs($this->engineer)
        ->patch(route('hub.rfq.quotations.final', [$this->project, $this->rfq, $draft]))
        ->assertSessionHas('error');

    expect($draft->fresh()->is_final)->toBeFalse();
});

it('lets the team award a submitted quotation and mirrors it onto the rfq', function () {
    $this->post(route('supplier-quote.store', $this->rfq->portal_token), [
        ...supplierForm(),
        'send' => 1,
    ])->assertRedirect();

    $quotation = $this->rfq->quotations()->where('origin', ProjectRfqQuotation::ORIGIN_SUPPLIER)->firstOrFail();

    $this->actingAs($this->engineer)
        ->patch(route('hub.rfq.quotations.final', [$this->project, $this->rfq, $quotation]))
        ->assertRedirect();

    $rfq = $this->rfq->fresh();
    expect((int) $rfq->duration_days)->toBe(45)
        ->and($rfq->scope_of_work)->toBe('Supply and install perimeter fencing')
        ->and($rfq->terms_conditions)->toBe('50% down payment')
        ->and($rfq->inclusions)->toBe('Labor and materials')
        ->and($rfq->exclusions)->toBe('Permits')
        ->and((float) $rfq->items->sum('total_cost'))->toBe(10000.0);
});
