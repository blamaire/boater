<?php

use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

/**
 * Maak een user aan met een gekoppelde persoon en de gevraagde permissies.
 *
 * @param  array<int, string>  $permissionKeys
 */
function loginWithPermissions(array $permissionKeys): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'Test',
        'last_name' => 'Beheerder',
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

it('weigert gast op alle rol-routes', function () {
    $this->get('/beheer/rollen')->assertRedirect('/login');
    $this->get('/beheer/rollen/nieuw')->assertRedirect('/login');
    $this->post('/beheer/rollen', [])->assertRedirect('/login');
});

it('weigert ingelogde gebruiker zonder roles.view', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create([
        'first_name' => 'Jan',
        'last_name' => 'Lid',
        'account_id' => $user->id,
    ]);

    $this->actingAs($user)->get('/beheer/rollen')->assertForbidden();
});

it('toont de rollijst voor gebruikers met roles.view maar staat geen wijzigen toe', function () {
    $user = loginWithPermissions(['roles.view']);

    $this->actingAs($user)
        ->get('/beheer/rollen')
        ->assertOk()
        ->assertSee('Beheerder')
        ->assertDontSee('Nieuwe rol');

    // Zonder roles.create/update mag de gebruiker het formulier niet openen.
    $this->actingAs($user)->get('/beheer/rollen/nieuw')->assertForbidden();

    $someRole = Role::create(['name' => 'Testrol', 'is_system' => false]);
    $this->actingAs($user)->get('/beheer/rollen/'.$someRole->id.'/bewerken')->assertForbidden();
});

it('laat gebruiker met roles.create een rol aanmaken', function () {
    $user = loginWithPermissions(['roles.view', 'roles.create', 'roles.update']);
    $viewPermission = Permission::query()->where('key', 'reservations.view')->value('id');

    $this->actingAs($user)
        ->post('/beheer/rollen', [
            'name' => 'Reserveringsleider',
            'description' => 'Beheert reserveringen',
            'permissions' => [$viewPermission],
        ])
        ->assertRedirect();

    $role = Role::query()->where('name', 'Reserveringsleider')->first();
    expect($role)->not->toBeNull()
        ->and($role->is_system)->toBeFalse()
        ->and($role->permissions->pluck('key')->all())->toContain('reservations.view');
});

it('laat gebruiker met roles.update een niet-systeem-rol wijzigen', function () {
    $user = loginWithPermissions(['roles.view', 'roles.update']);
    $role = Role::create(['name' => 'Voorloper', 'description' => 'Oude naam', 'is_system' => false]);

    $newPermission = Permission::query()->where('key', 'persons.view')->value('id');

    $this->actingAs($user)
        ->patch('/beheer/rollen/'.$role->id, [
            'name' => 'Nieuwe rolnaam',
            'description' => 'Nieuwe omschrijving',
            'permissions' => [$newPermission],
        ])
        ->assertRedirect();

    $role->refresh();
    expect($role->name)->toBe('Nieuwe rolnaam')
        ->and($role->permissions->pluck('key')->all())->toContain('persons.view');
});

it('weigert wijziging van de Beheerder-systeem-rol', function () {
    $user = loginWithPermissions(['roles.view', 'roles.update']);
    $beheerder = Role::query()->where('name', 'Beheerder')->firstOrFail();

    $originalPermissionCount = $beheerder->permissions()->count();

    $this->actingAs($user)
        ->patch('/beheer/rollen/'.$beheerder->id, [
            'name' => 'Herbenaamd',
            'description' => 'Verandering',
            'permissions' => [],
        ])
        ->assertRedirect('/beheer/rollen')
        ->assertSessionHas('error');

    $beheerder->refresh();
    expect($beheerder->name)->toBe('Beheerder')
        ->and($beheerder->permissions()->count())->toBe($originalPermissionCount);
});

it('weigert verwijderen van de Beheerder-systeem-rol via de UI', function () {
    $user = loginWithPermissions(['roles.view', 'roles.delete']);
    $beheerder = Role::query()->where('name', 'Beheerder')->firstOrFail();

    $this->actingAs($user)
        ->delete('/beheer/rollen/'.$beheerder->id)
        ->assertRedirect('/beheer/rollen')
        ->assertSessionHas('error');

    expect(Role::query()->where('name', 'Beheerder')->exists())->toBeTrue();
});

it('staat verwijderen van een gewone rol toe', function () {
    $user = loginWithPermissions(['roles.view', 'roles.delete']);
    $role = Role::create(['name' => 'Weg ermee', 'is_system' => false]);

    $this->actingAs($user)
        ->delete('/beheer/rollen/'.$role->id)
        ->assertRedirect('/beheer/rollen')
        ->assertSessionHas('status');

    expect(Role::query()->where('id', $role->id)->exists())->toBeFalse();
});

it('valideert dat rolnaam uniek is', function () {
    $user = loginWithPermissions(['roles.view', 'roles.create']);
    Role::create(['name' => 'Duplicaat', 'is_system' => false]);

    $this->actingAs($user)
        ->post('/beheer/rollen', [
            'name' => 'Duplicaat',
            'description' => 'Poging tot duplicaat',
            'permissions' => [],
        ])
        ->assertSessionHasErrors('name');
});

it('kan een nieuwe rol aan een persoon koppelen', function () {
    $user = loginWithPermissions(['roles.view', 'roles.create', 'roles.update']);
    $doelPerson = Person::create([
        'first_name' => 'Doel',
        'last_name' => 'Persoon',
    ]);
    $permissie = Permission::query()->where('key', 'reservations.view')->value('id');

    // Maak eerst de rol aan via de admin-UI.
    $this->actingAs($user)
        ->post('/beheer/rollen', [
            'name' => 'Reserveringsleider',
            'description' => 'Beheert reserveringen',
            'permissions' => [$permissie],
        ])
        ->assertRedirect();

    $role = Role::query()->where('name', 'Reserveringsleider')->firstOrFail();

    // Koppel de rol aan de doelpersoon.
    $this->actingAs($user)
        ->post('/beheer/personen/'.$doelPerson->id.'/rollen', [
            'role_id' => $role->id,
            'reason' => 'Nieuwe verantwoordelijke',
        ])
        ->assertRedirect(route('admin.person-roles.index', $doelPerson));

    $assignment = RoleAssignment::query()
        ->where('person_id', $doelPerson->id)
        ->where('role_id', $role->id)
        ->firstOrFail();

    expect($assignment->status)->toBe('active')
        ->and($assignment->reason)->toBe('Nieuwe verantwoordelijke')
        ->and($assignment->assigned_at)->not->toBeNull();
});

it('deactiveert een assignment en zet status en deactivated_at', function () {
    $user = loginWithPermissions(['roles.view', 'roles.update']);
    $doelPerson = Person::create([
        'first_name' => 'Doel',
        'last_name' => 'Persoon',
    ]);
    $role = Role::create(['name' => 'Tijdelijk', 'is_system' => false]);
    $assignment = RoleAssignment::create([
        'person_id' => $doelPerson->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete('/beheer/personen/'.$doelPerson->id.'/rollen/'.$assignment->id)
        ->assertRedirect(route('admin.person-roles.index', $doelPerson));

    $assignment->refresh();
    expect($assignment->status)->toBe('deactivated')
        ->and($assignment->deactivated_at)->not->toBeNull();
});

it('weigert toewijzen zonder roles.update', function () {
    $user = loginWithPermissions(['roles.view']);
    $doelPerson = Person::create([
        'first_name' => 'Doel',
        'last_name' => 'Persoon',
    ]);
    $role = Role::create(['name' => 'Iets', 'is_system' => false]);

    $this->actingAs($user)
        ->post('/beheer/personen/'.$doelPerson->id.'/rollen', [
            'role_id' => $role->id,
        ])
        ->assertForbidden();
});
