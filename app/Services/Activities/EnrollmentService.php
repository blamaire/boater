<?php

namespace App\Services\Activities;

use App\Enums\ActivityStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\Person;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verwerkt inschrijvingen op een activiteit met capaciteit-bewaking en
 * wachtlijst-promotie (§17.4).
 */
class EnrollmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Schrijft `person` in op `activity`, eventueel op wachtlijst.
     *
     * @param  Person  $person  De begunstigde (degene die daadwerkelijk meedoet).
     * @param  Person|null  $requestedBy  Wie de inschrijving doet (kan gelijk zijn aan $person).
     *
     * @throws RuntimeException Bij een gesloten of afgelaste activiteit, of dubbele actieve inschrijving.
     */
    public function enroll(Activity $activity, Person $person, ?Person $requestedBy = null): Enrollment
    {
        if ($activity->status !== ActivityStatus::Published) {
            throw new RuntimeException('Deze activiteit staat niet open voor inschrijving.');
        }

        return DB::transaction(function () use ($activity, $person, $requestedBy): Enrollment {
            $existing = Enrollment::query()
                ->where('activity_id', $activity->id)
                ->where('person_id', $person->id)
                ->first();

            if ($existing !== null && $existing->status !== EnrollmentStatus::Cancelled) {
                throw new RuntimeException('Er is al een actieve inschrijving voor deze persoon.');
            }

            $status = $activity->hasFreeSpot() ? EnrollmentStatus::Enrolled : EnrollmentStatus::Waitlist;

            if ($existing !== null) {
                // Eerder afgemeld → reactiveren met nieuwe status.
                $existing->update([
                    'status' => $status,
                    'requested_by_person_id' => $requestedBy?->id,
                    'enrolled_at' => Carbon::now(),
                ]);
                $enrollment = $existing;
            } else {
                $enrollment = Enrollment::query()->create([
                    'activity_id' => $activity->id,
                    'person_id' => $person->id,
                    'requested_by_person_id' => $requestedBy?->id,
                    'status' => $status,
                    'enrolled_at' => Carbon::now(),
                ]);
            }

            $this->audit->log('activity.enrolled', $activity, after: [
                'person_id' => $person->id,
                'enrollment_id' => $enrollment->id,
                'status' => $status->value,
                'requested_by' => $requestedBy?->id,
            ]);

            return $enrollment;
        });
    }

    /**
     * Meldt een inschrijving af. Als er een wachtlijst was, promoveert de
     * eerste wachtende automatisch naar 'aangemeld'.
     */
    public function cancel(Enrollment $enrollment, ?Person $actor = null): void
    {
        DB::transaction(function () use ($enrollment, $actor): void {
            $before = ['status' => $enrollment->status->value];

            $enrollment->update([
                'status' => EnrollmentStatus::Cancelled,
            ]);

            $this->audit->log('activity.enrollment_cancelled', $enrollment->activity, before: $before, after: [
                'enrollment_id' => $enrollment->id,
                'person_id' => $enrollment->person_id,
                'actor_id' => $actor?->id,
            ]);

            $this->promoteWaitlist($enrollment->activity()->firstOrFail());
        });
    }

    /**
     * Vult vrijgekomen plekken op door wachtlijst-inschrijvingen te promoveren
     * naar 'aangemeld'. Volgorde: eerst ingeschreven, eerst aan de beurt.
     */
    public function promoteWaitlist(Activity $activity): int
    {
        if ($activity->capacity === null) {
            return 0;
        }

        $promoted = 0;
        while ($activity->hasFreeSpot()) {
            /** @var Enrollment|null $next */
            $next = Enrollment::query()
                ->where('activity_id', $activity->id)
                ->where('status', EnrollmentStatus::Waitlist->value)
                ->orderBy('enrolled_at')
                ->first();

            if ($next === null) {
                break;
            }

            $next->update(['status' => EnrollmentStatus::Enrolled]);
            $this->audit->log('activity.waitlist_promoted', $activity, after: [
                'enrollment_id' => $next->id,
                'person_id' => $next->person_id,
            ]);
            $promoted++;
        }

        return $promoted;
    }
}
