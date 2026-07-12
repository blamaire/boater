<?php

use App\Livewire\Admin\ProductBeheer;
use App\Models\LedgerAccount;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(LedgerAccountSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist products.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/producten')->assertForbidden();
});

it('rendert de productbeheer-pagina voor een beheerder', function () {
    $this->actingAs($this->beheerder)->get('/beheer/producten')->assertOk()->assertSee('Producten');
});

it('maakt een product aan met opbrengstrekening', function () {
    $account = LedgerAccount::query()->where('code', '8000')->firstOrFail();
    $this->actingAs($this->beheerder);

    $component = Livewire::test(ProductBeheer::class)
        ->set('name', 'Seniorlidmaatschap')
        ->set('type', 'contributie')
        ->set('ledgerAccountId', $account->id)
        ->set('isRecurring', true)
        ->set('recurrence', 'jaarlijks')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->firstOrFail();

    // Na aanmaken blijft het product in bewerkmodus zodat de prijs direct
    // ingevoerd kan worden (het prijsvak is dan zichtbaar).
    $component->assertSet('editingId', $product->id);

    expect($product->name)->toBe('Seniorlidmaatschap')
        ->and($product->type->value)->toBe('contributie')
        ->and($product->ledger_account_id)->toBe($account->id)
        ->and($product->is_recurring)->toBeTrue()
        ->and($product->recurrence->value)->toBe('jaarlijks');
});

it('legt meteen een beginprijs vast bij het aanmaken', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ProductBeheer::class)
        ->set('name', 'Jeugdlidmaatschap')
        ->set('type', 'contributie')
        ->set('priceValidFrom', '2026-01-01')
        ->set('priceAmount', '85.00')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('name', 'Jeugdlidmaatschap')->firstOrFail();
    expect($product->prices()->count())->toBe(1)
        ->and((float) $product->currentPrice()->amount)->toBe(85.0);
});

it('maakt zonder bedrag gewoon een product zonder prijs aan', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ProductBeheer::class)
        ->set('name', 'Nog geen prijs')
        ->set('type', 'contributie')
        ->set('priceAmount', '')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::query()->where('name', 'Nog geen prijs')->firstOrFail();
    expect($product->prices()->count())->toBe(0);
});

it('vereist een ingangsdatum als er wel een bedrag maar geen datum is', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ProductBeheer::class)
        ->set('name', 'Bedrag zonder datum')
        ->set('type', 'contributie')
        ->set('priceValidFrom', '')
        ->set('priceAmount', '50.00')
        ->call('save')
        ->assertHasErrors(['priceValidFrom']);
});

it('zet Geldig vanaf standaard op vandaag', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ProductBeheer::class)
        ->assertSet('priceValidFrom', now()->toDateString());
});

it('vereist een herhaalschema wanneer het product terugkerend is', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ProductBeheer::class)
        ->set('name', 'Test')
        ->set('isRecurring', true)
        ->set('recurrence', null)
        ->call('save')
        ->assertHasErrors(['recurrence']);
});

it('legt prijshistorie vast en berekent de huidige prijs', function () {
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $this->actingAs($this->beheerder);

    Livewire::test(ProductBeheer::class)
        ->call('edit', $product->id)
        ->set('priceValidFrom', '2026-01-01')
        ->set('priceAmount', '120.00')
        ->call('addPrice')
        ->assertHasNoErrors();

    // Een oudere prijs mag de nieuwe niet overschrijven als geldende prijs.
    $product->prices()->create(['valid_from' => '2025-01-01', 'amount' => '100.00']);

    $current = $product->fresh()->currentPrice();
    expect($current)->not->toBeNull()
        ->and((float) $current->amount)->toBe(120.0)
        ->and($product->priceOn(Carbon::parse('2025-06-01'))->amount)->toBe('100.00');
});

it('koppelt en ontkoppelt lidmaatschapsvormen aan het product', function () {
    $type = MembershipType::create(['key' => 'senior', 'name' => 'Senior']);
    $product = Product::create(['name' => 'Contributie senior', 'type' => 'contributie']);
    $this->actingAs($this->beheerder);

    Livewire::test(ProductBeheer::class)
        ->call('edit', $product->id)
        ->set('linkedMembershipTypeIds', [$type->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($type->fresh()->product_id)->toBe($product->id);

    Livewire::test(ProductBeheer::class)
        ->call('edit', $product->id)
        ->set('linkedMembershipTypeIds', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($type->fresh()->product_id)->toBeNull();
});

it('verwijdert een product en ontkoppelt de lidmaatschapsvorm', function () {
    $type = MembershipType::create(['key' => 'senior', 'name' => 'Senior']);
    $product = Product::create(['name' => 'Weg', 'type' => 'contributie']);
    $type->update(['product_id' => $product->id]);

    $this->actingAs($this->beheerder);

    Livewire::test(ProductBeheer::class)->call('delete', $product->id);

    expect(Product::query()->count())->toBe(0)
        ->and($type->fresh()->product_id)->toBeNull();
});
