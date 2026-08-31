<?php

namespace App\Services\Activities;

use App\Enums\ActivityStatus;
use App\Enums\EnrollmentLevel;
use App\Enums\EnrollmentStatus;
use App\Models\Activity;
use App\Models\ActivityRegistrationField;
use App\Models\ActivitySeries;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\EnrollmentFieldValue;
use App\Models\Person;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Communication\MessageDispatcher;
use App\Services\Communication\MessageVariableBuilders;
use App\Services\Finance\BillingService;
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
 * Fase D: standaard-/annuleringskosten worden bij een bevestigde inschrijving
 * resp. annulering als `Charge` geboekt via `BillingService`, met het bedrag
 * uit de actuele prijs van het gekoppelde `Product` (§23.5).
 */
class EnrollmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ActivityManagerNotifier $notifier,
        private readonly BillingService $billing,
        private readonly MessageDispatcher $dispatcher,
    ) {}

    /**
     * Schrijft `person` in op `activity`, eventueel op wachtlijst.
     *
     * @param  Person  $person  De begunstigde (degene die daadwerkelijk meedoet).
     * @param  Person|null  $requestedBy  Wie de inschrijving doet (kan gelijk zijn aan $person).
     * @param  ActivitySeries|null  $series  Meegegeven bij een serie-brede inschrijving (intern gebruik, zie enrollSeries()).
     * @param  array<int, mixed>  $fieldAnswers  Antwoorden op `Activity::registrationFields()`, per veld-id: tekst (string),
     *                                           keuze (option-id, int) of aantal (int). Fase C — nog indicatief, geen Charge (§17.3/17.4).
     *
     * @throws RuntimeException Bij een gesloten of afgelaste activiteit, buiten leeftijd, verkeerd inschrijfniveau,
     *                          dubbele actieve inschrijving, of een ongeldig/ontbrekend antwoord op een inschrijfveld.
     */
    public function enroll(
        Activity $activity,
        Person $person,
        ?Person $requestedBy = null,
        ?ActivitySeries $series = null,
        EnrollmentLevel $level = EnrollmentLevel::Bundel,
        array $fieldAnswers = [],
    ): Enrollment {
        if ($activity->status !== ActivityStatus::Published) {
            throw new RuntimeException('Deze activiteit staat niet open voor inschrijving.');
        }

        if (! $activity->isEnrollmentOpen()) {
            throw new RuntimeException('Het inschrijfvenster voor deze activiteit is nog niet geopend of al gesloten.');
        }

        if ($level === EnrollmentLevel::Bundel && $activity->series !== null && ! $activity->series->enrollment_level->allowsPerVoorkomen()) {
            throw new RuntimeException('Voor deze reeks kun je alleen voor de hele reeks inschrijven.');
        }

        if (! $activity->isAgeEligible($person)) {
            throw new RuntimeException('Deze persoon voldoet niet aan de leeftijdseis van deze activiteit.');
        }

        $this->assertFieldAnswersValid($activity, $fieldAnswers);

        $enrollment = DB::transaction(function () use ($activity, $person, $requestedBy, $series, $level, $fieldAnswers): Enrollment {
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

            $this->saveFieldAnswers($activity, $enrollment, $fieldAnswers);

            if ($status === EnrollmentStatus::Enrolled) {
                $this->chargeStandardCost($activity, $enrollment);
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

        if ($person->email !== null && $person->email !== '') {
            $templateKey = $enrollment->status === EnrollmentStatus::Waitlist ? 'enrollment_waitlisted' : 'enrollment_confirmed';

            $this->dispatcher->send(
                $templateKey,
                $person->email,
                MessageVariableBuilders::enrollmentConfirmedOrWaitlisted($enrollment),
                recipient: $person,
                related: $activity,
            );
        }

        return $enrollment;
    }

    /**
     * Schrijft `person` in op alle (gepubliceerde) voorkomens van `series` in
     * één keer. Voorkomens waar al een actieve inschrijving bestaat worden
     * overgeslagen (geen fout) zodat een gedeeltelijke eerdere inschrijving
     * de rest van de reeks niet blokkeert.
     *
     * @param  array<int, mixed>  $fieldAnswers  Zelfde antwoorden toegepast op elk voorkomen (§17.3/17.4) — de
     *                                           inschrijfvelden van een reeks/bundel zijn identiek per voorkomen (gekopieerd bij aanmaken).
     * @return Collection<int, Enrollment>
     *
     * @throws RuntimeException Als de reeks geen serie-brede inschrijving toestaat.
     */
    public function enrollSeries(ActivitySeries $series, Person $person, ?Person $requestedBy = null, array $fieldAnswers = []): Collection
    {
        if (! $series->enrollment_level->allowsSerie()) {
            throw new RuntimeException('Voor deze reeks kun je alleen per voorkomen inschrijven.');
        }

        return DB::transaction(function () use ($series, $person, $requestedBy, $fieldAnswers): Collection {
            $created = collect();

            foreach ($series->activities()->where('status', ActivityStatus::Published->value)->get() as $activity) {
                try {
                    $created->push($this->enroll($activity, $person, $requestedBy, $series, EnrollmentLevel::Reeks, $fieldAnswers));
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
        $activity = $enrollment->activity()->firstOrFail();
        if (! $activity->canCancel()) {
            throw new RuntimeException('De uiterste annuleringsdatum voor deze activiteit is verstreken.');
        }

        $wasEnrolled = $enrollment->status === EnrollmentStatus::Enrolled;

        DB::transaction(function () use ($enrollment, $actor, $activity, $wasEnrolled): void {
            $before = ['status' => $enrollment->status->value];

            $enrollment->update([
                'status' => EnrollmentStatus::Cancelled,
            ]);

            $this->audit->log('activity.enrollment_cancelled', $enrollment->activity, before: $before, after: [
                'enrollment_id' => $enrollment->id,
                'person_id' => $enrollment->person_id,
                'actor_id' => $actor?->id,
            ]);

            // Alleen een al bevestigde plek (niet de wachtlijst) kost iets om te annuleren.
            if ($wasEnrolled) {
                $this->chargeCancellationCost($activity, $enrollment);
            }

            $this->promoteWaitlist($activity);
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
            $this->chargeStandardCost($activity, $next);
            $this->audit->log('activity.waitlist_promoted', $activity, after: [
                'enrollment_id' => $next->id,
                'person_id' => $next->person_id,
            ]);
            $promoted++;
        }

        return $promoted;
    }

    /**
     * @param  array<int, mixed>  $fieldAnswers
     *
     * @throws RuntimeException Als een verplicht veld ontbreekt, een aantal buiten de grenzen valt, of een
     *                          opgegeven keuze niet bij het veld hoort.
     */
    private function assertFieldAnswersValid(Activity $activity, array $fieldAnswers): void
    {
        foreach ($activity->registrationFields as $field) {
            $answer = $fieldAnswers[$field->id] ?? null;

            if ($field->required && ($answer === null || $answer === '')) {
                throw new RuntimeException("Het veld \"{$field->label}\" is verplicht.");
            }

            if ($answer === null || $answer === '') {
                continue;
            }

            if ($field->type === ActivityRegistrationField::TYPE_COUNT) {
                if (! is_numeric($answer) || (int) $answer < 0) {
                    throw new RuntimeException("Ongeldig aantal voor \"{$field->label}\".");
                }
                if ($field->max_count !== null && (int) $answer > $field->max_count) {
                    throw new RuntimeException("Het aantal voor \"{$field->label}\" mag niet hoger zijn dan {$field->max_count}.");
                }
            }

            if ($field->type === ActivityRegistrationField::TYPE_CHOICE && ! $field->options->contains('id', (int) $answer)) {
                throw new RuntimeException("Ongeldige keuze voor \"{$field->label}\".");
            }
        }
    }

    /** @param  array<int, mixed>  $fieldAnswers */
    private function saveFieldAnswers(Activity $activity, Enrollment $enrollment, array $fieldAnswers): void
    {
        foreach ($activity->registrationFields as $field) {
            $answer = $fieldAnswers[$field->id] ?? null;

            if ($answer === null || $answer === '') {
                EnrollmentFieldValue::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('field_id', $field->id)
                    ->delete();

                continue;
            }

            EnrollmentFieldValue::query()->updateOrCreate(
                ['enrollment_id' => $enrollment->id, 'field_id' => $field->id],
                match ($field->type) {
                    ActivityRegistrationField::TYPE_TEXT => ['text_value' => (string) $answer, 'option_id' => null, 'count_value' => null],
                    ActivityRegistrationField::TYPE_CHOICE => ['text_value' => null, 'option_id' => (int) $answer, 'count_value' => null],
                    ActivityRegistrationField::TYPE_COUNT => ['text_value' => null, 'option_id' => null, 'count_value' => (int) $answer],
                    default => ['text_value' => null, 'option_id' => null, 'count_value' => null],
                },
            );
        }
    }

    /**
     * Boekt de standaardkosten (§23.5, Fase D) — alleen bij een bevestigde
     * plek, nooit voor de wachtlijst. Zonder gekoppeld product of zonder
     * geldende prijs gebeurt er niets. Al geboekt voor dit voorkomen (bv. bij
     * een eerdere aanmelding die weer geannuleerd en herstart is): overslaan.
     */
    private function chargeStandardCost(Activity $activity, Enrollment $enrollment): void
    {
        $this->chargeOnce($activity->standardCostProduct, $enrollment, fn (Product $product) => "Deelname \"{$activity->title}\" ({$activity->starts_at->format('d-m-Y')})");
    }

    /**
     * Boekt de annuleringskosten (§23.5, Fase D) bij het afmelden van een
     * bevestigde plek.
     */
    private function chargeCancellationCost(Activity $activity, Enrollment $enrollment): void
    {
        $this->chargeOnce($activity->cancellationCostProduct, $enrollment, fn (Product $product) => "Annulering \"{$activity->title}\" ({$activity->starts_at->format('d-m-Y')})");
    }

    /** @param  callable(Product): string  $describe */
    private function chargeOnce(?Product $product, Enrollment $enrollment, callable $describe): void
    {
        if ($product === null) {
            return;
        }

        $price = $product->currentPrice();
        if ($price === null) {
            return;
        }

        $alreadyCharged = Charge::query()
            ->where('subject_type', Enrollment::class)
            ->where('subject_id', $enrollment->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($alreadyCharged) {
            return;
        }

        $debtor = $enrollment->requestedBy ?? $enrollment->person;

        $this->billing->createCharge(
            product: $product,
            debtor: $debtor,
            amount: $price->amount,
            description: $describe($product),
            subject: $enrollment,
        );
    }
}
