<?php

use App\Models\Person;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('fails when the user does not exist', function () {
    $this->artisan('rzvg:make-admin', ['email' => 'bestaat.niet@example.test'])
        ->expectsOutputToContain('Geen user gevonden')
        ->assertFailed();
});

it('creates a Person and assigns the Beheerder role when the user has no linked person', function () {
    $user = User::factory()->create(['name' => 'Anne Tester', 'email' => 'anne@example.test']);

    $this->artisan('rzvg:make-admin', ['email' => 'anne@example.test'])
        ->expectsOutputToContain('Nieuwe Person aangemaakt')
        ->expectsOutputToContain('is nu Beheerder')
        ->assertSuccessful();

    $person = Person::where('account_id', $user->id)->firstOrFail();
    expect($person->first_name)->toBe('Anne')
        ->and($person->last_name)->toBe('Tester');

    $role = Role::where('name', 'Beheerder')->firstOrFail();
    expect(RoleAssignment::where('person_id', $person->id)->where('role_id', $role->id)->exists())
        ->toBeTrue();
});

it('assigns the role to an existing Person without duplicating', function () {
    $user = User::factory()->create(['email' => 'bert@example.test']);
    $person = Person::create([
        'first_name' => 'Bert',
        'last_name' => 'Bestaand',
        'account_id' => $user->id,
    ]);

    $this->artisan('rzvg:make-admin', ['email' => 'bert@example.test'])->assertSuccessful();
    $this->artisan('rzvg:make-admin', ['email' => 'bert@example.test'])->assertSuccessful();

    $role = Role::where('name', 'Beheerder')->firstOrFail();
    expect(RoleAssignment::where('person_id', $person->id)->where('role_id', $role->id)->count())
        ->toBe(1);
});

it('grants all permissions transitively through the Beheerder role', function () {
    $user = User::factory()->create(['email' => 'carol@example.test']);
    Person::create(['first_name' => 'Carol', 'last_name' => 'C', 'account_id' => $user->id]);

    $this->artisan('rzvg:make-admin', ['email' => 'carol@example.test'])->assertSuccessful();

    expect($user->fresh()->can('pages.publish'))->toBeTrue()
        ->and($user->fresh()->can('media.upload'))->toBeTrue()
        ->and($user->fresh()->can('audit_trail.view'))->toBeTrue();
});
