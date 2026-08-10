<?php

use App\Models\Dagboek;
use App\Models\Person;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\BillingService;
use Database\Seeders\DagboekSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(DagboekSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist dagboeken.manage permissie', function () {
    $dagboek = Dagboek::query()->where('type', 'verkoop')->firstOrFail();
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get("/beheer/dagboeken/{$dagboek->id}")->assertForbidden();
});

it('toont de journaalposten van een dagboek', function () {
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $debtor = Person::create(['first_name' => 'Piet', 'last_name' => 'Betaler']);
    app(BillingService::class)->createCharge($product, $debtor, '90.00', 'Contributie 2026');

    $verkoop = Dagboek::query()->where('type', 'verkoop')->firstOrFail();

    $this->actingAs($this->beheerder)
        ->get("/beheer/dagboeken/{$verkoop->id}")
        ->assertOk()
        ->assertSee($verkoop->name)
        ->assertSee('Post: Contributie 2026')
        ->assertSee('90,00');
});

it('toont een leeg dagboek zonder fouten', function () {
    $kas = Dagboek::query()->where('type', 'kas')->firstOrFail();

    $this->actingAs($this->beheerder)
        ->get("/beheer/dagboeken/{$kas->id}")
        ->assertOk()
        ->assertSee('Nog geen journaalposten');
});
