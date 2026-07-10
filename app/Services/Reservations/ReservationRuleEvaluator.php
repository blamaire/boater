<?php

namespace App\Services\Reservations;

use App\Enums\ReservationConstraintType;
use App\Enums\ReservationStatus;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Models\ReservationRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * §18.4 — evalueert of een voorgenomen reservering één of meer
 * `reservation_rules` overschrijdt. Regels zijn additief; erven van
 * bovenliggende categorieën (§18.4 categoriehiërarchie). Overschrijding
 * blokkeert de aanvraag niet — hij gaat dan via de goedkeuringsmotor.
 */
class ReservationRuleEvaluator
{
    /**
     * @return Collection<int, RuleViolation> alle overtreden regels; leeg = binnen beleid
     */
    public function evaluate(
        ReservableObject $object,
        Person $beneficiary,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $excludeReservationId = null,
    ): Collection {
        $violations = collect();
        $rules = $this->applicableRules($object->category);
        $durationMinutes = (int) $startsAt->diffInMinutes($endsAt);

        foreach ($rules as $rule) {
            $violation = match ($rule->constraint_type) {
                ReservationConstraintType::Duration => $this->checkDuration($rule, $durationMinutes),
                ReservationConstraintType::Simultaneous => $this->checkSimultaneous($rule, $object, $beneficiary, $startsAt, $endsAt, $excludeReservationId),
                ReservationConstraintType::PerDay => $this->checkPerDay($rule, $object, $beneficiary, $startsAt, $excludeReservationId),
            };

            if ($violation !== null) {
                $violations->push($violation);
            }
        }

        return $violations;
    }

    /**
     * Verzamelt regels voor deze categorie én al haar bovenliggende
     * (parent-chain). Zo werkt "regel op categorie geldt ook op
     * subcategorieën" precies zoals §18.4 beschrijft.
     *
     * @return Collection<int, ReservationRule>
     */
    private function applicableRules(ObjectCategory $category): Collection
    {
        $ids = collect([$category->id, ...collect($category->ancestors())->pluck('id')->all()])
            ->unique()
            ->all();

        return ReservationRule::query()->whereIn('object_category_id', $ids)->get();
    }

    private function checkDuration(ReservationRule $rule, int $durationMinutes): ?RuleViolation
    {
        if ($durationMinutes > $rule->limit_value) {
            return new RuleViolation($rule, "Aanvraag duurt {$durationMinutes} minuten; regel staat maximaal {$rule->limit_value} minuten toe.");
        }

        return null;
    }

    private function checkSimultaneous(
        ReservationRule $rule,
        ReservableObject $object,
        Person $beneficiary,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $excludeReservationId,
    ): ?RuleViolation {
        $categoryIds = $this->descendantCategoryIds($rule->category);

        $query = Reservation::query()
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereHas('object', fn ($q) => $q->whereIn('object_category_id', $categoryIds))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($rule->per_person) {
            $query->where('person_id', $beneficiary->id);
        }

        if ($excludeReservationId !== null) {
            $query->where('id', '!=', $excludeReservationId);
        }

        $existingCount = $query->count();
        // +1 voor de voorgenomen reservering zelf.
        if ($existingCount + 1 > $rule->limit_value) {
            $scope = $rule->per_person ? "voor {$beneficiary->first_name}" : 'in totaal';
            $count = $existingCount + 1;

            return new RuleViolation($rule, "{$count} gelijktijdige reserveringen op categorie [{$rule->category->name}] {$scope}; regel staat er {$rule->limit_value} toe.");
        }

        return null;
    }

    private function checkPerDay(
        ReservationRule $rule,
        ReservableObject $object,
        Person $beneficiary,
        Carbon $startsAt,
        ?int $excludeReservationId,
    ): ?RuleViolation {
        $categoryIds = $this->descendantCategoryIds($rule->category);
        $dayStart = $startsAt->copy()->startOfDay();
        $dayEnd = $startsAt->copy()->endOfDay();

        $query = Reservation::query()
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereHas('object', fn ($q) => $q->whereIn('object_category_id', $categoryIds))
            ->whereBetween('starts_at', [$dayStart, $dayEnd]);

        if ($rule->per_person) {
            $query->where('person_id', $beneficiary->id);
        }

        if ($excludeReservationId !== null) {
            $query->where('id', '!=', $excludeReservationId);
        }

        $existingCount = $query->count();
        if ($existingCount + 1 > $rule->limit_value) {
            $scope = $rule->per_person ? "voor {$beneficiary->first_name}" : 'in totaal';
            $count = $existingCount + 1;

            return new RuleViolation($rule, "{$count} reserveringen op {$startsAt->format('d-m-Y')} in categorie [{$rule->category->name}] {$scope}; regel staat er {$rule->limit_value} toe.");
        }

        return null;
    }

    /**
     * Verzamelt de categorie zelf plus al haar afstammelingen zodat
     * een regel op "Boten" ook telt voor "C1", "C2", …
     *
     * @return array<int, int>
     */
    private function descendantCategoryIds(ObjectCategory $category): array
    {
        $collected = [$category->id];
        $stack = [$category->id];

        while ($stack !== []) {
            $currentId = array_pop($stack);
            $children = ObjectCategory::query()->where('parent_id', $currentId)->pluck('id')->all();
            foreach ($children as $childId) {
                if (! in_array($childId, $collected, true)) {
                    $collected[] = $childId;
                    $stack[] = $childId;
                }
            }
        }

        return $collected;
    }
}
