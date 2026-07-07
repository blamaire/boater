<?php

namespace App\Livewire\Portal;

use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationStatus;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Services\Reservations\ReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Portaal-scherm voor leden om objecten te reserveren. Toont:
 *  - eigen actieve reserveringen (met annuleer-knop);
 *  - een lijst met filter op categorie en datumbereik;
 *  - per object een compact reserveerformulier.
 */
#[Layout('layouts.app', ['header' => 'Reserveren'])]
class Reserveren extends Component
{
    public ?int $filterCategoryId = null;

    public string $filterFrom = '';

    public string $filterTo = '';

    public ?int $selectedObjectId = null;

    public string $startsAt = '';

    public string $endsAt = '';

    public ?int $selectedPersonId = null;

    public string $note = '';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->selectedPersonId = auth()->user()?->person?->id;
        $this->filterFrom = Carbon::now()->startOfDay()->format('Y-m-d\TH:i');
        $this->filterTo = Carbon::now()->addDays(14)->endOfDay()->format('Y-m-d\TH:i');
    }

    public function openForm(int $objectId): void
    {
        $this->selectedObjectId = $objectId;
        $this->startsAt = Carbon::now()->addHour()->startOfHour()->format('Y-m-d\TH:i');
        $this->endsAt = Carbon::now()->addHours(3)->startOfHour()->format('Y-m-d\TH:i');
        $this->note = '';
        $this->errorMessage = null;
    }

    public function closeForm(): void
    {
        $this->selectedObjectId = null;
    }

    public function reserve(ReservationService $service): void
    {
        $this->errorMessage = null;

        $this->validate([
            'selectedObjectId' => ['required', 'integer', 'exists:reservable_objects,id'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'selectedPersonId' => ['required', 'integer', 'exists:persons,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = auth()->user();
        if ($user === null || $user->person === null) {
            $this->errorMessage = 'Log opnieuw in om te reserveren.';

            return;
        }

        $target = Person::query()->findOrFail($this->selectedPersonId);
        if ($target->id !== $user->person->id && ! $this->mayReserveFor($user->person, $target)) {
            $this->errorMessage = 'Je hebt geen toestemming om deze persoon te reserveren.';

            return;
        }

        $object = ReservableObject::query()->findOrFail($this->selectedObjectId);

        try {
            $service->reserve(
                $object,
                $target,
                Carbon::parse($this->startsAt),
                Carbon::parse($this->endsAt),
                $user->person,
                $this->note !== '' ? $this->note : null,
            );
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = "Reservering vastgelegd voor [{$object->name}].";
        $this->selectedObjectId = null;
    }

    public function cancel(int $reservationId, ReservationService $service): void
    {
        $user = auth()->user();
        if ($user === null || $user->person === null) {
            return;
        }

        $reservation = Reservation::query()->findOrFail($reservationId);

        // Alleen intrekken toegestaan als je de begunstigde of de aanvrager bent.
        if ($reservation->person_id !== $user->person->id && $reservation->requested_by_person_id !== $user->person->id) {
            $this->errorMessage = 'Je mag alleen je eigen reserveringen intrekken.';

            return;
        }

        $service->cancel($reservation, $user->person);
        $this->statusMessage = 'Reservering ingetrokken.';
    }

    private function mayReserveFor(Person $actor, Person $target): bool
    {
        return $actor->relations()
            ->where('related_person_id', $target->id)
            ->whereIn('type', ['ouder_van', 'verzorger_van'])
            ->exists();
    }

    public function render(): View
    {
        $user = auth()->user();
        $ownPerson = $user?->person;

        // Beschikbare objecten in het gekozen tijdsraam.
        $objectQuery = ReservableObject::query()
            ->with('category')
            ->where('status', ReservableObjectStatus::Available->value)
            ->orderBy('name');

        if ($this->filterCategoryId !== null) {
            $objectQuery->where('object_category_id', $this->filterCategoryId);
        }

        $objects = $objectQuery->get();

        // Personen waarvoor de ingelogde gebruiker kan reserveren.
        $eligible = collect();
        if ($ownPerson !== null) {
            $eligible = collect([$ownPerson]);
            $wardIds = $ownPerson->relations()
                ->whereIn('type', ['ouder_van', 'verzorger_van'])
                ->pluck('related_person_id');
            $wards = Person::query()->whereIn('id', $wardIds)->get();
            $eligible = $eligible->merge($wards);
        }

        // Eigen actieve reserveringen (voor mij én voor gemachtigden).
        $myReservations = collect();
        if ($ownPerson !== null) {
            $myReservations = Reservation::query()
                ->with(['object.category', 'person'])
                ->where('status', ReservationStatus::Confirmed->value)
                ->where('ends_at', '>=', Carbon::now())
                ->where(function ($q) use ($ownPerson): void {
                    $q->where('person_id', $ownPerson->id)
                        ->orWhere('requested_by_person_id', $ownPerson->id);
                })
                ->orderBy('starts_at')
                ->get();
        }

        return view('livewire.portal.reserveren', [
            'objects' => $objects,
            'categories' => ObjectCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'eligible' => $eligible,
            'myReservations' => $myReservations,
        ]);
    }
}
