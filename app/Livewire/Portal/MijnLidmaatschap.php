<?php

namespace App\Livewire\Portal;

use App\Enums\ChangeType;
use App\Enums\MembershipStatus;
use App\Models\Household;
use App\Models\IceContact;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\PersonFieldVisibility;
use App\Services\Audit\AuditLogger;
use App\Services\Proposals\Handlers\PersonFieldUpdateHandler;
use App\Services\Proposals\ProposalEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Mijn lidmaatschap" — besloten scherm voor een ingelogd lid (§19.1, §19.4).
 *
 * Verantwoordelijkheden:
 *   - Toon eigen persoonsgegevens, huishouden-adres en huidig lidmaatschap.
 *   - Wijzig niet-gevoelige velden (phone/email/adres) direct met audit-log.
 *   - Dien wijzigingen op gevoelige velden in als Proposal via ProposalEngine.
 *   - Beheer per veld de zichtbaarheid voor andere leden (privacy-first).
 *   - Zeg het huidige lidmaatschap op (status Cancelled + einddatum vandaag).
 *   - Beheer eigen ICE-contacten (aanmaken/wijzigen/verwijderen).
 */
#[Layout('layouts.app')]
class MijnLidmaatschap extends Component
{
    /**
     * Velden die het lid direct mag wijzigen (Person + Household). Zie
     * PersonFieldUpdateHandler::SENSITIVE_FIELDS voor de tegenhanger.
     */
    public const array PERSON_DIRECT_FIELDS = ['email', 'phone'];

    public const array HOUSEHOLD_DIRECT_FIELDS = ['street', 'house_number', 'postal_code', 'city'];

    /**
     * Velden waarvoor een lid de zichtbaarheid naar andere leden kan
     * omschakelen. Voor deze PR hardcoded — later gedreven door de
     * FIELD_DEFINITION-tabel (§19.3, Agent B).
     *
     * @var list<string>
     */
    public const array VISIBILITY_TOGGLE_FIELDS = ['email', 'phone', 'date_of_birth'];

    /** @var array<string, string> */
    public array $person = [];

    /** @var array<string, string> */
    public array $household = [];

    /** @var array<string, bool> */
    public array $visibility = [];

    // Bevestigingsdialogen
    public bool $confirmCancelMembership = false;

    // ICE-contact editor
    public ?int $editingIceContactId = null;

    /** @var array<string, string> */
    public array $iceForm = [
        'name' => '',
        'relation' => '',
        'phone' => '',
        'email' => '',
        'notes' => '',
    ];

    public bool $iceFormOpen = false;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $person = $this->currentPerson();
        abort_if($person === null, 403, 'Je account is niet gekoppeld aan een persoon.');

        $this->person = [
            'email' => (string) ($person->email ?? ''),
            'phone' => (string) ($person->phone ?? ''),
        ];

        $household = $person->household;
        $this->household = [
            'street' => (string) ($household->street ?? ''),
            'house_number' => (string) ($household->house_number ?? ''),
            'postal_code' => (string) ($household->postal_code ?? ''),
            'city' => (string) ($household->city ?? ''),
        ];

        $storedVisibility = PersonFieldVisibility::query()
            ->where('person_id', $person->id)
            ->pluck('visible_to_members', 'field_key')
            ->all();

        foreach (self::VISIBILITY_TOGGLE_FIELDS as $field) {
            $this->visibility[$field] = (bool) ($storedVisibility[$field] ?? false);
        }
    }

    /**
     * Sla een niet-gevoelig persoons- of huishoudveld direct op.
     * Voor gevoelige velden gebruik je submitSensitive().
     */
    public function saveDirect(string $scope, string $field, AuditLogger $audit): void
    {
        $person = $this->requirePerson();

        if ($scope === 'person' && in_array($field, self::PERSON_DIRECT_FIELDS, true)) {
            $newValue = $this->trimOrNull($this->person[$field] ?? null);

            $this->validatePersonField($field, $newValue);

            $before = [$field => $person->{$field}];
            if ($person->{$field} === $newValue) {
                return;
            }

            DB::transaction(function () use ($person, $field, $newValue, $before, $audit) {
                $person->{$field} = $newValue;
                $person->save();

                $audit->log(
                    'person.field_updated',
                    $person,
                    before: $before,
                    after: [$field => $newValue],
                    context: ['direct' => true, 'field' => $field],
                );
            });

            $this->statusMessage = 'Je gegevens zijn opgeslagen.';

            return;
        }

        if ($scope === 'household' && in_array($field, self::HOUSEHOLD_DIRECT_FIELDS, true)) {
            $household = $person->household;
            if ($household === null) {
                $household = Household::create([
                    'name' => trim(($person->first_name).' '.$person->last_name),
                ]);
                $person->household_id = $household->id;
                $person->save();
            }

            $newValue = $this->trimOrNull($this->household[$field] ?? null);
            $before = [$field => $household->{$field}];
            if ($household->{$field} === $newValue) {
                return;
            }

            DB::transaction(function () use ($household, $field, $newValue, $before, $audit) {
                $household->{$field} = $newValue;
                $household->save();

                $audit->log(
                    'household.field_updated',
                    $household,
                    before: $before,
                    after: [$field => $newValue],
                    context: ['field' => $field],
                );
            });

            $this->statusMessage = 'Je adres is opgeslagen.';

            return;
        }

        abort(422, "Veld [{$scope}.{$field}] mag niet direct worden opgeslagen.");
    }

    /**
     * Dien een wijziging op een gevoelig veld in als Proposal. De
     * ProposalEngine kiest zelf tussen bypass, auto-apply of review.
     */
    public function submitSensitive(string $field, mixed $newValue, ProposalEngine $engine): void
    {
        $person = $this->requirePerson();

        if (! in_array($field, PersonFieldUpdateHandler::SENSITIVE_FIELDS, true)) {
            abort(422, "Veld [{$field}] is niet gevoelig — gebruik saveDirect().");
        }

        if ($field === 'date_of_birth') {
            $newValue = $newValue === null || $newValue === '' ? null : Carbon::parse((string) $newValue)->toDateString();
            $oldValue = $person->date_of_birth?->toDateString();
        } elseif ($field === 'membership_type_id') {
            $newValue = $newValue === null || $newValue === '' ? null : (int) $newValue;
            $oldValue = $person->currentMembership()?->membership_type_id;
        } else {
            $newValue = $this->trimOrNull($newValue);
            $oldValue = $person->{$field};
        }

        if ($newValue === $oldValue) {
            $this->statusMessage = 'Er is niets veranderd.';

            return;
        }

        $engine->submit(
            subjectType: PersonFieldUpdateHandler::SUBJECT_TYPE,
            changeType: ChangeType::Update,
            payload: [
                'person_id' => $person->id,
                'field' => $field,
                'new_value' => $newValue,
                'old_value' => $oldValue,
            ],
            proposer: $person,
            subjectId: $person->id,
        );

        $this->statusMessage = 'Je wijziging is ingediend en wordt beoordeeld.';
    }

    /**
     * Wijzig de zichtbaarheid van één veld naar andere leden en persisteer
     * direct.
     */
    public function toggleVisibility(string $field, AuditLogger $audit): void
    {
        if (! in_array($field, self::VISIBILITY_TOGGLE_FIELDS, true)) {
            abort(422, "Veld [{$field}] kent geen zichtbaarheidstoggle.");
        }

        $person = $this->requirePerson();
        $newValue = ! ($this->visibility[$field] ?? false);
        $this->visibility[$field] = $newValue;

        DB::transaction(function () use ($person, $field, $newValue, $audit) {
            $record = PersonFieldVisibility::updateOrCreate(
                ['person_id' => $person->id, 'field_key' => $field],
                ['visible_to_members' => $newValue],
            );

            $audit->log(
                'person.field_visibility_updated',
                $record,
                after: ['field_key' => $field, 'visible_to_members' => $newValue],
                context: ['field' => $field],
            );
        });
    }

    /**
     * Zeg het huidige lopende lidmaatschap op. §14 (opzegtermijn, restitutie,
     * pro-rata) is uitdrukkelijk uit scope voor deze PR — dat volgt met de
     * facturatie-module.
     */
    public function cancelMembership(AuditLogger $audit): void
    {
        $person = $this->requirePerson();
        $current = $person->currentMembership();

        if ($current === null) {
            $this->confirmCancelMembership = false;
            $this->statusMessage = 'Je hebt geen actief lidmaatschap dat opgezegd kan worden.';

            return;
        }

        DB::transaction(function () use ($current, $audit) {
            $before = [
                'status' => $current->status->value,
                'end_date' => $current->end_date?->toDateString(),
            ];

            $current->status = MembershipStatus::Cancelled;
            $current->end_date = Carbon::today();
            $current->save();

            $audit->log(
                'membership.cancelled',
                $current,
                before: $before,
                after: [
                    'status' => $current->status->value,
                    'end_date' => $current->end_date->toDateString(),
                ],
            );
        });

        $this->confirmCancelMembership = false;
        $this->statusMessage = 'Je lidmaatschap is opgezegd.';
    }

    // --- ICE-contacten ------------------------------------------------------

    public function openIceForm(?int $iceContactId = null): void
    {
        $this->iceFormOpen = true;
        $this->editingIceContactId = $iceContactId;

        if ($iceContactId === null) {
            $this->iceForm = [
                'name' => '',
                'relation' => '',
                'phone' => '',
                'email' => '',
                'notes' => '',
            ];

            return;
        }

        $person = $this->requirePerson();
        $contact = IceContact::query()->where('person_id', $person->id)->findOrFail($iceContactId);

        $this->iceForm = [
            'name' => (string) $contact->name,
            'relation' => (string) $contact->relation,
            'phone' => (string) $contact->phone,
            'email' => (string) ($contact->email ?? ''),
            'notes' => (string) ($contact->notes ?? ''),
        ];
    }

    public function closeIceForm(): void
    {
        $this->iceFormOpen = false;
        $this->editingIceContactId = null;
    }

    public function saveIceContact(AuditLogger $audit): void
    {
        $person = $this->requirePerson();

        $this->validate([
            'iceForm.name' => ['required', 'string', 'max:255'],
            'iceForm.relation' => ['required', 'string', 'max:100'],
            'iceForm.phone' => ['required', 'string', 'max:50'],
            'iceForm.email' => ['nullable', 'email', 'max:255'],
            'iceForm.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($person, $audit) {
            if ($this->editingIceContactId === null) {
                $contact = IceContact::create([
                    'person_id' => $person->id,
                    'name' => $this->iceForm['name'],
                    'relation' => $this->iceForm['relation'],
                    'phone' => $this->iceForm['phone'],
                    'email' => $this->trimOrNull($this->iceForm['email']),
                    'notes' => $this->trimOrNull($this->iceForm['notes']),
                ]);

                $audit->log(
                    'ice_contact.created',
                    $contact,
                    after: $contact->only(['name', 'relation', 'phone', 'email']),
                );

                return;
            }

            $contact = IceContact::query()->where('person_id', $person->id)->findOrFail($this->editingIceContactId);
            $before = $contact->only(['name', 'relation', 'phone', 'email', 'notes']);

            $contact->fill([
                'name' => $this->iceForm['name'],
                'relation' => $this->iceForm['relation'],
                'phone' => $this->iceForm['phone'],
                'email' => $this->trimOrNull($this->iceForm['email']),
                'notes' => $this->trimOrNull($this->iceForm['notes']),
            ])->save();

            $audit->log(
                'ice_contact.updated',
                $contact,
                before: $before,
                after: $contact->only(['name', 'relation', 'phone', 'email', 'notes']),
            );
        });

        $this->statusMessage = 'ICE-contact opgeslagen.';
        $this->closeIceForm();
    }

    public function deleteIceContact(int $iceContactId, AuditLogger $audit): void
    {
        $person = $this->requirePerson();
        $contact = IceContact::query()->where('person_id', $person->id)->findOrFail($iceContactId);

        DB::transaction(function () use ($contact, $audit) {
            $snapshot = $contact->only(['name', 'relation', 'phone', 'email', 'notes']);
            $contact->delete();

            $audit->log(
                'ice_contact.deleted',
                $contact,
                before: $snapshot,
                context: ['ice_contact_id' => $contact->id],
            );
        });

        $this->statusMessage = 'ICE-contact verwijderd.';
    }

    // --- Rendering ----------------------------------------------------------

    /**
     * @return Collection<int, IceContact>
     */
    #[Computed]
    public function iceContacts(): Collection
    {
        $person = $this->currentPerson();
        if ($person === null) {
            /** @var Collection<int, IceContact> $empty */
            $empty = new Collection;

            return $empty;
        }

        return $person->iceContacts()->orderBy('name')->get();
    }

    #[Computed]
    public function currentMembership(): ?Membership
    {
        return $this->currentPerson()?->currentMembership();
    }

    /**
     * @return Collection<int, MembershipType>
     */
    #[Computed]
    public function membershipTypes(): Collection
    {
        return MembershipType::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function render(): View
    {
        $person = $this->currentPerson();

        return view('livewire.portal.mijn-lidmaatschap', [
            'personModel' => $person,
            'currentMembership' => $this->currentMembership(),
            'iceContacts' => $this->iceContacts(),
            'membershipTypes' => $this->membershipTypes(),
        ]);
    }

    // --- Hulpfuncties -------------------------------------------------------

    private function currentPerson(): ?Person
    {
        return auth()->user()?->person;
    }

    private function requirePerson(): Person
    {
        $person = $this->currentPerson();
        abort_if($person === null, 403, 'Je account is niet gekoppeld aan een persoon.');

        return $person;
    }

    private function trimOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    /**
     * Handmatige validatie voor de directe-opslag-velden. We valideren via
     * Livewire's eigen mechaniek zodat foutmeldingen op de juiste key
     * (`person.email` etc.) terechtkomen.
     */
    private function validatePersonField(string $field, ?string $value): void
    {
        $key = "person.{$field}";
        $rules = match ($field) {
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            default => ['nullable', 'string', 'max:255'],
        };

        $this->validate([$key => $rules]);
    }
}
