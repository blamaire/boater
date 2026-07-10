<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EffectivePermissions
{
    /**
     * De permissies die op dit moment gelden voor deze persoon.
     *
     * Bron: §26.4 — unie van (a) rolpermissies via actieve roltoewijzingen,
     * (b) individueel toegekende person_permissions en (c) permissies die
     * automatisch aan actieve leden worden verleend.
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

        $membershipPermissionKeys = $person->hasActiveMembership()
            ? collect(self::MEMBERSHIP_GRANTED_KEYS)
            : collect();

        $effective = $rolePermissionKeys
            ->concat($directPermissionKeys)
            ->concat($membershipPermissionKeys)
            ->unique()
            ->values();

        // Impliceerde permissies: als iemand de zwaardere permissie heeft,
        // krijgt hij de lichtere er automatisch bij. Voorkomt dat Redacteurs
        // en Beheerders per ongeluk `pages.propose` moeten missen om leden-
        // routes te bereiken.
        foreach (self::IMPLICIT_GRANTS as $heavy => $light) {
            if ($effective->contains($heavy) && ! $effective->contains($light)) {
                $effective->push($light);
            }
        }

        return $effective->unique()->values();
    }

    /**
     * Permissies die iedereen met een actief lidmaatschap automatisch krijgt.
     * Voor nu handmatig gecureerd; wordt vervangen door
     * lidmaatschapstype-specifieke rechten (§6-7) wanneer die module er is.
     *
     * @var list<string>
     */
    private const array MEMBERSHIP_GRANTED_KEYS = [
        'pages.propose',
    ];

    /**
     * Zwaardere permissie → verleent automatisch de lichtere. Handig voor
     * combinaties zoals "wie mag publiceren mag ook indienen mag ook voorstellen".
     *
     * @var array<string, string>
     */
    private const array IMPLICIT_GRANTS = [
        'pages.update' => 'pages.propose',
    ];

    public function has(Person $person, string $permissionKey): bool
    {
        return $this->for($person)->contains($permissionKey);
    }
}
