<?php

use App\Livewire\Admin\BoekjaarBeheer;
use App\Models\FiscalYear;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    Carbon::setTestNow(Carbon::create(2026, 3, 15));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('vereist boekjaren.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/boekjaren')->assertForbidden();
});

it('maakt lazy het lopende boekjaar met dertien periodes aan bij bezoek van de pagina', function () {
    expect(FiscalYear::query()->where('year', 2026)->exists())->toBeFalse();

    $this->actingAs($this->beheerder)->get('/beheer/boekjaren')->assertOk();

    $fiscalYear = FiscalYear::query()->where('year', 2026)->firstOrFail();
    expect($fiscalYear->periods()->count())->toBe(13);
});

it('toont alle dertien periodes met de juiste labels', function () {
    $this->actingAs($this->beheerder)
        ->get('/beheer/boekjaren')
        ->assertOk()
        ->assertSee('Beginbalans')
        ->assertSee('januari 2026')
        ->assertSee('maart 2026')
        ->assertSee('december 2026');
});

it('sluit een periode af die al voorbij is en toont de bevestiging', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(BoekjaarBeheer::class);
    $januari = FiscalYear::query()->where('year', 2026)->firstOrFail()->periods()->where('number', 1)->firstOrFail();

    Livewire::test(BoekjaarBeheer::class)
        ->call('close', $januari->id)
        ->assertSee('afgesloten');

    expect($januari->fresh()->isClosed())->toBeTrue();
});

it('toont geen afsluit-actie meer voor een reeds gesloten periode', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(BoekjaarBeheer::class);
    $januari = FiscalYear::query()->where('year', 2026)->firstOrFail()->periods()->where('number', 1)->firstOrFail();

    Livewire::test(BoekjaarBeheer::class)->call('close', $januari->id);

    $component = Livewire::test(BoekjaarBeheer::class);
    expect($component->html())->not->toContain("close({$januari->id})");
});

it('toont geen afsluit-actie voor de lopende periode', function () {
    $this->actingAs($this->beheerder);

    $component = Livewire::test(BoekjaarBeheer::class);
    $maart = FiscalYear::query()->where('year', 2026)->firstOrFail()->periods()->where('number', 3)->firstOrFail();

    expect($component->html())->not->toContain("close({$maart->id})");
});
