<?php

use App\Livewire\Admin\EnvironmentBeheer;
use App\Models\Environment;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist environments.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/omgevingen')->assertForbidden();
});

it('rendert de beheer-pagina voor een beheerder', function () {
    $this->actingAs($this->beheerder)->get('/beheer/omgevingen')->assertOk()->assertSee('Omgevingen');
});

it('maakt een nieuwe omgeving aan met versleutelde token', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(EnvironmentBeheer::class)
        ->set('name', 'test')
        ->set('url', 'https://rzvg-tst.lamaire.nl')
        ->set('apiToken', str_repeat('a', 32))
        ->set('isActive', true)
        ->call('save')
        ->assertHasNoErrors();

    $env = Environment::query()->firstOrFail();
    expect($env->name)->toBe('test')
        ->and($env->url)->toBe('https://rzvg-tst.lamaire.nl')
        ->and($env->api_token)->toBe(str_repeat('a', 32))
        ->and($env->getRawOriginal('api_token'))->not->toBe(str_repeat('a', 32));
});

it('bewaart bestaande token als het veld leeg blijft bij wijzigen', function () {
    $env = Environment::query()->create([
        'name' => 'test',
        'url' => 'https://rzvg-tst.lamaire.nl',
        'api_token' => str_repeat('b', 32),
        'is_active' => true,
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(EnvironmentBeheer::class)
        ->call('edit', $env->id)
        ->set('name', 'test-2')
        ->set('apiToken', '')
        ->call('save')
        ->assertHasNoErrors();

    $env->refresh();
    expect($env->name)->toBe('test-2')
        ->and($env->api_token)->toBe(str_repeat('b', 32));
});

it('valideert dat een nieuwe omgeving een token en geldige URL heeft', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(EnvironmentBeheer::class)
        ->set('name', 'test')
        ->set('url', 'geen-url')
        ->set('apiToken', 'kort')
        ->call('save')
        ->assertHasErrors(['url', 'apiToken']);
});

it('verwijdert een omgeving', function () {
    $env = Environment::query()->create([
        'name' => 'test',
        'url' => 'https://rzvg-tst.lamaire.nl',
        'api_token' => str_repeat('c', 32),
        'is_active' => true,
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(EnvironmentBeheer::class)
        ->call('delete', $env->id);

    expect(Environment::query()->count())->toBe(0);
});
