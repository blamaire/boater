<?php

namespace App\Livewire\Admin;

use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Gecombineerde beheer-UI voor rollen én directe permissies per persoon.
 * Rollen bovenaan (toewijzen en deactiveren), directe rechten daaronder.
 * Rechten via een rol kunnen hier niet worden weggehaald — die pas je aan
 * bij de rol zelf.
 */
#[Layout('layouts.app', ['header' => 'Rollen en rechten per persoon'])]
class PersonPermissionBeheer extends Component
{
    public Person $person;

    public ?int $newRoleId = null;

    public string $newRoleReason = '';

    public ?string $statusMessage = null;

    public function mount(Person $person): void
    {
        $this->person = $person;
    }

    public function assignRole(AuditLogger $audit): void
    {
        $this->validate([
            'newRoleId' => ['required', 'integer', 'exists:roles,id'],
            'newRoleReason' => ['nullable', 'string', 'max:500'],
        ]);

        $role = Role::query()->findOrFail($this->newRoleId);
        $assignerPersonId = auth()->user()?->person?->id;

        RoleAssignment::query()->create([
            'person_id' => $this->person->id,
            'role_id' => $role->id,
            'status' => 'active',
            'assigned_by' => $assignerPersonId,
            'assigned_at' => Carbon::now(),
            'reason' => trim($this->newRoleReason) !== '' ? trim($this->newRoleReason) : null,
        ]);

        $audit->log('person.role_assigned', $this->person, after: [
            'role_id' => $role->id,
            'role_name' => $role->name,
        ]);

        $this->reset(['newRoleId', 'newRoleReason']);
        $this->statusMessage = "Rol [{$role->name}] toegewezen.";
    }

    public function deactivateAssignment(int $assignmentId, AuditLogger $audit): void
    {
        $assignment = RoleAssignment::query()
            ->where('person_id', $this->person->id)
            ->findOrFail($assignmentId);

        $roleName = $assignment->role->name;

        $assignment->update([
            'status' => 'deactivated',
            'deactivated_at' => Carbon::now(),
        ]);

        $audit->log('person.role_deactivated', $this->person, before: [
            'role_id' => $assignment->role_id,
            'role_name' => $roleName,
        ]);

        $this->statusMessage = "Rol [{$roleName}] gedeactiveerd.";
    }

    public function grant(int $permissionId, AuditLogger $audit): void
    {
        $permission = Permission::query()->findOrFail($permissionId);

        DB::transaction(function () use ($permission, $audit): void {
            $created = PersonPermission::query()->create([
                'person_id' => $this->person->id,
                'permission_id' => $permission->id,
                'status' => 'active',
            ]);

            $audit->log('person.permission_granted', $this->person, after: [
                'permission' => $permission->key,
                'person_permission_id' => $created->id,
            ]);
        });

        $this->statusMessage = "Recht [{$permission->key}] direct toegekend.";
    }

    public function revoke(int $personPermissionId, AuditLogger $audit): void
    {
        $direct = PersonPermission::query()
            ->where('person_id', $this->person->id)
            ->findOrFail($personPermissionId);

        $permissionKey = $direct->permission->key;

        DB::transaction(function () use ($direct, $permissionKey, $audit): void {
            $audit->log('person.permission_revoked', $this->person, before: [
                'permission' => $permissionKey,
                'person_permission_id' => $direct->id,
            ]);
            $direct->delete();
        });

        $this->statusMessage = "Directe toewijzing van [{$permissionKey}] verwijderd.";
    }

    public function render(): View
    {
        $allAssignments = RoleAssignment::query()
            ->with(['role', 'assignedBy'])
            ->where('person_id', $this->person->id)
            ->orderByDesc('assigned_at')
            ->get();

        $activeAssignments = $allAssignments->filter(fn (RoleAssignment $a) => $a->status === 'active');

        $allRoles = Role::query()
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        $activeRoleIds = $activeAssignments->pluck('role_id')->all();
        $availableRoles = $allRoles->reject(fn ($r) => in_array($r->id, $activeRoleIds, true))->values();

        $rolePermissions = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('role_assignments', 'role_assignments.role_id', '=', 'role_permissions.role_id')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->where('role_assignments.person_id', $this->person->id)
            ->where('role_assignments.status', 'active')
            ->whereNull('role_assignments.deactivated_at')
            ->where(function ($q): void {
                $q->whereNull('role_assignments.ends_at')
                    ->orWhere('role_assignments.ends_at', '>', now());
            })
            ->select('permissions.key as permission_key', 'roles.name as role_name')
            ->get()
            ->groupBy('permission_key')
            ->map(fn ($rows) => $rows->pluck('role_name')->unique()->values()->all());

        $directPermissions = PersonPermission::query()
            ->with('permission')
            ->where('person_id', $this->person->id)
            ->where('status', 'active')
            ->get()
            ->keyBy(fn (PersonPermission $pp) => $pp->permission->key);

        $allPermissions = Permission::query()
            ->orderBy('module')
            ->orderBy('action')
            ->get()
            ->groupBy('module');

        return view('livewire.admin.person-permission-beheer', [
            'person' => $this->person,
            'activeAssignments' => $activeAssignments,
            'allAssignments' => $allAssignments,
            'availableRoles' => $availableRoles,
            'permissionsByModule' => $allPermissions,
            'rolePermissions' => $rolePermissions,
            'directPermissions' => $directPermissions,
        ]);
    }
}
