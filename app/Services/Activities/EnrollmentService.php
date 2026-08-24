<?php

namespace App\Services\Activities;

use App\Enums\ActivityStatus;
use App\Enums\EnrollmentLevel;
use App\Enums\EnrollmentStatus;
use App\Models\Activity;
use App\Models\ActivitySeries;
use App\Models\Enrollment;
use App\Models\Person;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verwerkt inschrijvingen op een activiteit met capaciteit-bewaking en
 * wachtlijst-promotie (§17.4). Capaciteit/wachtlijst wordt altijd per
 * voorkomen bewaakt, ook bij een serie-brede inschrijving (`enrollSeries`) —
 * die schrijft per voorkomen een losse `Enrollment` in, samen getagd met
 * `series_id`/`level` zodat ze als één geheel herkenbaar en af te melden zijn.
 */
class EnrollmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ActivityManagerNotifier $notifier,
    ) {}

    /**
     * Schrijft `person` in op `activity`, eventueel op wachtlijst.
     *
     * @param  Person  $person  De begunstigde (degene die daadwerkelijk meedoet).
     * @param  Person|null  $requestedBy  Wie de inschrijving doet (kan gelijk zijn aan $person).
     * @param  ActivitySeries|null  $series  Meegegeven bij een serie-brede inschrijving (intern gebruik, zie enrollSeries()).
     *
     * @throws RuntimeException Bij een gesloten of afgelaste activiteit, buiten leeftijd, verkeerd inschrijfniveau, of dubbele actieve inschrijving.
     */
    public function enroll(
        Activity $activity,
        Person $person,
        ?Person $requestedBy = null,
        ?ActivitySeries $series = null,
        EnrollmentLevel $level = EnrollmentLevel::Bundel,
    ): Enrollment {
        if ($activity->status !== ActivityStatus::Published) {
            throw new RuntimeException('Deze activiteit staat niet open voor inschrijving.');
        }

        if ($level === EnrollmentLevel::Bundel && $activity->series !== null && ! $activity->series->enrollment_level->allowsPerVoorkomen()) {
            throw new RuntimeException('Voor deze reeks kun je alleen voor de hele reeks inschrijven.');
        }

        if (! $activity->isAgeEligible($person)) {
            throw new RuntimeException('Deze persoon voldoet niet aan de leeftijdseis van deze activiteit.');
        }

        $enrollment = DB::transaction(function () use ($activity, $person, $requestedBy, $series, $level): Enrollment {
            $existing = Enrollment::query()
                ->where('activity_id', $activity->id)
                ->where('person_id', $person->id)
                ->first();

            if ($existing !== null && $existing->status !== EnrollmentStatus::Cancelled) {
                throw new RuntimeException('Er is al een actieve inschrijving voor deze persoon.');
            }

            $status = $activity->hasFreeSpot() ? EnrollmentStatus::Enrolled : EnrollmentStatus::Waitlist;

            $attributes = [
                'status' => $status,
                'requested_by_person_id' => $requestedBy?->id,
                'series_id' => $series?->id,
                'level' => $level->value,
                'enrolled_at' => Carbon::now(),
            ];

            if ($existing !== null) {
                // Eerder afgemeld → reactiveren met nieuwe status.
                $existing->update($attributes);
                $enrollment = $existing;
            } else {
                $enrollment = Enrollment::query()->create($attributes + [
                    'activity_id' => $activity->id,
                    'person_id' => $person->id,
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

        $this->notifier->notifyEnrollment($activity, $person, true);

        return $enrollment;
    }

    /**
     * Schrijft `person` in op alle (gepubliceerde) voorkomens van `series` in
     * één keer. Voorkomens waar al een actieve inschrijving bestaat worden
     * overgeslagen (geen fout) zodat een gedeeltelijke eerdere inschrijving
     * de rest van de reeks niet blokkeert.
     *
     * @return Collection<int, Enrollment>
     *
     * @throws RuntimeException Als de reeks geen serie-brede inschrijving toestaat.
     */
    public function enrollSeries(ActivitySeries $series, Person $person, ?Person $requestedBy = null): Collection
    {
        if (! $series->enrollment_level->allowsSerie()) {
            throw new RuntimeException('Voor deze reeks kun je alleen per voorkomen inschrijven.');
        }

        return DB::transaction(function () use ($series, $person, $requestedBy): Collection {
            $created = collect();

            foreach ($series->activities()->where('status', ActivityStatus::Published->value)->get() as $activity) {
                try {
                    $created->push($this->enroll($activity, $person, $requestedBy, $series, EnrollmentLevel::Reeks));
                } catch (RuntimeException) {
                    // Al actief ingeschreven op dit voorkomen (of niet leeftijdsgeschikt) — overslaan.
                    continue;
                }
            }

            return $created;
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

        $this->notifier->notifyEnrollment($enrollment->activity, $enrollment->person, false);
    }

    /**
     * Meldt `person` af voor de hele reeks-brede inschrijving (alle voorkomens
     * die als 'reeks' getagd zijn). Losse afmelding per voorkomen loopt
     * gewoon via cancel() met die ene Enrollment.
     */
    public function cancelSeries(ActivitySeries $series, Person $person, ?Person $actor = null): int
    {
        return DB::transaction(function () use ($series, $person, $actor): int {
            $enrollments = Enrollment::query()
                ->where('series_id', $series->id)
                ->where('person_id', $person->id)
                ->where('level', EnrollmentLevel::Reeks->value)
                ->whereIn('status', [EnrollmentStatus::Enrolled->value, EnrollmentStatus::Waitlist->value])
                ->get();

            foreach ($enrollments as $enrollment) {
                $this->cancel($enrollment, $actor);
            }

            return $enrollments->count();
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
