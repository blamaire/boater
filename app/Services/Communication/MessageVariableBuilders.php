<?php

namespace App\Services\Communication;

use App\Models\Activity;
use App\Models\ContactRequest;
use App\Models\DamageReport;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Person;

/**
 * Bouwt de `{{variabele}}`-array voor een sjabloon-sleutel op uit een
 * concreet record. Bewust hier gecentraliseerd (§24, Fase B2/C) i.p.v. inline
 * in elke trigger: `MessageTemplateBeheer`'s testmail-preview (via
 * `MessageSampleRegistry`) roept exact dezelfde methode aan als de echte
 * trigger, zodat een testmail nooit uit de pas kan lopen met wat er
 * werkelijk wordt verstuurd.
 */
class MessageVariableBuilders
{
    /** @return array<string, string> */
    public static function damageReportSubmitted(DamageReport $report): array
    {
        $reporter = $report->reportedBy;
        $reporterLabel = trim($reporter->first_name.' '.$reporter->last_name);
        $notice = $report->reporter_marked_unusable
            ? 'De melder heeft "niet bruikbaar" aangevinkt. Het object staat nu op "buiten gebruik" en is onreserveerbaar totdat een behandelaar dat terugdraait.'
            : '';

        return [
            '{{object}}' => $report->object->name,
            '{{melder}}' => $reporterLabel !== '' ? $reporterLabel : 'onbekend',
            '{{ernst}}' => $report->severity->label(),
            '{{niet_bruikbaar_notice}}' => $notice,
            '{{melding_url}}' => url('/beheer/schademeldingen/'.$report->id),
        ];
    }

    /** @return array<string, string> */
    public static function contactRequestSubmitted(ContactRequest $request): array
    {
        return [
            '{{onderwerp}}' => $request->topic->name,
            '{{naam}}' => $request->name,
            '{{voorkeur}}' => $request->contactMethodLabel(),
            '{{telefoon_regel}}' => $request->phone ? 'Telefoon: '.$request->phone.'<br>' : '',
            '{{email_regel}}' => $request->email ? 'E-mail: '.$request->email.'<br>' : '',
            '{{bericht}}' => $request->message,
            '{{verzoek_url}}' => url('/beheer/contactverzoeken/'.$request->id),
        ];
    }

    /** @return array<string, string> */
    public static function activityChanged(Activity $activity): array
    {
        return [
            '{{titel}}' => $activity->title,
            '{{datum}}' => $activity->starts_at->translatedFormat('l j F Y H:i'),
            '{{activiteiten_url}}' => url('/beheer/activiteiten'),
        ];
    }

    /** @return array<string, string> */
    public static function activityEnrollmentChanged(Activity $activity, Person $person, bool $enrolled): array
    {
        $personLabel = trim($person->first_name.' '.$person->last_name);

        return [
            '{{onderwerp_actie}}' => $enrolled ? 'Nieuwe inschrijving' : 'Nieuwe afmelding',
            '{{persoon}}' => $personLabel,
            '{{actie}}' => $enrolled ? 'ingeschreven op' : 'afgemeld voor',
            '{{titel}}' => $activity->title,
            '{{activiteiten_url}}' => url('/beheer/activiteiten'),
        ];
    }

    /**
     * Zelfde variabelen voor `enrollment_confirmed` en `enrollment_waitlisted`
     * — alleen de gekozen sjabloon-tekst verschilt, niet de invulling.
     *
     * @return array<string, string>
     */
    public static function enrollmentConfirmedOrWaitlisted(Enrollment $enrollment): array
    {
        $person = $enrollment->person;
        $activity = $enrollment->activity;

        return [
            '{{voornaam}}' => $person->first_name,
            '{{achternaam}}' => $person->last_name,
            '{{titel}}' => $activity->title,
            '{{datum}}' => $activity->starts_at->translatedFormat('l j F Y H:i'),
            '{{locatie_regel}}' => $activity->location !== null ? 'Locatie: '.$activity->location : '',
            '{{activiteit_url}}' => route('activiteit.show', $activity),
        ];
    }

    /** @return array<string, string> */
    public static function invoiceCreated(Invoice $invoice): array
    {
        return [
            '{{factuurnummer}}' => (string) $invoice->number,
            '{{bedrag}}' => '€ '.number_format((float) $invoice->total, 2, ',', '.'),
            '{{vervaldatum}}' => $invoice->due_at?->translatedFormat('j F Y') ?? '',
        ];
    }
}
