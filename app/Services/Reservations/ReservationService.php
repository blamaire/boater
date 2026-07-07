<?php

namespace App\Services\Reservations;

use App\Enums\MembershipStatus;
use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationStatus;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reserveringsservice v1 (§18). Handelt aanmaken en annuleren af en dwingt
 * de twee harde invarianten uit §18.4 af:
 *
 * 1. Geen dubbelboeking op hetzelfde object (overlappende bevestigde
 *    reserveringen zijn niet toegestaan).
 * 2. Bootrecht: als de categorie van het object `requires_boat_right` is,
 *    moet de begunstigde een lopende `Membership` hebben waarvan het
 *    `MembershipType.allows_boat_use=true` is.
 *
 * Reserveringsregels/drempels en de goedkeuringsmotor komen in v2.
 */
class ReservationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function reserve(
        ReservableObject $object,
        Person $beneficiary,
        Carbon $startsAt,
        Carbon $endsAt,
        ?Person $requestedBy = null,
        ?string $note = null,
    ): Reservation {
        if ($startsAt->greaterThanOrEqualTo($endsAt)) {
            throw new RuntimeException('De eindtijd moet ná de starttijd liggen.');
        }

        if ($object->status !== ReservableObjectStatus::Available) {
            throw new RuntimeException("Object [{$object->name}] is niet beschikbaar voor reservering.");
        }

        if ($object->category->requires_boat_right && ! $this->hasBoatRight($beneficiary)) {
            throw new RuntimeException(
                'Voor deze categorie is bootrecht vereist. '
                ."{$beneficiary->first_name} heeft geen lopend lidmaatschap dat bootrecht geeft."
            );
        }

        return DB::transaction(function () use ($object, $beneficiary, $startsAt, $endsAt, $requestedBy, $note): Reservation {
            $this->assertNoOverlap($object, $startsAt, $endsAt);

            $reservation = Reservation::query()->create([
                'reservable_object_id' => $object->id,
                'person_id' => $beneficiary->id,
                'requested_by_person_id' => $requestedBy !== null ? $requestedBy->id : $beneficiary->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => ReservationStatus::Confirmed,
                'note' => $note,
            ]);

            $this->audit->log('reservation.created', $reservation, after: [
                'object_id' => $object->id,
                'person_id' => $beneficiary->id,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'requested_by' => $requestedBy?->id,
            ]);

            return $reservation;
        });
    }

    public function cancel(Reservation $reservation, ?Person $actor = null): void
    {
        if ($reservation->status === ReservationStatus::Cancelled) {
            return;
        }

        DB::transaction(function () use ($reservation, $actor): void {
            $before = ['status' => $reservation->status->value];

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
            ]);

            $this->audit->log('reservation.cancelled', $reservation, before: $before, after: [
                'reservation_id' => $reservation->id,
                'actor_id' => $actor?->id,
            ]);
        });
    }

    /**
     * Invariant 1: geen dubbelboeking. Overlap-definitie: twee tijdvakken
     * overlappen als het ene start vóór het ander eindigt EN eindigt na
     * het ander start. Gelijke start-/eindtijden (aansluitend) tellen niet
     * als overlap.
     */
    private function assertNoOverlap(ReservableObject $object, Carbon $startsAt, Carbon $endsAt): void
    {
        $conflict = Reservation::query()
            ->where('reservable_object_id', $object->id)
            ->where('status', ReservationStatus::Confirmed->value)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($conflict) {
            throw new RuntimeException(
                "Object [{$object->name}] is al gereserveerd in het gekozen tijdvak."
            );
        }
    }

    /**
     * Invariant 2: bootrecht op basis van huidig lidmaatschap.
     */
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
}
