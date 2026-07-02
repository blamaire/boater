<?php

namespace App\Livewire\Admin;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * §19.2 — Detail-editor voor de ledenadministratie: bewerkt Person-velden
 * (ook gevoelige — administratie ziet en wijzigt alles direct, zonder
 * goedkeuringsroute, §21.3 "Onderscheid met Ledenbeheer") en beheert
 * de bijbehorende lidmaatschappen.
 */
class LedenBeheer extends Component
{
    public int $personId;

    // Persoonsgegevens (bewerkbaar)
    public string $first_name = '';

    public ?string $last_name_prefix = null;

    public string $last_name = '';

    public ?string $date_of_birth = null;

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $status = null;

    // Formulier "nieuw lidmaatschap toekennen"
    public ?int $newMembershipTypeId = null;

    public ?string $newMembershipStartDate = null;

    // Formulier "lidmaatschap beëindigen"
    public ?int $endingMembershipId = null;

    public ?string $endingMembershipEndDate = null;

    public function mount(int $personId): void
    {
        $this->personId = $personId;
        $this->hydrateFromPerson();
    }

    private function hydrateFromPerson(): void
    {
        $person = $this->person();
        $this->first_name = $person->first_name;
        $this->last_name_prefix = $person->last_name_prefix;
        $this->last_name = $person->last_name;
        $this->date_of_birth = $person->date_of_birth?->format('Y-m-d');
        $this->email = $person->email;
        $this->phone = $person->phone;
        $this->status = $person->status;
    }

    #[Computed]
    public function person(): Person
    {
        return Person::query()
            ->with(['memberships.type', 'household'])
            ->findOrFail($this->personId);
    }

    #[Computed]
    public function membershipTypes(): Collection
    {
        return MembershipType::query()->orderBy('sort_order')->get();
    }

    /**
     * Sla wijzigingen aan persoonsgegevens op met audit-log.
     */
    public function savePerson(AuditLogger $audit): void
    {
        $data = $this->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name_prefix' => ['nullable', 'string', 'max:20'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        DB::transaction(function () use ($data, $audit): void {
            $person = Person::query()->findOrFail($this->personId);
            $before = $person->only(array_keys($data));
            $person->fill($data);
            $person->save();
            $after = $person->only(array_keys($data));

            $audit->log(
                action: 'person.updated',
                subject: $person,
                before: $before,
                after: $after,
                context: ['via' => 'ledenadministratie'],
            );
        });

        unset($this->person);
        session()->flash('status', 'Wijzigingen opgeslagen.');
    }

    /**
     * Ken een lidmaatschap toe aan deze persoon (status: actief).
     */
    public function grantMembership(AuditLogger $audit): void
    {
        $data = $this->validate([
            'newMembershipTypeId' => ['required', 'integer', Rule::exists('membership_types', 'id')],
            'newMembershipStartDate' => ['nullable', 'date'],
        ], [
            'newMembershipTypeId.required' => 'Kies een lidmaatschapsvorm.',
        ]);

        DB::transaction(function () use ($data, $audit): void {
            $membership = Membership::create([
                'person_id' => $this->personId,
                'membership_type_id' => $data['newMembershipTypeId'],
                'status' => MembershipStatus::Active,
                'start_date' => $data['newMembershipStartDate'] ?? Carbon::today()->toDateString(),
            ]);

            $audit->log(
                action: 'membership.granted',
                subject: $membership,
                after: [
                    'person_id' => $membership->person_id,
                    'membership_type_id' => $membership->membership_type_id,
                    'status' => $membership->status->value,
                    'start_date' => optional($membership->start_date)->toDateString(),
                ],
                context: ['via' => 'ledenadministratie'],
            );
        });

        $this->newMembershipTypeId = null;
        $this->newMembershipStartDate = null;
        unset($this->person);
        session()->flash('status', 'Lidmaatschap toegekend.');
    }

    /**
     * Wijzig de status van een lidmaatschap.
     */
    public function updateMembershipStatus(int $membershipId, string $status, AuditLogger $audit): void
    {
        $enumStatus = MembershipStatus::from($status);

        DB::transaction(function () use ($membershipId, $enumStatus, $audit): void {
            $membership = Membership::query()
                ->where('person_id', $this->personId)
                ->findOrFail($membershipId);

            $before = ['status' => $membership->status->value];
            $membership->status = $enumStatus;
            $membership->save();

            $audit->log(
                action: 'membership.status_changed',
                subject: $membership,
                before: $before,
                after: ['status' => $enumStatus->value],
                context: ['via' => 'ledenadministratie'],
            );
        });

        unset($this->person);
        session()->flash('status', 'Status van lidmaatschap gewijzigd.');
    }

    /**
     * Beëindig een lidmaatschap per einddatum (status → opgezegd).
     */
    public function endMembership(AuditLogger $audit): void
    {
        $data = $this->validate([
            'endingMembershipId' => ['required', 'integer'],
            'endingMembershipEndDate' => ['required', 'date'],
        ], [
            'endingMembershipEndDate.required' => 'Kies een einddatum.',
        ]);

        DB::transaction(function () use ($data, $audit): void {
            $membership = Membership::query()
                ->where('person_id', $this->personId)
                ->findOrFail($data['endingMembershipId']);

            $before = [
                'status' => $membership->status->value,
                'end_date' => optional($membership->end_date)->toDateString(),
            ];

            $membership->status = MembershipStatus::Cancelled;
            $membership->end_date = Carbon::parse($data['endingMembershipEndDate']);
            $membership->save();

            $audit->log(
                action: 'membership.ended',
                subject: $membership,
                before: $before,
                after: [
                    'status' => $membership->status->value,
                    'end_date' => optional($membership->end_date)->toDateString(),
                ],
                context: ['via' => 'ledenadministratie'],
            );
        });

        $this->endingMembershipId = null;
        $this->endingMembershipEndDate = null;
        unset($this->person);
        session()->flash('status', 'Lidmaatschap beëindigd.');
    }

    public function render(): View
    {
        return view('livewire.admin.leden-beheer', [
            'statussen' => MembershipStatus::cases(),
        ]);
    }
}
