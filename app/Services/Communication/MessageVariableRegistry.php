<?php

namespace App\Services\Communication;

/**
 * Welke `{{variabele}}`-namen elk sjabloon (via zijn `key`) accepteert —
 * puur voor de "variabele invoegen"-UI in `/beheer/berichtsjablonen`.
 * Systeembepaald (niet door de beheerder in te vullen): de lijst hoort bij
 * de code van de bijbehorende trigger (§24) en moet in de pas blijven met
 * wat die trigger daadwerkelijk aan `MessageDispatcher::send()` meegeeft.
 * Een sleutel zonder eigen trigger (een nieuw, handmatig aangemaakt
 * sjabloon) heeft simpelweg geen bekende variabelen.
 */
class MessageVariableRegistry
{
    /**
     * @return array<int, string>
     */
    public static function for(string $templateKey): array
    {
        return self::map()[$templateKey] ?? [];
    }

    /**
     * Altijd beschikbaar voor zelf aangemaakte Mailings-sjablonen (§24.4) —
     * die hebben geen vaste trigger-contract zoals Systeemberichten, dus
     * hier wél een vrij te gebruiken basisset: gegevens van de geadresseerde
     * en, waar relevant, van een gekoppelde activiteit. **Nooit** toevoegen
     * aan Systeemberichten-sjablonen (zie `MessageTemplateBeheer`) — daar
     * moet de lijst exact overeenkomen met wat de bijbehorende trigger
     * meegeeft, anders belandt er een nooit-ingevulde `{{token}}` in echte
     * transactionele mail.
     *
     * @return array<int, string>
     */
    public static function baseline(): array
    {
        return ['voornaam', 'achternaam', 'titel', 'datum'];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function map(): array
    {
        return [
            'password_reset' => ['reset_url', 'minuten'],
            'email_verification' => ['verificatie_url'],
            'account_invitation' => ['voornaam', 'achternaam', 'uitnodiging_url', 'minuten'],
            'membership_application_received' => ['voornaam', 'lidmaatschapsvorm'],
            'damage_report_submitted' => ['object', 'melder', 'ernst', 'niet_bruikbaar_notice', 'melding_url'],
            'contact_request_submitted' => ['onderwerp', 'naam', 'voorkeur', 'telefoon_regel', 'email_regel', 'bericht', 'verzoek_url'],
            'activity_changed' => ['titel', 'datum', 'activiteiten_url'],
            'activity_enrollment_changed' => ['onderwerp_actie', 'titel', 'persoon', 'actie', 'activiteiten_url'],
            'enrollment_confirmed' => ['voornaam', 'achternaam', 'titel', 'datum', 'locatie_regel', 'activiteit_url'],
            'enrollment_waitlisted' => ['voornaam', 'achternaam', 'titel', 'datum', 'locatie_regel', 'activiteit_url'],
            'enrollment_waitlist_promoted' => ['voornaam', 'achternaam', 'titel', 'datum', 'locatie_regel', 'activiteit_url'],
            'invoice_created' => ['factuurnummer', 'bedrag', 'vervaldatum'],
        ];
    }
}
