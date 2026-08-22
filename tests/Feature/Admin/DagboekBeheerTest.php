<?php

use App\Livewire\Admin\DagboekBeheer;
use App\Models\Dagboek;
use App\Models\JournalEntry;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\FiscalYearService;
use Database\Seeders\DagboekSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(DagboekSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist dagboeken.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/dagboeken')->assertForbidden();
});

it('rendert de vijf geseede dagboeken voor een beheerder', function () {
    $this->actingAs($this->beheerder)
        ->get('/beheer/dagboeken')
        ->assertOk()
        ->assertSee('Verkoopboek')
        ->assertSee('Inkoopboek')
        ->assertSee('Memoriaal')
        ->assertSee('Bank')
        ->assertSee('Kas');
});

it('maakt een extra Bank-dagboek aan', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(DagboekBeheer::class)
        ->set('name', 'Bank — ASN spaarrekening')
        ->set('type', 'bank')
        ->call('save')
        ->assertHasNoErrors();

    expect(Dagboek::query()->where('name', 'Bank — ASN spaarrekening')->where('type', 'bank')->exists())->toBeTrue();
});

it('weigert een tweede Verkoop-dagboek aan te maken', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(DagboekBeheer::class)
        ->set('name', 'Nog een verkoopboek')
        ->set('type', 'verkoop')
        ->call('save')
        ->assertHasErrors(['type']);

    expect(Dagboek::query()->where('type', 'verkoop')->count())->toBe(1);
});

it('hernoemt een bestaand dagboek', function () {
    $dagboek = Dagboek::query()->where('type', 'bank')->firstOrFail();
    $this->actingAs($this->beheerder);

    Livewire::test(DagboekBeheer::class)
        ->call('edit', $dagboek->id)
        ->set('name', 'Bank — Rabobank')
        ->call('save')
        ->assertHasNoErrors();

    expect($dagboek->fresh()->name)->toBe('Bank — Rabobank');
});

it('verwijdert een ongebruikt Bank-dagboek', function () {
    $dagboek = Dagboek::query()->where('type', 'bank')->firstOrFail();
    $this->actingAs($this->beheerder);

    Livewire::test(DagboekBeheer::class)->call('delete', $dagboek->id);

    expect(Dagboek::query()->whereKey($dagboek->id)->exists())->toBeFalse();
});

it('weigert een dagboek met journaalposten te verwijderen', function () {
    $dagboek = Dagboek::query()->where('type', 'bank')->firstOrFail();
    $period = app(FiscalYearService::class)->periodForDate(now());
    JournalEntry::create(['dagboek_id' => $dagboek->id, 'period_id' => $period->id, 'date' => now(), 'description' => 'test', 'reference' => null]);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(DagboekBeheer::class)->call('delete', $dagboek->id);

    expect(Dagboek::query()->whereKey($dagboek->id)->exists())->toBeTrue()
        ->and($component->get('errorMessage'))->not->toBeNull();
});

it('weigert een vast dagboek (Verkoop/Inkoop/Memoriaal) te verwijderen', function () {
    $dagboek = Dagboek::query()->where('type', 'verkoop')->firstOrFail();
    $this->actingAs($this->beheerder);

    Livewire::test(DagboekBeheer::class)->call('delete', $dagboek->id);

    expect(Dagboek::query()->whereKey($dagboek->id)->exists())->toBeTrue();
});
