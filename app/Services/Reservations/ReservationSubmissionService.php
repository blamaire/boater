<?php

namespace App\Services\Reservations;

use App\Enums\ChangeType;
use App\Enums\MembershipStatus;
use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationStatus;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Services\Proposals\Handlers\ReservationProposalHandler;
use App\Services\Proposals\ProposalEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Neemt een reserveringsaanvraag aan uit de portaal-flow, resolveert
 * "beschikbaar van categorie" naar een concreet object, evalueert de
 * drempels (§18.4) en routeert:
 *
 *   - clean én eigen (of gemachtigd) → direct via `ReservationService::reserve()`;
 *   - drempeloverschrijding OF aanvraag-voor-een-ander-zonder-machtiging
 *     → indienen als Proposal via de goedkeuringsmotor. Apply-time
 *     hervalidatie zit in `ReservationProposalHandler`.
 */
class ReservationSubmissionService
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ReservationRuleEvaluator $evaluator,
        private readonly ProposalEngine $proposals,
    ) {}

    /**
     * @return SubmissionOutcome bevat óf een direct-aangemaakte reservering,
     *                           óf het ingediende proposal met de reden.
     */
    public function submit(
        ?ReservableObject $object,
        ?ObjectCategory $category,
        Person $beneficiary,
        Carbon $startsAt,
        Carbon $endsAt,
        Person $requester,
        ?string $note = null,
    ): SubmissionOutcome {
        if ($startsAt->greaterThanOrEqualTo($endsAt)) {
            throw new RuntimeException('De eindtijd moet ná de starttijd liggen.');
        }

        $resolvedObject = $object ?? $this->resolveFromCategory(
            $category ?? throw new RuntimeException('Kies een object of een categorie.'),
            $startsAt,
            $endsAt,
        );

        if ($resolvedObject->category->requires_boat_right && ! $this->hasBoatRight($beneficiary)) {
            // Bootrecht is een harde invariant (§18.4) — niet iets waar de
            // goedkeuringsmotor over gaat oordelen. Meteen weigeren.
            throw new RuntimeException(
                'Voor deze categorie is bootrecht vereist. '
                ."{$beneficiary->first_name} heeft geen lopend lidmaatschap dat bootrecht geeft."
            );
        }

        $needsReviewForOther = $beneficiary->id !== $requester->id
            && ! $this->mayReserveFor($requester, $beneficiary);

        $violations = $this->evaluator->evaluate($resolvedObject, $beneficiary, $startsAt, $endsAt);

        if ($needsReviewForOther || $violations->isNotEmpty()) {
            $proposal = $this->submitProposal(
                $resolvedObject,
                $beneficiary,
                $startsAt,
                $endsAt,
                $requester,
                $note,
                $violations,
                $needsReviewForOther,
            );

            return SubmissionOutcome::forProposal($proposal, $needsReviewForOther, $violations);
        }

        $reservation = $this->reservations->reserve(
            $resolvedObject,
            $beneficiary,
            $startsAt,
            $endsAt,
            $requester,
            $note,
        );

        return SubmissionOutcome::forDirect($reservation);
    }

    /**
     * §18.4 "beschikbaar van categorie" — systeem wijst het object toe.
     * Strategie in v2: eerste vrije object binnen de categorie (én
     * afstammelingen) op `sort_order`, dat niet overlapt met een
     * bevestigde reservering in het gevraagde tijdvak.
     */
    public function resolveFromCategory(ObjectCategory $category, Carbon $startsAt, Carbon $endsAt): ReservableObject
    {
        $categoryIds = $this->descendantIds($category);

        $candidates = ReservableObject::query()
            ->whereIn('object_category_id', $categoryIds)
            ->where('status', ReservableObjectStatus::Available->value)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($candidates as $candidate) {
            $overlap = Reservation::query()
                ->where('reservable_object_id', $candidate->id)
                ->where('status', ReservationStatus::Confirmed->value)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();
            if (! $overlap) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            "Geen beschikbaar object in [{$category->name}] voor het gekozen tijdvak."
        );
    }

    /**
     * @param  Collection<int, RuleViolation>  $violations
     */
    private function submitProposal(
        ReservableObject $object,
        Person $beneficiary,
        Carbon $startsAt,
        Carbon $endsAt,
        Person $requester,
        ?string $note,
        Collection $violations,
        bool $needsReviewForOther,
    ): Proposal {
        $payload = [
            'reservable_object_id' => $object->id,
            'person_id' => $beneficiary->id,
            'requested_by_person_id' => $requester->id,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'note' => $note,
            'submission_reason' => match (true) {
                $needsReviewForOther && $violations->isNotEmpty() => 'other_and_violations',
                $needsReviewForOther => 'other',
                default => 'violations',
            },
            'violations' => $violations->map(fn (RuleViolation $v) => [
                'rule_id' => $v->rule->id,
                'rule_name' => $v->rule->name,
                'message' => $v->message,
            ])->all(),
        ];

        return $this->proposals->submit(
            subjectType: ReservationProposalHandler::SUBJECT_TYPE,
            changeType: ChangeType::Create,
            payload: $payload,
            proposer: $requester,
        );
    }

    private function mayReserveFor(Person $actor, Person $target): bool
    {
        return $actor->relations()
            ->where('related_person_id', $target->id)
            ->whereIn('type', ['ouder_van', 'verzorger_van'])
            ->exists();
    }

    private function hasBoatRight(Person $person): bool
    {
        return $person->memberships()
            ->where('status', MembershipStatus::Active->value)
            ->where(function ($q): void {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->whereHas('type', fn ($q) => $q->where('allows_boat_use', true))
            ->exists();
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(ObjectCategory $category): array
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
