<?php

namespace App\Livewire\Public;

use App\Enums\EnrollmentLevel;
use App\Enums\EnrollmentStatus;
use App\Models\ActivitySeries;
use App\Models\Enrollment;
use App\Models\Person;
use App\Services\Activities\EnrollmentService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

/**
 * Serie-brede inschrijfknop op de publieke reeks-overzichtpagina: schrijft in
 * één keer in op alle (gepubliceerde) voorkomens van de reeks (§17.4).
 */
class SerieInschrijven extends Component
{
    public int $seriesId;

    public ?int $selectedPersonId = null;

    public ?string $statusMessage = null;

    public function mount(int $seriesId): void
    {
        $this->seriesId = $seriesId;
        $this->selectedPersonId = auth()->user()?->person?->id;
    }

    public function enroll(EnrollmentService $service): void
    {
        $user = auth()->user();
        if ($user === null || $user->person === null) {
            $this->statusMessage = 'Log in om je in te schrijven.';

            return;
        }

        $series = ActivitySeries::query()->findOrFail($this->seriesId);
        $target = $this->selectedPersonId !== null
            ? Person::query()->findOrFail($this->selectedPersonId)
            : $user->person;

        if ($target->id !== $user->person->id && ! $this->mayEnrollFor($user->person, $target)) {
            $this->statusMessage = 'Je hebt geen toestemming om deze persoon in te schrijven.';

            return;
        }

        try {
            $created = $service->enrollSeries($series, $target, $user->person);
        } catch (RuntimeException $e) {
            $this->statusMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = $created->isEmpty()
            ? 'Je was al voor alle voorkomens ingeschreven.'
            : 'Inschrijving voor de hele reeks geregistreerd ('.$created->count().' voorkomen(s)).';
    }

    public function cancel(EnrollmentService $service): void
    {
        $user = auth()->user();
        if ($user === null || $user->person === null) {
            return;
        }

        $series = ActivitySeries::query()->findOrFail($this->seriesId);
        $target = $this->selectedPersonId !== null
            ? Person::query()->findOrFail($this->selectedPersonId)
            : $user->person;

        $count = $service->cancelSeries($series, $target, $user->person);
        $this->statusMessage = $count > 0 ? 'Afgemeld voor de hele reeks.' : 'Geen actieve serie-inschrijving gevonden.';
    }

    private function mayEnrollFor(Person $actor, Person $target): bool
    {
        return $actor->relations()
            ->where('related_person_id', $target->id)
            ->whereIn('type', ['ouder_van', 'verzorger_van'])
            ->exists();
    }

    public function render(): View
    {
        $series = ActivitySeries::query()->findOrFail($this->seriesId);
        $user = auth()->user();
        $ownPerson = $user?->person;

        $eligible = collect();
        if ($ownPerson !== null) {
            $eligible = collect([$ownPerson]);
            $wards = Person::query()
                ->whereIn('id', $ownPerson->relations()
                    ->whereIn('type', ['ouder_van', 'verzorger_van'])
                    ->pluck('related_person_id'))
                ->get();
            $eligible = $eligible->merge($wards);
        }

        $activeCount = 0;
        if ($this->selectedPersonId !== null) {
            $activeCount = Enrollment::query()
                ->where('series_id', $series->id)
                ->where('person_id', $this->selectedPersonId)
                ->where('level', EnrollmentLevel::Reeks->value)
                ->whereIn('status', [EnrollmentStatus::Enrolled->value, EnrollmentStatus::Waitlist->value])
                ->count();
        }

        return view('livewire.public.serie-inschrijven', [
            'series' => $series,
            'eligible' => $eligible,
            'activeCount' => $activeCount,
        ]);
    }
}
