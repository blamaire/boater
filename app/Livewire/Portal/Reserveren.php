<?php

namespace App\Livewire\Portal;

use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationStatus;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Services\Reservations\ReservationRuleEvaluator;
use App\Services\Reservations\ReservationService;
use App\Services\Reservations\ReservationSubmissionService;
use App\Services\Reservations\RuleViolation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Portaal-scherm reserveren (§18). Toont een dag-kalender met alle
 * objecten (filterbaar op categorie), een reserveerformulier met
 * kwartier-precisie en live signalen wanneer een aanvraag een
 * drempel overschrijdt (die aanvraag mag doorgaan — ze gaat dan via
 * de goedkeuringsmotor).
 */
#[Layout('layouts.app', ['header' => 'Reserveren'])]
class Reserveren extends Component
{
    public ?int $filterCategoryId = null;

    public string $viewDate = '';

    /**
     * Formuliermodus: 'object' = specifiek object; 'category' = beschikbaar object
     * van een categorie (systeem wijst toe bij bevestigen).
     */
    public string $mode = 'object';

    public ?int $selectedObjectId = null;

    public ?int $selectedCategoryId = null;

    public string $startsAt = '';

    public string $endsAt = '';

    public ?int $selectedPersonId = null;

    public string $note = '';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    /**
     * Live drempel-signalen. Elke entry: ['rule_name' => …, 'message' => …].
     *
     * @var array<int, array{rule_name:string,message:string}>
     */
    public array $liveViolations = [];

    public function mount(): void
    {
        $this->selectedPersonId = auth()->user()?->person?->id;
        $this->viewDate = Carbon::now()->toDateString();
        $this->startsAt = Carbon::now()->addHour()->startOfHour()->format('Y-m-d\TH:i');
        $this->endsAt = Carbon::now()->addHours(2)->startOfHour()->format('Y-m-d\TH:i');
    }

    public function shiftDay(int $days): void
    {
        $this->viewDate = Carbon::parse($this->viewDate)->addDays($days)->toDateString();
    }

    public function today(): void
    {
        $this->viewDate = Carbon::now()->toDateString();
    }

    /**
     * Klik op een kalendercel: object + starttijd voorvullen, eindtijd
     * standaard +1 uur.
     */
    public function pickSlot(int $objectId, string $isoStart): void
    {
        $this->mode = 'object';
        $this->selectedObjectId = $objectId;
        $this->selectedCategoryId = null;
        $this->startsAt = Carbon::parse($isoStart)->format('Y-m-d\TH:i');
        $this->endsAt = Carbon::parse($isoStart)->addHour()->format('Y-m-d\TH:i');
        $this->errorMessage = null;
        $this->recomputeLiveViolations();
    }

    public function updatedSelectedObjectId(): void
    {
        $this->recomputeLiveViolations();
    }

    public function updatedSelectedCategoryId(): void
    {
        $this->recomputeLiveViolations();
    }

    public function updatedStartsAt(): void
    {
        $this->recomputeLiveViolations();
    }

    public function updatedEndsAt(): void
    {
        $this->recomputeLiveViolations();
    }

    public function updatedSelectedPersonId(): void
    {
        $this->recomputeLiveViolations();
    }

    public function updatedMode(): void
    {
        // Wisselen tussen "specifiek" en "categorie" zet de andere leeg zodat
        // de validatieregels niet met leftover-ids gevoed worden.
        if ($this->mode === 'object') {
            $this->selectedCategoryId = null;
        } else {
            $this->selectedObjectId = null;
        }
        $this->recomputeLiveViolations();
    }

    private function recomputeLiveViolations(): void
    {
        $this->liveViolations = [];

        if ($this->startsAt === '' || $this->endsAt === '' || $this->selectedPersonId === null) {
            return;
        }

        try {
            $start = Carbon::parse($this->startsAt);
            $end = Carbon::parse($this->endsAt);
        } catch (\Throwable) {
            return;
        }
        if ($start->greaterThanOrEqualTo($end)) {
            return;
        }

        $target = Person::query()->find($this->selectedPersonId);
        if ($target === null) {
            return;
        }

        $object = null;
        if ($this->mode === 'object' && $this->selectedObjectId !== null) {
            $object = ReservableObject::query()->with('category')->find($this->selectedObjectId);
        } elseif ($this->mode === 'category' && $this->selectedCategoryId !== null) {
            // Voor de live-check kiezen we een representant uit de categorie
            // (categorie zelf is genoeg voor de rule-evaluator via z'n object).
            $object = ReservableObject::query()
                ->where('object_category_id', $this->selectedCategoryId)
                ->with('category')
                ->first();
        }

        if ($object === null) {
            return;
        }

        $violations = app(ReservationRuleEvaluator::class)->evaluate($object, $target, $start, $end);
        $this->liveViolations = $violations
            ->map(fn (RuleViolation $v): array => [
                'rule_name' => $v->rule->name,
                'message' => $v->message,
            ])
            ->all();
    }

    public function reserve(ReservationSubmissionService $service): void
    {
        $this->errorMessage = null;

        $rules = [
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'selectedPersonId' => ['required', 'integer', 'exists:persons,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
        if ($this->mode === 'object') {
            $rules['selectedObjectId'] = ['required', 'integer', 'exists:reservable_objects,id'];
        } else {
            $rules['selectedCategoryId'] = ['required', 'integer', 'exists:object_categories,id'];
        }
        $this->validate($rules);

        $user = auth()->user();
        if ($user === null || $user->person === null) {
            $this->errorMessage = 'Log opnieuw in om te reserveren.';

            return;
        }

        $target = Person::query()->findOrFail($this->selectedPersonId);
        $object = $this->mode === 'object'
            ? ReservableObject::query()->findOrFail($this->selectedObjectId)
            : null;
        $category = $this->mode === 'category'
            ? ObjectCategory::query()->findOrFail($this->selectedCategoryId)
            : null;

        try {
            $outcome = $service->submit(
                $object,
                $category,
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

        $this->statusMessage = $outcome->wasReviewed()
            ? 'Je aanvraag is ingediend voor goedkeuring. Je krijgt bericht zodra er een besluit is.'
            : ($object !== null
                ? "Reservering vastgelegd voor [{$object->name}]."
                : 'Reservering vastgelegd.');

        $this->reset(['selectedObjectId', 'selectedCategoryId', 'note']);
        $this->liveViolations = [];
    }

    public function cancel(int $reservationId, ReservationService $service): void
    {
        $user = auth()->user();
        if ($user === null || $user->person === null) {
            return;
        }

        $reservation = Reservation::query()->findOrFail($reservationId);
        if ($reservation->person_id !== $user->person->id && $reservation->requested_by_person_id !== $user->person->id) {
            $this->errorMessage = 'Je mag alleen je eigen reserveringen intrekken.';

            return;
        }

        $service->cancel($reservation, $user->person);
        $this->statusMessage = 'Reservering ingetrokken.';
    }

    public function render(): View
    {
        $user = auth()->user();
        $ownPerson = $user?->person;

        $categoryFilter = fn ($q) => $this->filterCategoryId !== null
            ? $q->where('object_category_id', $this->filterCategoryId)
            : $q;

        $objects = $categoryFilter(
            ReservableObject::query()
                ->with('category')
                ->orderBy('sort_order')
                ->orderBy('name')
        )->get();

        // Reserveringen op de gekozen kalenderdag, alleen bevestigd, per object.
        $day = Carbon::parse($this->viewDate);
        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        $dayReservations = Reservation::query()
            ->with('person')
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereIn('reservable_object_id', $objects->pluck('id'))
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get()
            ->groupBy('reservable_object_id');

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
            'availableObjects' => $objects->filter(fn (ReservableObject $o) => $o->status === ReservableObjectStatus::Available)->values(),
            'categories' => ObjectCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'eligible' => $eligible,
            'myReservations' => $myReservations,
            'day' => $day,
            'hours' => $this->calendarHours(),
            'dayReservationsByObject' => $dayReservations,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function calendarHours(): array
    {
        return range(6, 22);
    }
}
