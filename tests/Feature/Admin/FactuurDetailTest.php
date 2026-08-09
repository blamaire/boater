<?php

use App\Livewire\Admin\FactuurDetail;
use App\Models\Charge;
use App\Models\Invoice;
use App\Models\Person;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\BillingService;
use Database\Seeders\BtwCodeSeeder;
use Database\Seeders\DagboekSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(DagboekSeeder::class);
    $this->seed(BtwCodeSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->debtor = Person::create(['first_name' => 'Piet', 'last_name' => 'Betaler']);
    $this->product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $this->billing = app(BillingService::class);
});

it('vereist invoices.manage permissie', function () {
    $this->billing->createCharge($this->product, $this->debtor, '90.00', 'Post');
    $invoice = $this->billing->invoiceOpenCharges($this->debtor);

    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get("/beheer/facturen/{$invoice->id}")->assertForbidden();
});

it('toont een factuur read-only voor een beheerder', function () {
    $this->billing->createCharge($this->product, $this->debtor, '90.00', 'Contributie 2026');
    $invoice = $this->billing->invoiceOpenCharges($this->debtor);

    $this->actingAs($this->beheerder)
        ->get("/beheer/facturen/{$invoice->id}")
        ->assertOk()
        ->assertSee($invoice->number)
        ->assertSee('Contributie 2026');
});

it('crediteert een post volledig via het scherm en maakt een nieuwe creditfactuur, zonder de oorspronkelijke te wijzigen', function () {
    $charge = $this->billing->createCharge($this->product, $this->debtor, '90.00', 'Post');
    $invoice = $this->billing->invoiceOpenCharges($this->debtor);

    $this->actingAs($this->beheerder);

    Livewire::test(FactuurDetail::class, ['invoice' => $invoice])
        ->set("creditReason.{$charge->id}", 'Fout ingeschreven')
        ->call('creditCharge', $charge->id)
        ->assertHasNoErrors();

    // Oorspronkelijke factuur/post blijven ongewijzigd.
    expect($charge->fresh()->status->value)->toBe('gefactureerd')
        ->and((float) $invoice->fresh()->total)->toBe(90.0);

    // Er is een nieuwe creditfactuur bijgekomen.
    expect(Invoice::query()->count())->toBe(2);
    $creditInvoice = Invoice::query()->where('id', '!=', $invoice->id)->firstOrFail();
    expect((float) $creditInvoice->total)->toBe(-90.0);

    $creditCharge = Charge::query()->where('invoice_id', $creditInvoice->id)->firstOrFail();
    expect($creditCharge->subject_type)->toBe(Charge::class)
        ->and($creditCharge->subject_id)->toBe($charge->id);
});

it('crediteert een post gedeeltelijk via het scherm', function () {
    $charge = $this->billing->createCharge($this->product, $this->debtor, '90.00', 'Post');
    $invoice = $this->billing->invoiceOpenCharges($this->debtor);

    $this->actingAs($this->beheerder);

    Livewire::test(FactuurDetail::class, ['invoice' => $invoice])
        ->set("creditAmount.{$charge->id}", '30.00')
        ->set("creditReason.{$charge->id}", 'Deelrestitutie')
        ->call('creditCharge', $charge->id)
        ->assertHasNoErrors();

    expect((float) $charge->fresh()->creditedAmount())->toBe(30.0)
        ->and($charge->fresh()->remainingCreditable())->toBe('60.00')
        ->and((float) $invoice->fresh()->total)->toBe(90.0);
});

it('geeft een validatiefout bij een te hoog credit-bedrag', function () {
    $charge = $this->billing->createCharge($this->product, $this->debtor, '90.00', 'Post');
    $invoice = $this->billing->invoiceOpenCharges($this->debtor);

    $this->actingAs($this->beheerder);

    Livewire::test(FactuurDetail::class, ['invoice' => $invoice])
        ->set("creditAmount.{$charge->id}", '200.00')
        ->set("creditReason.{$charge->id}", 'Te veel')
        ->call('creditCharge', $charge->id)
        ->assertHasErrors(["creditAmount.{$charge->id}"]);

    expect((float) $charge->fresh()->creditedAmount())->toBe(0.0);
});
