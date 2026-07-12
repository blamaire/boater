<?php

use App\Livewire\Admin\FacturatieBeheer;
use App\Models\Charge;
use App\Models\Invoice;
use App\Models\Person;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\BillingService;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(LedgerAccountSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->debtor = Person::create(['first_name' => 'Piet', 'last_name' => 'Betaler']);
    $this->product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
});

it('vereist invoices.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/facturatie')->assertForbidden();
});

it('rendert de facturatie-pagina voor een beheerder', function () {
    $this->actingAs($this->beheerder)->get('/beheer/facturatie')->assertOk()->assertSee('Facturatie');
});

it('voegt een post toe via het scherm', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(FacturatieBeheer::class)
        ->set('chargeDebtorId', $this->debtor->id)
        ->set('chargeProductId', $this->product->id)
        ->set('chargeAmount', '90.00')
        ->set('chargeDescription', 'Contributie 2026')
        ->call('addCharge')
        ->assertHasNoErrors();

    expect(Charge::query()->count())->toBe(1)
        ->and((float) Charge::query()->first()->amount)->toBe(90.0);
});

it('factureert de openstaande posten van een betaler via het scherm', function () {
    app(BillingService::class)->createCharge($this->product, $this->debtor, '90.00', 'Post');

    $this->actingAs($this->beheerder);

    Livewire::test(FacturatieBeheer::class)
        ->call('invoiceDebtor', $this->debtor->id);

    expect(Invoice::query()->count())->toBe(1)
        ->and((float) Invoice::query()->first()->total)->toBe(90.0);
});

it('toont een factuur read-only voor een beheerder', function () {
    app(BillingService::class)->createCharge($this->product, $this->debtor, '90.00', 'Contributie 2026');
    $invoice = app(BillingService::class)->invoiceOpenCharges($this->debtor);

    $this->actingAs($this->beheerder)
        ->get("/beheer/facturen/{$invoice->id}")
        ->assertOk()
        ->assertSee($invoice->number)
        ->assertSee('Contributie 2026');
});
