<?php

use App\Enums\MembershipStatus;
use App\Livewire\Admin\FacturatieBeheer;
use App\Models\Charge;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\BillingService;
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

it('factureert in één keer de openstaande posten van alle betalers', function () {
    $otherDebtor = Person::create(['first_name' => 'Anna', 'last_name' => 'Andere']);
    app(BillingService::class)->createCharge($this->product, $this->debtor, '90.00', 'Post 1');
    app(BillingService::class)->createCharge($this->product, $otherDebtor, '25.00', 'Post 2');

    $this->actingAs($this->beheerder);

    Livewire::test(FacturatieBeheer::class)
        ->call('invoiceAllDebtors')
        ->assertSet('statusMessage', '2 factuur/facturen aangemaakt (€ 115,00 totaal).');

    expect(Invoice::query()->count())->toBe(2)
        ->and(Charge::query()->whereNull('invoice_id')->count())->toBe(0);
});

it('meldt dat er niets te factureren valt als er geen openstaande posten zijn', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(FacturatieBeheer::class)
        ->call('invoiceAllDebtors')
        ->assertSet('statusMessage', 'Geen openstaande posten om te factureren.');

    expect(Invoice::query()->count())->toBe(0);
});

it('bereidt een contributie-run voor en voert die uit via het scherm', function () {
    ProductPrice::create(['product_id' => $this->product->id, 'valid_from' => '2026-01-01', 'amount' => '120.00']);
    $type = MembershipType::create(['key' => 'a-livewire-test', 'name' => 'A-lid', 'product_id' => $this->product->id]);
    $lid = Person::create(['first_name' => 'Contri', 'last_name' => 'Butant']);
    Membership::create([
        'person_id' => $lid->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => '2026-03-01',
        'billing_person_id' => $lid->id,
    ]);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(FacturatieBeheer::class)
        ->set('contributionYear', 2026)
        ->call('previewContributionRun');

    expect($component->get('contributionPreview'))->toHaveCount(1);

    $component->call('runContributionRun');

    expect(Charge::query()->where('subject_type', Membership::class)->count())->toBe(1)
        ->and($component->get('statusMessage'))->toContain('1 posten aangemaakt');
});
