<?php

namespace App\Livewire\Public;

use App\Enums\ChangeType;
use App\Services\Membership\BagAddressLookup;
use App\Services\Membership\MembershipEligibility;
use App\Services\Membership\MembershipTypeEligibility;
use App\Services\Proposals\Handlers\MembershipApplicationHandler;
use App\Services\Proposals\ProposalEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Publieke "Lid worden"-flow (§19.1). Verzamelt persoonsgegevens, adres en
 * eventueel guardian-info, laat de aanvrager een lidmaatschapsvorm kiezen
 * (niet-passende vormen met reden + uitleg-veld) en dient het geheel in
 * bij de goedkeuringsmotor.
 */
#[Layout('components.public-layout', ['title' => 'Lid worden'])]
class LidWorden extends Component
{
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

    public bool $submitted = false;

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

    public function submit(ProposalEngine $engine): void
    {
        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'email' => 'required|email|max:200|unique:persons,email',
            'phone' => 'nullable|string|max:30',
            'postal_code' => 'required|string|max:10',
            'house_number' => 'required|string|max:10',
            'street' => 'required|string|max:200',
            'city' => 'required|string|max:200',
            'membership_type_key' => 'required|string|exists:membership_types,key',
            'agree_statutes' => 'accepted',
            'agree_house_rules' => 'accepted',
            'agree_privacy' => 'accepted',
        ], [
            'email.unique' => 'Dit e-mailadres is al bekend bij ons — heb je al een account?',
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

        $engine->submit(
            subjectType: MembershipApplicationHandler::SUBJECT_TYPE,
            changeType: ChangeType::Create,
            payload: [
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
            ],
            proposer: auth()->user()?->person,
        );

        $this->submitted = true;
    }

    public function render(): View
    {
        return view('livewire.public.lid-worden');
    }
}
