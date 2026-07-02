<?php

namespace App\Services\Proposals\Handlers;

use App\Models\Person;
use App\Models\Proposal;
use App\Services\Audit\AuditLogger;
use App\Services\Proposals\Contracts\ProposalHandler;
use App\Services\Proposals\Exceptions\ProposalConflictException;
use Illuminate\Support\Carbon;

/**
 * Behandelt wijzigingen op enkele "gevoelige" persoonsvelden die niet direct
 * mogen worden opgeslagen (bijv. naam, geboortedatum, lidmaatschapstype).
 *
 * subject_type: 'person.field_update'
 * subject_id:   ID van de Person waarop de wijziging betrekking heeft
 * payload:      [
 *                 'person_id'  => int,
 *                 'field'      => string,   // een van SENSITIVE_FIELDS
 *                 'new_value'  => mixed,
 *                 'old_value'  => mixed,    // snapshot bij indienen
 *               ]
 *
 * NB: SENSITIVE_FIELDS is nu hardcoded. Zodra Agent B de generieke
 * FIELD_DEFINITION-tabel (§19.3) toevoegt vervangt die dit — de constante
 * hier is dan het fallback-vangnet.
 */
class PersonFieldUpdateHandler implements ProposalHandler
{
    public const string SUBJECT_TYPE = 'person.field_update';

    /**
     * Velden die alleen via een goedgekeurd voorstel gewijzigd mogen worden.
     * Overige velden op Person mag het lid direct wijzigen.
     *
     * @var list<string>
     */
    public const array SENSITIVE_FIELDS = [
        'first_name',
        'last_name_prefix',
        'last_name',
        'date_of_birth',
        'membership_type_id',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function revalidate(Proposal $proposal): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $proposal->payload;

        $field = (string) ($payload['field'] ?? '');

        if (! in_array($field, self::SENSITIVE_FIELDS, true)) {
            throw new ProposalConflictException("Veld [{$field}] is niet toegestaan voor deze goedkeuringsroute.");
        }

        $person = Person::query()->find($payload['person_id'] ?? null);

        if ($person === null) {
            throw new ProposalConflictException('Persoon bestaat niet meer.');
        }

        $currentActual = $this->currentValue($person, $field);
        $expected = $payload['old_value'] ?? null;

        if ($this->normalize($currentActual) !== $this->normalize($expected)) {
            throw new ProposalConflictException(
                "De waarde van [{$field}] is intussen door iemand anders gewijzigd.",
            );
        }
    }

    public function apply(Proposal $proposal): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $proposal->payload;

        $field = (string) $payload['field'];

        /** @var Person $person */
        $person = Person::query()->findOrFail($payload['person_id']);

        $before = [$field => $this->currentValue($person, $field)];

        if ($field === 'membership_type_id') {
            $current = $person->currentMembership();
            if ($current === null) {
                throw new ProposalConflictException('Geen lopend lidmaatschap om het type van te wijzigen.');
            }
            $current->membership_type_id = (int) $payload['new_value'];
            $current->save();
        } else {
            $person->{$field} = $payload['new_value'] ?? null;
            $person->save();
        }

        $this->audit->log(
            'person.field_updated',
            $person,
            before: $before,
            after: [$field => $this->currentValue($person->refresh(), $field)],
            context: ['via_proposal_id' => $proposal->id, 'field' => $field],
        );
    }

    /**
     * Haal de actuele waarde van een veld op de manier zoals hij ook in de
     * payload was opgeslagen (voor date-of-birth als Y-m-d-string en voor
     * membership_type_id via het lopende Membership-record).
     */
    private function currentValue(Person $person, string $field): mixed
    {
        if ($field === 'membership_type_id') {
            return $person->currentMembership()?->membership_type_id;
        }

        $value = $person->{$field};

        if ($field === 'date_of_birth' && $value instanceof Carbon) {
            return $value->toDateString();
        }

        return $value;
    }

    /**
     * Ontdoe waarden van type-verschillen die bij vergelijking niet relevant
     * zijn: null en lege string zijn beide "geen waarde", en getallen die
     * als string binnenkomen worden numeriek vergeleken.
     */
    private function normalize(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return $value;
    }
}
