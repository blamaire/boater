<?php

namespace App\Livewire\Portal;

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Models\Person;
use App\Models\Proposal;
use App\Services\Membership\BagAddressLookup;
use App\Services\Membership\MembershipEligibility;
use App\Services\Membership\MembershipTypeEligibility;
use App\Services\Proposals\Exceptions\ProposalConflictException;
use App\Services\Proposals\Exceptions\ProposalStateException;
use App\Services\Proposals\Handlers\MembershipApplicationHandler;
use App\Services\Proposals\ProposalEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Bewerken van een eigen, nog openstaande lidmaatschapsaanvraag
 * (subject_type membership.application). Zelfde velden/validatie/eligibility
 * als de publieke aanmeldflow (App\Livewire\Public\LidWorden), maar dan
 * vooraf ingevuld vanuit de bestaande proposal-payload. Opslaan trekt het
 * oude voorstel in en dient de bijgewerkte gegevens opnieuw in (§20 —
 * "aanpassen vóór beoordeling" is intrekken + opnieuw indienen, hetzelfde
 * patroon als MijnLidmaatschap::submitSensitive()).
 */
#[Layout('layouts.app', ['header' => 'Aanvraag aanpassen'])]
class LidmaatschapsaanvraagBewerken extends Component
{
    public Proposal $proposal;

    public string $first_name = '';

    public ?string $last_name_prefix = null;

    public string $last_name = '';

    public ?string $date_of_birth = null;

    public string $email = '';

    public ?string $phone = null;

    public string $postal_code = '';

    public string $house_number = '';

    public ?string $house_number_addition = null;

    public ?string $street = null;

    public ?string $city = null;

    public string $country = 'NL';

    public bool $abroad = false;

    public ?string $bag_error = null;

    public ?string $membership_type_key = null;

    public string $override_reason = '';

    public string $guardian_first_name = '';

    public ?string $guardian_last_name_prefix = null;

    public string $guardian_last_name = '';

    public string $guardian_email = '';

    public ?string $guardian_phone = null;

    public bool $agree_statutes = false;

    public bool $agree_house_rules = false;

    public bool $agree_privacy = false;

    public function mount(Proposal $proposal): void
    {
        $person = $this->requirePerson();

        abort_unless($proposal->subject_type === MembershipApplicationHandler::SUBJECT_TYPE, 404);
        abort_unless($proposal->proposed_by_person_id === $person->id, 403);
        abort_unless(
            $proposal->status->isOpen() || $proposal->status === ProposalStatus::Rejected,
            403,
            'Dit voorstel is al afgehandeld en kan niet meer worden aangepast.',
        );

        $this->proposal = $proposal;

        $payload = $proposal->payload;
        $personPayload = $payload['person'] ?? [];
        $addressPayload = $payload['address'] ?? [];
        $guardianPayload = $payload['guardian'] ?? [];

        $this->first_name = (string) ($personPayload['first_name'] ?? '');
        $this->last_name_prefix = $personPayload['last_name_prefix'] ?? null;
        $this->last_name = (string) ($personPayload['last_name'] ?? '');
        $this->date_of_birth = $personPayload['date_of_birth'] ?? null;
        $this->email = (string) ($personPayload['email'] ?? '');
        $this->phone = $personPayload['phone'] ?? null;

        $this->postal_code = (string) ($addressPayload['postal_code'] ?? '');
        $this->house_number = (string) ($addressPayload['house_number'] ?? '');
        $this->house_number_addition = $addressPayload['house_number_addition'] ?? null;
        $this->street = $addressPayload['street'] ?? null;
        $this->city = $addressPayload['city'] ?? null;
        $this->country = (string) ($addressPayload['country'] ?? 'NL');
        $this->abroad = strtoupper($this->country) !== 'NL';

        $this->membership_type_key = $payload['membership_type_key'] ?? null;
        $this->override_reason = (string) ($payload['membership_type_override_reason'] ?? '');

        if (empty($guardianPayload['existing_person_id'])) {
            $this->guardian_first_name = (string) ($guardianPayload['first_name'] ?? '');
            $this->guardian_last_name_prefix = $guardianPayload['last_name_prefix'] ?? null;
            $this->guardian_last_name = (string) ($guardianPayload['last_name'] ?? '');
            $this->guardian_email = (string) ($guardianPayload['email'] ?? '');
            $this->guardian_phone = $guardianPayload['phone'] ?? null;
        }
    }

    public function lookupAddress(BagAddressLookup $lookup): void
    {
        $this->bag_error = null;

        $address = $lookup->lookup($this->postal_code, $this->house_number, $this->house_number_addition);
        if ($address === null) {
            $this->street = null;
            $this->city = null;
            $this->bag_error = 'Geen adres gevonden voor deze combinatie van postcode en huisnummer.';

            return;
        }

        $this->postal_code = $address->postalCode;
        $this->street = $address->street;
        $this->city = $address->city;
    }

    #[Computed]
    public function dateOfBirth(): ?Carbon
    {
        if ($this->date_of_birth === null || $this->date_of_birth === '') {
            return null;
        }
        try {
            return Carbon::parse($this->date_of_birth)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    #[Computed]
    public function isMinor(): bool
    {
        $dob = $this->dateOfBirth();

        return $dob !== null && $dob->age < 18;
    }

    /**
     * @return Collection<int, MembershipTypeEligibility>
     */
    #[Computed]
    public function eligibility(): Collection
    {
        return app(MembershipEligibility::class)->evaluate(
            $this->dateOfBirth(),
            $this->postal_code !== '' ? $this->postal_code : null,
            $this->house_number !== '' ? $this->house_number : null,
        );
    }

    #[Computed]
    public function chosenEligibility(): ?MembershipTypeEligibility
    {
        if ($this->membership_type_key === null) {
            return null;
        }

        return $this->eligibility()->firstWhere(fn (MembershipTypeEligibility $e) => $e->type->key === $this->membership_type_key);
    }

    public function save(ProposalEngine $engine): void
    {
        $person = $this->requirePerson();

        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'email' => 'required|email|max:200|unique:persons,email',
            'phone' => 'nullable|string|max:30',
            'postal_code' => ($this->abroad ? 'nullable' : 'required').'|string|max:10',
            'house_number' => 'required|string|max:10',
            'street' => 'required|string|max:200',
            'city' => 'required|string|max:200',
            'country' => 'required|string|size:2',
            'membership_type_key' => 'required|string|exists:membership_types,key',
            'agree_statutes' => 'accepted',
            'agree_house_rules' => 'accepted',
            'agree_privacy' => 'accepted',
        ], [
            'email.unique' => 'Dit e-mailadres is al bekend bij ons.',
            'agree_statutes.accepted' => 'Je moet akkoord gaan met de statuten.',
            'agree_house_rules.accepted' => 'Je moet akkoord gaan met het huishoudelijk reglement.',
            'agree_privacy.accepted' => 'Je moet akkoord gaan met het privacybeleid.',
        ]);

        $chosen = $this->chosenEligibility();
        if ($chosen === null) {
            $this->addError('membership_type_key', 'Kies een lidmaatschapsvorm.');

            return;
        }

        if (! $chosen->available && trim($this->override_reason) === '') {
            $this->addError('override_reason', 'Licht toe waarom deze vorm volgens jou toch van toepassing is.');

            return;
        }

        $guardianPayload = null;
        if ($this->isMinor()) {
            $ingelogd = auth()->user()?->person;
            if ($ingelogd !== null) {
                $guardianPayload = ['existing_person_id' => $ingelogd->id];
            } else {
                $this->validate([
                    'guardian_first_name' => 'required|string|max:100',
                    'guardian_last_name' => 'required|string|max:100',
                    'guardian_email' => 'required|email|max:200|unique:persons,email',
                    'guardian_phone' => 'nullable|string|max:30',
                ], [
                    'guardian_email.unique' => 'Dit e-mailadres is al bekend bij ons — log in als ouder/verzorger of gebruik een ander adres.',
                ]);
                $guardianPayload = [
                    'first_name' => $this->guardian_first_name,
                    'last_name_prefix' => $this->guardian_last_name_prefix,
                    'last_name' => $this->guardian_last_name,
                    'email' => $this->guardian_email,
                    'phone' => $this->guardian_phone,
                ];
            }
        }

        $payload = [
            'person' => [
                'first_name' => $this->first_name,
                'last_name_prefix' => $this->last_name_prefix,
                'last_name' => $this->last_name,
                'date_of_birth' => $this->date_of_birth,
                'email' => $this->email,
                'phone' => $this->phone,
            ],
            'address' => [
                'postal_code' => $this->postal_code,
                'house_number' => $this->house_number,
                'house_number_addition' => $this->house_number_addition,
                'street' => $this->street,
                'city' => $this->city,
                'country' => strtoupper($this->country),
            ],
            'membership_type_key' => $this->membership_type_key,
            'membership_type_override_reason' => $chosen->available ? null : trim($this->override_reason),
            'is_minor' => $this->isMinor(),
            'guardian' => $guardianPayload,
            'agreements' => [
                'statutes' => true,
                'house_rules' => true,
                'privacy' => true,
            ],
        ];

        try {
            if ($this->proposal->status->isOpen()) {
                $engine->withdraw($this->proposal, $person);
            }

            $engine->submit(
                subjectType: MembershipApplicationHandler::SUBJECT_TYPE,
                changeType: ChangeType::Create,
                payload: $payload,
                proposer: $person,
            );

            if ($this->proposal->status === ProposalStatus::Rejected) {
                $engine->archive($this->proposal, $person);
            }
        } catch (ProposalStateException|ProposalConflictException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        session()->flash('status', 'Je aanvraag is bijgewerkt en opnieuw ingediend ter beoordeling.');
        $this->redirectRoute('portal.wijzigingsvoorstellen');
    }

    public function render(): View
    {
        return view('livewire.portal.lidmaatschapsaanvraag-bewerken');
    }

    private function requirePerson(): Person
    {
        $person = auth()->user()?->person;
        abort_if($person === null, 403, 'Je account is niet gekoppeld aan een persoon.');

        return $person;
    }
}
