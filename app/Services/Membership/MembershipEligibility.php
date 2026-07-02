<?php

namespace App\Services\Membership;

use App\Enums\MembershipStatus;
use App\Models\Household;
use App\Models\MembershipType;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Beoordeelt per lidmaatschapsvorm of een aanvrager 'm mag kiezen (§7 —
 * overwegend signalerend). Niet-passende vormen blijven zichtbaar, maar met
 * een reden; het formulier eist dan een uitleg. Zo kan een aanvrager altijd
 * doorzetten en de beoordelaar heeft context.
 */
class MembershipEligibility
{
    /**
     * @return Collection<int, MembershipTypeEligibility>
     */
    public function evaluate(?Carbon $dateOfBirth, ?string $postalCode = null, ?string $houseNumber = null): Collection
    {
        $age = $dateOfBirth?->age;
        $household = $this->findHousehold($postalCode, $houseNumber);

        return MembershipType::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MembershipType $type) => $this->evaluateType($type, $age, $household));
    }

    private function evaluateType(MembershipType $type, ?int $age, ?Household $household): MembershipTypeEligibility
    {
        $ageReason = $this->ageReason($type, $age);
        if ($ageReason !== null) {
            return new MembershipTypeEligibility($type, false, $ageReason);
        }

        if ($type->is_partner_type) {
            $partnerReason = $this->partnerReason($type, $household);
            if ($partnerReason !== null) {
                return new MembershipTypeEligibility($type, false, $partnerReason);
            }
        }

        return new MembershipTypeEligibility($type, true, null);
    }

    private function ageReason(MembershipType $type, ?int $age): ?string
    {
        if ($age === null) {
            return null;
        }

        if ($type->min_age !== null && $type->max_age !== null && ($age < $type->min_age || $age > $type->max_age)) {
            return sprintf('Alleen voor leeftijd %d t/m %d jaar (je bent %d).', $type->min_age, $type->max_age, $age);
        }

        if ($type->min_age !== null && $age < $type->min_age) {
            return sprintf('Alleen vanaf %d jaar (je bent %d).', $type->min_age, $age);
        }

        if ($type->max_age !== null && $age > $type->max_age) {
            return sprintf('Alleen tot en met %d jaar (je bent %d).', $type->max_age, $age);
        }

        return null;
    }

    private function partnerReason(MembershipType $type, ?Household $household): ?string
    {
        if ($household === null) {
            return 'Alleen beschikbaar als je partner al op dit adres lid is; vul postcode en huisnummer in en licht toe wie je partner is.';
        }

        $basisKey = $type->derives_from_key;
        if ($basisKey === null) {
            return null;
        }

        $partnerExists = Person::query()
            ->where('household_id', $household->id)
            ->whereHas('memberships', function ($q) use ($basisKey) {
                $q->where('status', MembershipStatus::Active->value)
                    ->whereHas('type', fn ($t) => $t->where('key', $basisKey));
            })
            ->exists();

        if (! $partnerExists) {
            return sprintf(
                'Geen actief %s-lid op dit adres gevonden; licht toe wie je partner is.',
                strtoupper($basisKey)
            );
        }

        return null;
    }

    private function findHousehold(?string $postalCode, ?string $houseNumber): ?Household
    {
        if ($postalCode === null || $houseNumber === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $postalCode) ?? '');
        if ($normalized === '' || trim($houseNumber) === '') {
            return null;
        }

        return Household::query()
            ->where('postal_code', $normalized)
            ->where('house_number', trim($houseNumber))
            ->first();
    }
}
