<?php

use App\Livewire\Admin\BtwCodeBeheer;
use App\Models\BtwCode;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BtwCodeSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(BtwCodeSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist btw_codes.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/btw-codes')->assertForbidden();
});

it('rendert de geseede standaardcodes voor een beheerder', function () {
    $this->actingAs($this->beheerder)
        ->get('/beheer/btw-codes')
        ->assertOk()
        ->assertSee('21% hoog tarief')
        ->assertSee('9% laag tarief')
        ->assertSee('0% vrijgesteld');
});

it('maakt een nieuwe BTW-code aan', function () {
    $account = LedgerAccount::query()->where('code', '1600')->firstOrFail();
    $this->actingAs($this->beheerder);

    Livewire::test(BtwCodeBeheer::class)
        ->set('name', '13% overgangstarief')
        ->set('percentage', '13.00')
        ->set('direction', 'af_te_dragen')
        ->set('ledgerAccountId', $account->id)
        ->set('validFrom', '2026-01-01')
        ->call('save')
        ->assertHasNoErrors();

    $code = BtwCode::query()->where('name', '13% overgangstarief')->firstOrFail();
    expect((float) $code->percentage)->toBe(13.0)
        ->and($code->ledger_account_id)->toBe($account->id);
});

it('weigert verwijderen van een BTW-code die aan een product gekoppeld is', function () {
    $code = BtwCode::query()->where('name', '21% hoog tarief')->firstOrFail();
    Product::create(['name' => 'Advertentie', 'type' => 'advertentie', 'btw_code_id' => $code->id]);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(BtwCodeBeheer::class)->call('delete', $code->id);

    expect(BtwCode::query()->whereKey($code->id)->exists())->toBeTrue()
        ->and($component->get('errorMessage'))->not->toBeNull();
});

it('verwijdert een ongebruikte BTW-code', function () {
    $code = BtwCode::query()->where('name', '0% vrijgesteld')->firstOrFail();
    $this->actingAs($this->beheerder);

    Livewire::test(BtwCodeBeheer::class)->call('delete', $code->id);

    expect(BtwCode::query()->whereKey($code->id)->exists())->toBeFalse();
});
