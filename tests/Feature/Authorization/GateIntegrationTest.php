<?php

use App\Models\Permission;
use App\Models\Person;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

it('grants ability through Gate when person has effective permission', function () {
    $user = User::factory()->create();
    $person = Person::create([
        'first_name' => 'Test',
        'last_name' => 'Persoon',
        'account_id' => $user->id,
    ]);

    $permission = Permission::create([
        'key' => 'persons.search',
        'module' => 'persons',
        'action' => 'search',
    ]);
    $role = Role::create(['name' => 'TestRol']);
    $role->permissions()->attach($permission->id);

    RoleAssignment::create([
        'person_id' => $person->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    expect(Gate::forUser($user)->allows('persons.search'))->toBeTrue();
});

it('denies ability through Gate when person lacks effective permission', function () {
    $user = User::factory()->create();
    Person::create([
        'first_name' => 'Test',
        'last_name' => 'Persoon',
        'account_id' => $user->id,
    ]);

    expect(Gate::forUser($user)->allows('persons.search'))->toBeFalse();
});

it('denies ability when user has no person linked', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('persons.search'))->toBeFalse();
});
