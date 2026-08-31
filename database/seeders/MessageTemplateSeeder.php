<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use App\Services\Communication\MessageVariableRegistry;
use Illuminate\Database\Seeder;

/**
 * Standaard berichtsjablonen (§24) — tekst grotendeels 1-op-1 overgenomen uit
 * de voormalige losse `Notification`-classes (`app/Notifications/*.php`,
 * vóór de retrofit naar `MessageDispatcher`) zodat er geen tekst-regressie
 * optreedt. Drie sjablonen (`account_invitation`, `enrollment_confirmed`,
 * `enrollment_waitlisted`) groeten voortaan bewust met `{{voornaam}}` i.p.v.
 * de generieke "Hallo," van het origineel — deze gaan altijd naar één
 * bekende persoon, dus personalisatie kon zonder extra opzoekwerk toegevoegd
 * worden (zie {@see MessageVariableRegistry}).
 *
 * `body` is een array van blocks (§24, `App\Enums\MessageBlockType`) — geen
 * platte HTML-string. Waar het origineel een call-to-action-knop had, staat
 * hier een echte Knop-block met een `{{..._url}}`-variabele als `href`.
 *
 * Een beheerder kan de tekst nadien vrij aanpassen op
 * `/beheer/berichtsjablonen`.
 */
class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            MessageTemplate::updateOrCreate(['key' => $template['key']], $template);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'key' => 'password_reset',
                'name' => 'Wachtwoord opnieuw instellen',
                'type' => 'transactioneel',
                'subject' => 'Wachtwoord opnieuw instellen',
                'body' => [
                    ['type' => 'tekst', 'content' => ['html' => '<p>Hallo!</p><p>Je ontvangt deze e-mail omdat we een verzoek hebben ontvangen om je wachtwoord opnieuw in te stellen.</p>']],
                    ['type' => 'knop', 'content' => ['label' => 'Wachtwoord opnieuw instellen', 'href' => '{{reset_url}}']],
                    ['type' => 'tekst', 'content' => ['html' => '<p>Deze link verloopt over {{minuten}} minuten.</p><p>Heb je geen verzoek ingediend? Dan hoef je niets te doen.</p><p>Met vriendelijke groet, Roei- en Zeilvereniging Gouda</p>']],
                ],
            ],
            [
                'key' => 'email_verification',
                'name' => 'E-mailadres bevestigen',
                'type' => 'transactioneel',
                'subject' => 'Bevestig je e-mailadres',
                'body' => [
                    ['type' => 'tekst', 'content' => ['html' => '<p>Hallo!</p><p>Klik op onderstaande knop om je e-mailadres te bevestigen.</p>']],
                    ['type' => 'knop', 'content' => ['label' => 'E-mailadres bevestigen', 'href' => '{{verificatie_url}}']],
                    ['type' => 'tekst', 'content' => ['html' => '<p>Heb je geen account aangemaakt? Dan hoef je niets te doen.</p><p>Met vriendelijke groet, Roei- en Zeilvereniging Gouda</p>']],
                ],
            ],
            [
                'key' => 'account_invitation',
                'name' => 'Accountuitnodiging',
                'type' => 'transactioneel',
                'subject' => 'Welkom bij RZVG — kies je wachtwoord',
                'body' => [
                    ['type' => 'tekst', 'content' => ['html' => '<p>Hallo {{voornaam}}!</p><p>Er is voor jou een account aangemaakt op de website van Roei- en Zeilvereniging Gouda.</p><p>Klik op onderstaande knop om je eigen wachtwoord in te stellen. Daarna kun je direct inloggen.</p>']],
                    ['type' => 'knop', 'content' => ['label' => 'Wachtwoord instellen', 'href' => '{{uitnodiging_url}}']],
                    ['type' => 'tekst', 'content' => ['html' => '<p>Deze uitnodiging is {{minuten}} minuten geldig. Vraag de beheerder om een nieuwe link als je te laat bent.</p><p>Met vriendelijke groet, Roei- en Zeilvereniging Gouda</p>']],
                ],
            ],
            [
                'key' => 'membership_application_received',
                'name' => 'Lidmaatschapsaanvraag ontvangen',
                'type' => 'transactioneel',
                'subject' => 'We hebben je aanmelding ontvangen',
                'body' => [
                    ['type' => 'tekst', 'content' => ['html' => '<p>Hallo {{voornaam}}!</p><p>Bedankt voor je aanmelding bij de Roei- en Zeilvereniging Gouda.</p><p>Je hebt gekozen voor de lidmaatschapsvorm: {{lidmaatschapsvorm}}.</p><p>Onze ledenadministratie beoordeelt je aanvraag. Zodra er een besluit is genomen, ontvang je een tweede e-mail met de definitieve bevestiging en verdere instructies (waaronder het instellen van een wachtwoord voor je account).</p><p>Heb je in de tussentijd een vraag? Beantwoord dan gewoon deze e-mail.</p><p>Met vriendelijke groet, Roei- en Zeilvereniging Gouda</p>']],
                ],
            ],
            [
                'key' => 'damage_report_submitted',
                'name' => 'Schademelding ontvangen',
                'type' => 'transactioneel',
                'subject' => 'Nieuwe schademelding op {{object}}',
                'body' => [
                    ['type' => 'tekst', 'content' => ['html' => '<p>Hallo,</p><p>Er is een nieuwe schademelding binnengekomen op een object in jouw categorie.</p><p>Object: {{object}}<br>Melder: {{melder}}<br>Ernst: {{ernst}}</p><p>{{niet_bruikbaar_notice}}</p>']],
                    ['type' => 'knop', 'content' => ['label' => 'Melding openen', 'href' => '{{melding_url}}']],
                    ['type' => 'tekst', 'content' => ['html' => '<p>— RZVG</p>']],
                ],
            ],
            [
                'key' => 'contact_request_submitted',
                'name' => 'Contactverzoek ontvangen',
                'type' => 'transactioneel',
                'subject' => 'Nieuw contactverzoek: {{onderwerp}}',
                'body' => [
                    ['type' => 'tekst', 'content' => ['html' => '<p>Hallo,</p><p>Er is een nieuw contactverzoek binnengekomen voor "{{onderwerp}}".</p><p>Naam: {{naam}}<br>Voorkeur: {{voorkeur}}<br>{{telefoon_regel}}{{email_regel}}</p><p>Bericht:</p><p>{{bericht}}</p>']],
                    ['type' => 'knop', 'content' => ['label' => 'Verzoek openen', 'href' => '{{verzoek_url}}']],
                    ['type' => 'tekst', 'content' => ['html' => '<p>— RZVG</p>']],
                ],
            ],
            [
                'key' => 'activity_changed',
                'name' => 'Activiteit gewijzigd (beheerder)',
                'type' => 'transactioneel',
                'subject' => 'Activiteit gewijzigd: {{titel}}',
                'body' => [
                    ['type' => 'tekst', 'content' => ['html' => '<p>Hallo,</p><p>De activiteit "{{titel}}" is zojuist gewijzigd.</p><p>Datum: {{datum}}</p>']],
                    ['type' => 'knop', 'content' => ['label' => 'Activiteit bekijken', 'href' => '{{activiteiten_url}}']],
                    ['type' => 'tekst', 'content' => ['html' => '<p>— RZVG</p>']],
                ],
            ],
            [
                'key' => 'activity_enrollment_changed',
                'name' => 'In-/uitschrijving activiteit (beheerder)',
                'type' => 'transactioneel',
                'subject' => '{{onderwerp_actie}}: {{titel}}',
                'body' => [
                    ['type' => 'tekst', 'content' => ['html' => '<p>Hallo,</p><p>{{persoon}} heeft zich zojuist {{actie}} "{{titel}}".</p>']],
                    ['type' => 'knop', 'content' => ['label' => 'Activiteit bekijken', 'href' => '{{activiteiten_url}}']],
                    ['type' => 'tekst', 'content' => ['html' => '<p>— RZVG</p>']],
                ],
            ],
            [
                'key' => 'enrollment_confirmed',
                'name' => 'Inschrijfbevestiging (bevestigd)',
                'type' => 'transactioneel',
                'subject' => 'Inschrijfbevestiging: {{titel}}',
                'body' => [
                    ['type' => 'tekst', 'content' => ['html' => '<p>Hallo {{voornaam}},</p><p>Je inschrijving voor "{{titel}}" is bevestigd.</p><p>Datum: {{datum}}</p><p>{{locatie_regel}}</p>']],
                    ['type' => 'knop', 'content' => ['label' => 'Bekijk de activiteit', 'href' => '{{activiteit_url}}']],
                    ['type' => 'tekst', 'content' => ['html' => '<p>— RZVG</p>']],
                ],
            ],
            [
                'key' => 'enrollment_waitlisted',
                'name' => 'Inschrijfbevestiging (wachtlijst)',
                'type' => 'transactioneel',
                'subject' => 'Op de wachtlijst: {{titel}}',
                'body' => [
                    ['type' => 'tekst', 'content' => ['html' => '<p>Hallo {{voornaam}},</p><p>Je staat op de wachtlijst voor "{{titel}}". Zodra er een plek vrijkomt, hoor je van ons.</p><p>Datum: {{datum}}</p><p>{{locatie_regel}}</p>']],
                    ['type' => 'knop', 'content' => ['label' => 'Bekijk de activiteit', 'href' => '{{activiteit_url}}']],
                    ['type' => 'tekst', 'content' => ['html' => '<p>— RZVG</p>']],
                ],
            ],
        ];
    }
}
