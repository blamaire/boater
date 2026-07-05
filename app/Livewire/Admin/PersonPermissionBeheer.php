<?php

namespace App\Livewire\Admin;

use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer directe permissies per persoon (`person_permissions`). Toont
 * per permissie of hij via een rol komt of direct is toegekend, en laat
 * de beheerder directe toewijzingen aan- en uitzetten. Rechten via rollen
 * kunnen hier niet worden weggehaald — die pas je aan bij de rol.
 */
#[Layout('layouts.app', ['header' => 'Rechten per persoon'])]
class PersonPermissionBeheer extends Component
{
    public Person $person;

    public ?string $statusMessage = null;

    public function mount(Person $person): void
    {
        $this->person = $person;
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
            'permissionsByModule' => $allPermissions,
            'rolePermissions' => $rolePermissions,
            'directPermissions' => $directPermissions,
        ]);
    }
}
