<?php

use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Role;

function makeSupplierAdmin(): User
{
    Role::firstOrCreate(['name' => 'approver']);

    $user = User::factory()->create();
    $user->assignRole('approver');

    return $user;
}

it('accepts a supplier with several email addresses', function () {
    $user = makeSupplierAdmin();

    $this->actingAs($user)
        ->post(route('master.suppliers.store'), [
            'company' => 'Multi Mailbox Traders',
            'email'   => 'sales@multi.com, admin@multi.com; owner@multi.com',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $supplier = Supplier::where('company', 'Multi Mailbox Traders')->firstOrFail();

    expect($supplier->email)->toBe('sales@multi.com, admin@multi.com, owner@multi.com')
        ->and($supplier->emails)->toBe(['sales@multi.com', 'admin@multi.com', 'owner@multi.com'])
        ->and($supplier->source)->toBe(Supplier::SOURCE_PMD);
});

it('lists a multi-email supplier in master data', function () {
    $user = makeSupplierAdmin();

    $this->actingAs($user)->post(route('master.suppliers.store'), [
        'company' => 'Multi Mailbox Traders',
        'email'   => 'sales@multi.com, admin@multi.com',
    ])->assertRedirect();

    $this->actingAs($user)->get(route('master.index'))
        ->assertInertia(fn ($page) => $page->where(
            'suppliers.0.company',
            'Multi Mailbox Traders',
        ));
});

it('takes a mailbox list far longer than the old column allowed', function () {
    $user = makeSupplierAdmin();

    // 30 addresses — well past the 191- and 500-char limits the column used to
    // carry, so this only passes while `email` is a text column.
    $addresses = collect(range(1, 30))
        ->map(fn (int $n) => "purchasing.department.contact{$n}@long-supplier-name.example.com")
        ->all();

    $this->actingAs($user)
        ->post(route('master.suppliers.store'), [
            'company' => 'Many Mailbox Traders',
            'email'   => implode(', ', $addresses),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $supplier = Supplier::where('company', 'Many Mailbox Traders')->firstOrFail();

    expect($supplier->emails)->toBe($addresses)
        ->and(strlen($supplier->email))->toBeGreaterThan(500);
});

it('rejects the list when one address is malformed', function () {
    $user = makeSupplierAdmin();

    $this->actingAs($user)
        ->post(route('master.suppliers.store'), [
            'company' => 'Broken Mailbox Traders',
            'email'   => 'sales@broken.com, not-an-email',
        ])
        ->assertSessionHasErrors('email');

    expect(Supplier::where('company', 'Broken Mailbox Traders')->exists())->toBeFalse();
});

it('still accepts a single address', function () {
    $user = makeSupplierAdmin();

    $this->actingAs($user)
        ->post(route('master.suppliers.store'), [
            'company' => 'Single Mailbox Traders',
            'email'   => 'sales@single.com',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Supplier::where('company', 'Single Mailbox Traders')->first()->email)
        ->toBe('sales@single.com');
});

it('normalizes the address list when a supplier is edited', function () {
    $user = makeSupplierAdmin();
    $supplier = Supplier::create([
        'company' => 'Edited Traders',
        'email'   => 'one@edited.com',
        'source'  => Supplier::SOURCE_PMD,
    ]);

    $this->actingAs($user)
        ->put(route('master.suppliers.update', $supplier), [
            'company' => 'Edited Traders',
            'email'   => ' one@edited.com ;two@edited.com , one@edited.com ',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($supplier->fresh()->email)->toBe('one@edited.com, two@edited.com');
});
