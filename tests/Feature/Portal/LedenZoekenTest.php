<?php

use App\Livewire\Portal\LedenZoeken;
use App\Models\FieldDefinition;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\User;
use App\Services\Portal\PersonFieldVisibilityResolver;
use Database\Seeders\FieldDefinitionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(FieldDefinitionSeeder::class);
});

/**
 * @param  array<int, string>  $permissionKeys
 */
function portaalGebruiker(array $permissionKeys, ?string $firstName = 'Test', ?string $lastName = 'Portaal'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'account_id' => $user->id,
    ]);
    foreach ($permissionKeys as $key) {
        $permissionId = Permission::query()->where('key', $key)->value('id');
        PersonPermission::create([
            'person_id' => $person->id,
            'permission_id' => $permissionId,
            'status' => 'active',
        ]);
    }

    return $user;
}

it('weigert gast op de ledengids', function () {
    $this->get('/leden')->assertRedirect('/login');
});

it('weigert ingelogde gebruiker zonder persons.search', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create([
        'first_name' => 'Zonder',
        'last_name' => 'Recht',
        'account_id' => $user->id,
    ]);

    $this->actingAs($user)->get('/leden')->assertForbidden();
});

it('geeft toegang aan gebruiker met persons.search', function () {
    $user = portaalGebruiker(['persons.search']);

    $this->actingAs($user)->get('/leden')->assertOk();
});

it('zoekt standaard op naam en toont alleen matches', function () {
    $user = portaalGebruiker(['persons.search']);
    Person::create(['first_name' => 'Anna', 'last_name' => 'Roeier']);
    Person::create(['first_name' => 'Piet', 'last_name' => 'Zeiler']);

    Livewire::actingAs($user)
        ->test(LedenZoeken::class)
        ->set('zoekterm', 'Roeier')
        ->assertSee('Anna')
        ->assertDontSee('Piet Zeiler');
});

it('respecteert standaard-zichtbaarheid: e-mail is standaard verborgen', function () {
    $user = portaalGebruiker(['persons.search']);
    Person::create([
        'first_name' => 'Anna',
        'last_name' => 'Roeier',
        'email' => 'anna@example.test',
    ]);

    Livewire::actingAs($user)
        ->test(LedenZoeken::class)
        ->set('zoekterm', 'Roeier')
        ->assertSee('Anna')
        ->assertDontSee('anna@example.test');
});

it('verbergt contactgegevens van minderjarigen ook zonder expliciete opt-in', function () {
    $volwassene = Person::create([
        'first_name' => 'Volwassen',
        'last_name' => 'Persoon',
        'date_of_birth' => now()->subYears(30)->toDateString(),
        'email' => 'volwassen@example.test',
        'phone' => '0612345678',
    ]);
    $minderjarige = Person::create([
        'first_name' => 'Jong',
        'last_name' => 'Persoon',
        'date_of_birth' => now()->subYears(12)->toDateString(),
        'email' => 'jong@example.test',
        'phone' => '0698765432',
    ]);

    $resolver = new PersonFieldVisibilityResolver;

    $volwassenVisible = $resolver->visibleFieldsFor($volwassene);
    $minderjarigVisible = $resolver->visibleFieldsFor($minderjarige);

    // Standaard: volwassene volgt default (email/phone niet default_visible → verborgen).
    expect($volwassenVisible)->not->toContain('email');
    expect($volwassenVisible)->not->toContain('phone');

    // Minderjarige: sowieso verborgen zonder expliciete opt-in.
    expect($minderjarigVisible)->not->toContain('email');
    expect($minderjarigVisible)->not->toContain('phone');
});

it('toont een naamresultaat altijd, ook zonder FieldDefinition-records', function () {
    // Simuleer een omgeving waar de seed nog niet gedraaid is.
    FieldDefinition::query()->delete();

    $user = portaalGebruiker(['persons.search']);
    Person::create(['first_name' => 'Anna', 'last_name' => 'Naamloos']);

    Livewire::actingAs($user)
        ->test(LedenZoeken::class)
        ->set('zoekterm', 'Naamloos')
        ->assertSee('Anna');
});
