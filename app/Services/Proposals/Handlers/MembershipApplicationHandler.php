<?php

namespace App\Services\Proposals\Handlers;

use App\Enums\MembershipStatus;
use App\Models\Guardianship;
use App\Models\Household;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Proposals\Contracts\ProposalHandler;
use App\Services\Proposals\Exceptions\ProposalConflictException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Effectueert een goedgekeurde "Lid worden"-aanvraag (§19).
 *
 * subject_type: 'membership.application'
 * subject_id:   null (er is nog geen bestaande entiteit)
 * payload:      [
 *   'person' => ['first_name', 'last_name_prefix', 'last_name', 'date_of_birth', 'email', 'phone'],
 *   'address' => ['postal_code', 'house_number', 'house_number_addition', 'street', 'city'],
 *   'membership_type_key' => string,
 *   'membership_type_override_reason' => ?string,
 *   'is_minor' => bool,
 *   'guardian' => [
 *     'existing_person_id' => ?int,    // guardian was ingelogd
 *     'first_name' => ?string,          // óf nieuwe guardian-gegevens
 *     'last_name_prefix' => ?string,
 *     'last_name' => ?string,
 *     'email' => ?string,
 *     'phone' => ?string,
 *   ],
 * ]
 */
class MembershipApplicationHandler implements ProposalHandler
{
    public const string SUBJECT_TYPE = 'membership.application';

    public function revalidate(Proposal $proposal): void
    {
        $payload = $proposal->payload ?? [];
        $typeKey = $payload['membership_type_key'] ?? null;

        if (! is_string($typeKey) || MembershipType::query()->where('key', $typeKey)->doesntExist()) {
            throw new ProposalConflictException('Gekozen lidmaatschapsvorm bestaat niet meer.');
        }

        $guardianId = $payload['guardian']['existing_person_id'] ?? null;
        if (is_int($guardianId) && Person::query()->whereKey($guardianId)->doesntExist()) {
            throw new ProposalConflictException('De gekoppelde ouder/verzorger bestaat niet meer.');
        }

        $applicantEmail = $payload['person']['email'] ?? null;
        if (is_string($applicantEmail) && Person::query()->where('email', $applicantEmail)->exists()) {
            throw new ProposalConflictException('Er bestaat inmiddels al een persoon met dit e-mailadres.');
        }

        $guardianEmail = $payload['guardian']['email'] ?? null;
        if (is_string($guardianEmail) && Person::query()->where('email', $guardianEmail)->exists()) {
            throw new ProposalConflictException('Er bestaat inmiddels al een persoon met dit e-mailadres voor de ouder/verzorger.');
        }
    }

    public function apply(Proposal $proposal): void
    {
        $payload = $proposal->payload ?? [];

        $type = MembershipType::query()->where('key', $payload['membership_type_key'])->firstOrFail();
        $household = $this->resolveHousehold($payload['address'] ?? []);
        $applicant = $this->createPerson($payload['person'] ?? [], $household);

        $isMinor = (bool) ($payload['is_minor'] ?? false);
        $guardian = $isMinor ? $this->resolveGuardian($payload['guardian'] ?? [], $household) : null;

        if (! $isMinor) {
            $this->attachAccount($applicant);
        }

        Membership::create([
            'person_id' => $applicant->id,
            'membership_type_id' => $type->id,
            'start_date' => now()->toDateString(),
            'status' => MembershipStatus::Active,
            'billing_person_id' => $guardian !== null ? $guardian->id : $applicant->id,
        ]);

        if ($guardian !== null) {
            Guardianship::create([
                'minor_person_id' => $applicant->id,
                'guardian_person_id' => $guardian->id,
                'is_payer' => true,
                'may_act_on_behalf' => true,
                'consent_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function resolveHousehold(array $address): Household
    {
        $postalCode = strtoupper(preg_replace('/\s+/', '', (string) ($address['postal_code'] ?? '')) ?? '');
        $houseNumber = trim((string) ($address['house_number'] ?? ''));
        $addition = trim((string) ($address['house_number_addition'] ?? ''));

        $existing = Household::query()
            ->where('postal_code', $postalCode)
            ->where('house_number', $addition !== '' ? $houseNumber.' '.$addition : $houseNumber)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $country = strtoupper((string) ($address['country'] ?? 'NL'));
        if ($country === '') {
            $country = 'NL';
        }

        return Household::create([
            'name' => trim((string) ($address['street'] ?? '').' '.$houseNumber),
            'street' => (string) ($address['street'] ?? ''),
            'house_number' => $addition !== '' ? $houseNumber.' '.$addition : $houseNumber,
            'postal_code' => $postalCode,
            'city' => (string) ($address['city'] ?? ''),
            'country' => $country,
        ]);
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function createPerson(array $person, Household $household): Person
    {
        return Person::create([
            'first_name' => (string) ($person['first_name'] ?? ''),
            'last_name_prefix' => $this->nullableString($person['last_name_prefix'] ?? null),
            'last_name' => (string) ($person['last_name'] ?? ''),
            'date_of_birth' => $person['date_of_birth'] ?? null,
            'email' => $this->nullableString($person['email'] ?? null),
            'phone' => $this->nullableString($person['phone'] ?? null),
            'household_id' => $household->id,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $guardian
     */
    private function resolveGuardian(array $guardian, Household $household): Person
    {
        $existingId = $guardian['existing_person_id'] ?? null;
        if (is_int($existingId)) {
            return Person::query()->findOrFail($existingId);
        }

        $person = Person::create([
            'first_name' => (string) ($guardian['first_name'] ?? ''),
            'last_name_prefix' => $this->nullableString($guardian['last_name_prefix'] ?? null),
            'last_name' => (string) ($guardian['last_name'] ?? ''),
            'email' => $this->nullableString($guardian['email'] ?? null),
            'phone' => $this->nullableString($guardian['phone'] ?? null),
            'household_id' => $household->id,
            'status' => 'active',
        ]);

        $this->attachAccount($person);

        return $person;
    }

    private function attachAccount(Person $person): void
    {
        if ($person->email === null || $person->email === '') {
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $person->email],
            [
                'name' => trim(collect([$person->first_name, $person->last_name_prefix, $person->last_name])->filter()->implode(' ')),
                'password' => bcrypt(Str::random(48)),
            ]
        );

        $conflict = Person::query()
            ->where('account_id', $user->id)
            ->where('id', '!=', $person->id)
            ->exists();

        if ($conflict) {
            throw new ProposalConflictException(
                "Het gebruikersaccount voor {$user->email} is al gekoppeld aan een ander lid; koppeling niet uitgevoerd."
            );
        }

        $person->update(['account_id' => $user->id]);

        Password::broker()->sendResetLink(['email' => $user->email]);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
