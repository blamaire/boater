<?php

use App\Models\AuditEntry;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\Role;
use App\Models\RoleAssignment;
use Illuminate\Support\Carbon;

it('logs an audit entry when a role assignment is created', function () {
    $person = Person::create(['first_name' => 'Test', 'last_name' => 'Persoon']);
    $role = Role::create(['name' => 'TestRol']);

    RoleAssignment::create([
        'person_id' => $person->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    expect(AuditEntry::where('action', 'role.assigned')->count())->toBe(1);
});

it('logs an audit entry when a role assignment status changes', function () {
    $person = Person::create(['first_name' => 'Test', 'last_name' => 'Persoon']);
    $role = Role::create(['name' => 'TestRol']);
    $assignment = RoleAssignment::create([
        'person_id' => $person->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    $assignment->update(['status' => 'deactivated', 'deactivated_at' => Carbon::now()]);

    $entry = AuditEntry::where('action', 'role.status_changed')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->before)->toBe(['status' => 'active'])
        ->and($entry->after)->toBe(['status' => 'deactivated']);
});

it('does not log when an unrelated field changes on a role assignment', function () {
    $person = Person::create(['first_name' => 'Test', 'last_name' => 'Persoon']);
    $role = Role::create(['name' => 'TestRol']);
    $assignment = RoleAssignment::create([
        'person_id' => $person->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    \Illuminate\Support\Facades\DB::table('audit_entries')->truncate();

    $assignment->update(['ends_at' => Carbon::now()->addYear()]);

    expect(AuditEntry::where('action', 'role.status_changed')->count())->toBe(0);
});

it('logs an audit entry when a person permission is granted', function () {
    $person = Person::create(['first_name' => 'Test', 'last_name' => 'Persoon']);
    $permission = Permission::create([
        'key' => 'impersonate',
        'module' => 'support',
        'action' => 'impersonate',
    ]);

    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => $permission->id,
        'status' => 'active',
    ]);

    expect(AuditEntry::where('action', 'person_permission.granted')->count())->toBe(1);
});

it('logs an audit entry when a person permission is revoked', function () {
    $person = Person::create(['first_name' => 'Test', 'last_name' => 'Persoon']);
    $permission = Permission::create([
        'key' => 'impersonate',
        'module' => 'support',
        'action' => 'impersonate',
    ]);
    $personPermission = PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => $permission->id,
        'status' => 'active',
    ]);

    $personPermission->update(['status' => 'revoked']);

    expect(AuditEntry::where('action', 'person_permission.status_changed')->count())->toBe(1);
});
