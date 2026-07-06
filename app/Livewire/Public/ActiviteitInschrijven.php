<?php

namespace App\Livewire\Public;

use App\Enums\EnrollmentStatus;
use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\Person;
use App\Services\Activities\EnrollmentService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

/**
 * Inschrijfknop op de publieke activiteit-detailpagina. Toont de status van
 * de huidige gebruiker en biedt "voor wie schrijf je in?" — jezelf of een
 * gekoppeld kind (via `person_relations`, bv. ouder_verzorger-relatie).
 */
class ActiviteitInschrijven extends Component
{
    public int $activityId;

    public ?int $selectedPersonId = null;

    public ?string $statusMessage = null;

    public function mount(int $activityId): void
    {
        $this->activityId = $activityId;
        $this->selectedPersonId = auth()->user()?->person?->id;
    }

    public function enroll(EnrollmentService $service): void
    {
        $user = auth()->user();
        if ($user === null || $user->person === null) {
            $this->statusMessage = 'Log in om je in te schrijven.';

            return;
        }

        $activity = Activity::query()->findOrFail($this->activityId);
        $target = $this->selectedPersonId !== null
            ? Person::query()->findOrFail($this->selectedPersonId)
            : $user->person;

        if ($target->id !== $user->person->id && ! $this->mayEnrollFor($user->person, $target)) {
            $this->statusMessage = 'Je hebt geen toestemming om deze persoon in te schrijven.';

            return;
        }

        try {
            $service->enroll($activity, $target, $user->person);
        } catch (RuntimeException $e) {
            $this->statusMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = 'Inschrijving geregistreerd.';
    }

    public function cancel(EnrollmentService $service): void
    {
        $user = auth()->user();
        if ($user === null || $user->person === null) {
            return;
        }

        $target = $this->selectedPersonId !== null
            ? Person::query()->findOrFail($this->selectedPersonId)
            : $user->person;

        $enrollment = Enrollment::query()
            ->where('activity_id', $this->activityId)
            ->where('person_id', $target->id)
            ->whereIn('status', [EnrollmentStatus::Enrolled->value, EnrollmentStatus::Waitlist->value])
            ->first();

        if ($enrollment === null) {
            $this->statusMessage = 'Geen actieve inschrijving gevonden.';

            return;
        }

        $service->cancel($enrollment, $user->person);
        $this->statusMessage = 'Afgemeld.';
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
        $activity = Activity::query()->with('enrollments')->findOrFail($this->activityId);
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

        $currentEnrollment = null;
        if ($this->selectedPersonId !== null) {
            $currentEnrollment = Enrollment::query()
                ->where('activity_id', $this->activityId)
                ->where('person_id', $this->selectedPersonId)
                ->whereIn('status', [EnrollmentStatus::Enrolled->value, EnrollmentStatus::Waitlist->value])
                ->first();
        }

        return view('livewire.public.activiteit-inschrijven', [
            'activity' => $activity,
            'eligible' => $eligible,
            'currentEnrollment' => $currentEnrollment,
        ]);
    }
}
