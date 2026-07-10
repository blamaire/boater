<?php

namespace App\Services\Proposals\Handlers;

use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationStatus;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Services\Proposals\Contracts\ProposalHandler;
use App\Services\Proposals\Exceptions\ProposalConflictException;
use App\Services\Reservations\ReservationService;
use Illuminate\Support\Carbon;

/**
 * Effectueert een goedgekeurde reserveringsaanvraag (§18).
 *
 * subject_type: 'reservation.create'
 * payload: [
 *   'reservable_object_id' => int,
 *   'person_id'            => int (begunstigde),
 *   'requested_by_person_id' => int (indiener),
 *   'starts_at'            => string (ISO),
 *   'ends_at'              => string (ISO),
 *   'note'                 => ?string,
 *   'submission_reason'    => 'violations' | 'other' | 'other_and_violations',
 *   'violations'           => list<array{rule_id,rule_name,message}> // snapshot
 * ]
 *
 * Apply-time hervalidatie (§20.4): object bestaat nog en is beschikbaar,
 * en er is geen conflicterende bevestigde reservering ontstaan tussen
 * indienen en goedkeuren.
 */
class ReservationProposalHandler implements ProposalHandler
{
    public const string SUBJECT_TYPE = 'reservation.create';

    public function __construct(private readonly ReservationService $reservations) {}

    public function revalidate(Proposal $proposal): void
    {
        [$object, , $startsAt, $endsAt] = $this->extract($proposal);

        if ($object->status !== ReservableObjectStatus::Available) {
            throw new ProposalConflictException(
                "Object [{$object->name}] is intussen niet meer beschikbaar."
            );
        }

        $conflict = Reservation::query()
            ->where('reservable_object_id', $object->id)
            ->where('status', ReservationStatus::Confirmed->value)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($conflict) {
            throw new ProposalConflictException(
                "Object [{$object->name}] is intussen al gereserveerd in het gekozen tijdvak."
            );
        }
    }

    public function apply(Proposal $proposal): void
    {
        [$object, $beneficiary, $startsAt, $endsAt, $requester, $note] = $this->extract($proposal);

        $this->reservations->reserve(
            $object,
            $beneficiary,
            $startsAt,
            $endsAt,
            $requester,
            $note,
        );
    }

    /**
     * @return array{0: ReservableObject, 1: Person, 2: Carbon, 3: Carbon, 4: ?Person, 5: ?string}
     */
    private function extract(Proposal $proposal): array
    {
        $payload = $proposal->payload ?? [];

        $object = ReservableObject::query()->find($payload['reservable_object_id'] ?? null);
        if ($object === null) {
            throw new ProposalConflictException('Het object voor deze reservering bestaat niet meer.');
        }

        $beneficiary = Person::query()->find($payload['person_id'] ?? null);
        if ($beneficiary === null) {
            throw new ProposalConflictException('De begunstigde persoon bestaat niet meer.');
        }

        $requester = isset($payload['requested_by_person_id'])
            ? Person::query()->find($payload['requested_by_person_id'])
            : null;

        $note = isset($payload['note']) && is_string($payload['note']) ? $payload['note'] : null;

        return [
            $object,
            $beneficiary,
            Carbon::parse($payload['starts_at'] ?? ''),
            Carbon::parse($payload['ends_at'] ?? ''),
            $requester,
            $note,
        ];
    }
}
