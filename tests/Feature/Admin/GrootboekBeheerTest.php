<?php

use App\Livewire\Admin\GrootboekBeheer;
use App\Models\Hoofdverdichting;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Verdichting;
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
});

it('vereist ledger_accounts.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/grootboek')->assertForbidden();
});

it('rendert de geseede rekeningen voor een beheerder', function () {
    $this->actingAs($this->beheerder)
        ->get('/beheer/grootboek')
        ->assertOk()
        ->assertSee('Debiteuren');
});

it('maakt een hoofdverdichting aan', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(GrootboekBeheer::class)
        ->set('hvCode', '1')
        ->set('hvName', 'Activa')
        ->call('saveHoofdverdichting')
        ->assertHasNoErrors();

    expect(Hoofdverdichting::query()->where('code', '1')->where('name', 'Activa')->exists())->toBeTrue();
});

it('maakt een verdichting aan gekoppeld aan een hoofdverdichting', function () {
    $hv = Hoofdverdichting::create(['code' => '1', 'name' => 'Activa']);
    $this->actingAs($this->beheerder);

    Livewire::test(GrootboekBeheer::class)
        ->set('vCode', '10')
        ->set('vName', 'Liquide middelen')
        ->set('vHoofdverdichtingId', $hv->id)
        ->call('saveVerdichting')
        ->assertHasNoErrors();

    $v = Verdichting::query()->where('code', '10')->firstOrFail();
    expect($v->hoofdverdichting_id)->toBe($hv->id);
});

it('weigert een hoofdverdichting met verdichtingen te verwijderen', function () {
    $hv = Hoofdverdichting::create(['code' => '1', 'name' => 'Activa']);
    Verdichting::create(['hoofdverdichting_id' => $hv->id, 'code' => '10', 'name' => 'Liquide middelen']);
    $this->actingAs($this->beheerder);

    $component = Livewire::test(GrootboekBeheer::class)->call('deleteHoofdverdichting', $hv->id);

    expect(Hoofdverdichting::query()->whereKey($hv->id)->exists())->toBeTrue()
        ->and($component->get('errorMessage'))->not->toBeNull();
});

it('maakt een grootboekrekening aan met een verdichting', function () {
    $hv = Hoofdverdichting::create(['code' => '1', 'name' => 'Activa']);
    $v = Verdichting::create(['hoofdverdichting_id' => $hv->id, 'code' => '10', 'name' => 'Liquide middelen']);
    $this->actingAs($this->beheerder);

    Livewire::test(GrootboekBeheer::class)
        ->set('code', '1050')
        ->set('name', 'Spaarrekening')
        ->set('type', 'activa')
        ->set('verdichtingId', $v->id)
        ->call('save')
        ->assertHasNoErrors();

    $account = LedgerAccount::query()->where('code', '1050')->firstOrFail();
    expect($account->verdichting_id)->toBe($v->id);
});

it('weigert verwijderen van een grootboekrekening die in gebruik is', function () {
    $account = LedgerAccount::query()->where('code', '1300')->firstOrFail();
    Product::create(['name' => 'Contributie', 'type' => 'contributie', 'ledger_account_id' => $account->id]);
    $this->actingAs($this->beheerder);

    $component = Livewire::test(GrootboekBeheer::class)->call('delete', $account->id);

    expect(LedgerAccount::query()->whereKey($account->id)->exists())->toBeTrue()
        ->and($component->get('errorMessage'))->not->toBeNull();
});

it('verwijdert een ongebruikte grootboekrekening', function () {
    $account = LedgerAccount::create(['code' => '9999', 'name' => 'Test', 'type' => 'activa']);
    $this->actingAs($this->beheerder);

    Livewire::test(GrootboekBeheer::class)->call('delete', $account->id);

    expect(LedgerAccount::query()->whereKey($account->id)->exists())->toBeFalse();
});
