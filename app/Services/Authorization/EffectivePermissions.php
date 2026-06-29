<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EffectivePermissions
{
    /**
     * Resolve the union of permission keys that apply to a person right now.
     *
     * Bron: §26.4 — unie van (a) rolpermissies via actieve roltoewijzingen,
     * (b) individueel toegekende person_permissions en (c) lidmaatschapsprivileges.
     * (c) is een stub tot module Lidmaatschap er is.
     *
     * @return Collection<int, string>
     */
    public function for(Person $person): Collection
    {
        $now = Carbon::now();

        $rolePermissionKeys = Permission::query()
            ->whereIn('id', function ($query) use ($person, $now) {
                $query->select('role_permissions.permission_id')
                    ->from('role_permissions')
                    ->join('role_assignments', 'role_assignments.role_id', '=', 'role_permissions.role_id')
                    ->where('role_assignments.person_id', $person->id)
                    ->where('role_assignments.status', 'active')
                    ->whereNull('role_assignments.deactivated_at')
                    ->where(function ($q) use ($now) {
                        $q->whereNull('role_assignments.ends_at')
                            ->orWhere('role_assignments.ends_at', '>', $now);
                    });
            })
            ->pluck('key');

        $directPermissionKeys = Permission::query()
            ->whereIn('id', function ($query) use ($person, $now) {
                $query->select('permission_id')
                    ->from('person_permissions')
                    ->where('person_id', $person->id)
                    ->where('status', 'active')
                    ->where(function ($q) use ($now) {
                        $q->whereNull('ends_at')
                            ->orWhere('ends_at', '>', $now);
                    });
            })
            ->pluck('key');

        return $rolePermissionKeys->concat($directPermissionKeys)->unique()->values();
    }

    public function has(Person $person, string $permissionKey): bool
    {
        return $this->for($person)->contains($permissionKey);
    }
}
