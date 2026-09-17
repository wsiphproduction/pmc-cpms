<?php

use App\Mail\RfqDispatched;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeApproverForRfq(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

function makeProjectForRfq(User $creator): Project
{
    return Project::create([
        'project_no' => 'PRJ-TEST-' . uniqid(),
        'title' => 'RFQ Test Project',
        'site' => 'Main Plant',
        'asset_id' => 'A1',
        'class_name' => 'Minor',
        'priority' => '1',
        'status_key' => 'PLANNING',
        'work_force' => 'In-House',
        'wr_no' => 'WR-1',
        'wr_date' => now(),
        'dept_owner' => 'Engineering',
        'cost_code' => 'CC-001',
        'category' => 'General',
        'service_type' => 'Repair',
        'deadline' => now()->addDays(30),
        'created_by' => $creator->id,
    ]);
}

it('ccs additional recipients and the sender when dispatching an rfq', function () {
    Mail::fake();

    $approver = makeApproverForRfq();
    $project = makeProjectForRfq($approver);

    $this->actingAs($approver)->post(route('hub.rfq.store', $project), [
        'contractor_name' => 'ABC Construction',
        'recipient_email' => 'contractor@example.com',
        'additional_recipients' => ['second@example.com', 'third@example.com'],
        'cc_self' => true,
    ])->assertRedirect();

    Mail::assertQueued(RfqDispatched::class, function ($mail) use ($approver) {
        return $mail->hasTo('contractor@example.com')
            && $mail->hasCc('second@example.com')
            && $mail->hasCc('third@example.com')
            && $mail->hasCc($approver->email);
    });
});

it('always copies procurement on a dispatched rfq, without it being in the form', function () {
    Mail::fake();
    config(['mail.procurement_cc' => 'procurement@example.com, buyer@example.com']);

    $approver = makeApproverForRfq();
    $project = makeProjectForRfq($approver);

    $this->actingAs($approver)->post(route('hub.rfq.store', $project), [
        'contractor_name' => 'ABC Construction',
        'recipient_email' => 'contractor@example.com',
    ])->assertRedirect();

    Mail::assertQueued(RfqDispatched::class, fn ($mail) => $mail->hasTo('contractor@example.com')
        && $mail->hasCc('procurement@example.com')
        && $mail->hasCc('buyer@example.com'));
});

it('copies nobody extra when no procurement address is configured', function () {
    Mail::fake();
    config(['mail.procurement_cc' => '']);

    $approver = makeApproverForRfq();
    $project = makeProjectForRfq($approver);

    $this->actingAs($approver)->post(route('hub.rfq.store', $project), [
        'contractor_name' => 'ABC Construction',
        'recipient_email' => 'contractor@example.com',
    ])->assertRedirect();

    Mail::assertQueued(RfqDispatched::class, fn ($mail) => $mail->hasTo('contractor@example.com')
        && empty($mail->cc));
});

it('does not cc the sender when cc_self is not requested', function () {
    Mail::fake();

    $approver = makeApproverForRfq();
    $project = makeProjectForRfq($approver);

    $this->actingAs($approver)->post(route('hub.rfq.store', $project), [
        'contractor_name' => 'ABC Construction',
        'recipient_email' => 'contractor@example.com',
    ])->assertRedirect();

    Mail::assertQueued(RfqDispatched::class, function ($mail) use ($approver) {
        return $mail->hasTo('contractor@example.com') && !$mail->hasCc($approver->email);
    });
});

it('fails validation when an additional recipient email is invalid', function () {
    $approver = makeApproverForRfq();
    $project = makeProjectForRfq($approver);

    $this->actingAs($approver)->post(route('hub.rfq.store', $project), [
        'contractor_name' => 'ABC Construction',
        'recipient_email' => 'contractor@example.com',
        'additional_recipients' => ['not-an-email'],
    ])->assertSessionHasErrors('additional_recipients.0');
});
