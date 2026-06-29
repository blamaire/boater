<?php

use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Services\Authorization\EffectivePermissions;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->resolver = app(EffectivePermissions::class);
});

it('returns no permissions when person has no roles or direct permissions', function () {
    $person = Person::create([
        'first_name' => 'Test',
        'last_name' => 'Persoon',
    ]);

    expect($this->resolver->for($person))->toBeEmpty();
});

it('includes role permissions for active role assignments', function () {
    $person = makePerson();
    $role = makeRoleWith(['persons.search', 'activities.view']);
    assignRole($person, $role);

    expect($this->resolver->for($person)->all())
        ->toEqualCanonicalizing(['persons.search', 'activities.view']);
});

it('unions permissions when person has multiple roles', function () {
    $person = makePerson();
    $roleA = makeRoleWith(['persons.search', 'activities.view']);
    $roleB = makeRoleWith(['reservations.view', 'activities.view']);
    assignRole($person, $roleA);
    assignRole($person, $roleB);

    expect($this->resolver->for($person)->all())
        ->toEqualCanonicalizing(['persons.search', 'activities.view', 'reservations.view']);
});

it('excludes deactivated role assignments', function () {
    $person = makePerson();
    $role = makeRoleWith(['persons.search']);
    assignRole($person, $role, ['status' => 'deactivated', 'deactivated_at' => Carbon::now()]);

    expect($this->resolver->for($person))->toBeEmpty();
});

it('excludes role assignments whose ends_at is in the past', function () {
    $person = makePerson();
    $role = makeRoleWith(['persons.search']);
    assignRole($person, $role, ['ends_at' => Carbon::now()->subDay()]);

    expect($this->resolver->for($person))->toBeEmpty();
});

it('includes direct person permissions', function () {
    $person = makePerson();
    $permission = Permission::create([
        'key' => 'impersonate',
        'module' => 'support',
        'action' => 'impersonate',
    ]);
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => $permission->id,
    ]);

    expect($this->resolver->for($person)->all())->toEqual(['impersonate']);
});

it('excludes expired direct person permissions', function () {
    $person = makePerson();
    $permission = Permission::create([
        'key' => 'impersonate',
        'module' => 'support',
        'action' => 'impersonate',
    ]);
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => $permission->id,
        'ends_at' => Carbon::now()->subDay(),
    ]);

    expect($this->resolver->for($person))->toBeEmpty();
});

it('has() returns true when permission is present, false otherwise', function () {
    $person = makePerson();
    $role = makeRoleWith(['persons.search']);
    assignRole($person, $role);

    expect($this->resolver->has($person, 'persons.search'))->toBeTrue();
    expect($this->resolver->has($person, 'nonexistent.permission'))->toBeFalse();
});

function makePerson(): Person
{
    return Person::create([
        'first_name' => 'Test',
        'last_name' => 'Persoon',
    ]);
}

function makeRoleWith(array $permissionKeys): Role
{
    $role = Role::create(['name' => 'Test-' . uniqid()]);
    foreach ($permissionKeys as $key) {
        $permission = Permission::firstOrCreate(
            ['key' => $key],
            ['module' => explode('.', $key)[0], 'action' => explode('.', $key)[1] ?? 'view'],
        );
        $role->permissions()->attach($permission->id);
    }
    return $role;
}

function assignRole(Person $person, Role $role, array $overrides = []): RoleAssignment
{
    return RoleAssignment::create(array_merge([
        'person_id' => $person->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ], $overrides));
}
