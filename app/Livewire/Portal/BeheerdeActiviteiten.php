<?php

namespace App\Livewire\Portal;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Services\Activities\ActivityManagerNotifier;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portaalscherm voor gedelegeerde activiteitbeheerders (`Activity::managers()`,
 * §6): zien inschrijvingen en mogen basisvelden wijzigen zonder de globale
 * `activities.update`-permissie — dus buiten `/beheer` (die route is
 * permissie-gated). Structurele wijzigingen (categorie, reeks, activiteiten-
 * pagina) blijven voorbehouden aan `/beheer/activiteiten`.
 */
#[Layout('layouts.app', ['header' => 'Mijn beheerde activiteiten'])]
class BeheerdeActiviteiten extends Component
{
    public ?int $editingId = null;

    public string $title = '';

    public string $description = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $location = '';

    public ?int $capacity = null;

    public string $status = 'gepubliceerd';

    public ?string $statusMessage = null;

    public function editActivity(int $id): void
    {
        $activity = $this->managedActivity($id);
        if ($activity === null) {
            return;
        }

        $this->editingId = $activity->id;
        $this->title = $activity->title;
        $this->description = $activity->description ?? '';
        $this->startsAt = $activity->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = $activity->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->location = $activity->location ?? '';
        $this->capacity = $activity->capacity;
        $this->status = $activity->status->value;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'title', 'description', 'startsAt', 'endsAt', 'location', 'capacity']);
        $this->status = 'gepubliceerd';
    }

    public function save(AuditLogger $audit, ActivityManagerNotifier $notifier): void
    {
        $activity = $this->editingId !== null ? $this->managedActivity($this->editingId) : null;
        if ($activity === null) {
            return;
        }

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'in:concept,gepubliceerd,afgelast'],
        ]);

        $attributes = [
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : null,
            'starts_at' => Carbon::parse($this->startsAt),
            'ends_at' => $this->endsAt !== '' ? Carbon::parse($this->endsAt) : null,
            'location' => $this->location !== '' ? $this->location : null,
            'capacity' => $this->capacity,
            'status' => $this->status,
        ];

        if ($activity->series_id !== null && ! $activity->is_exception) {
            $attributes['is_exception'] = true;
        }

        $before = $activity->only(array_keys($attributes));
        $activity->update($attributes);
        $audit->log('activity.updated', $activity, before: $before, after: $attributes, context: ['via' => 'portal_manager']);
        $notifier->notifyChanged($activity);

        $this->statusMessage = "Activiteit [{$activity->title}] bijgewerkt.";
        $this->cancelEdit();
    }

    public function toggleOwnNotify(int $activityId): void
    {
        $person = auth()->user()?->person;
        if ($person === null) {
            return;
        }
        $activity = $this->managedActivity($activityId);
        if ($activity === null) {
            return;
        }
        $current = $activity->managers()->where('persons.id', $person->id)->first();
        if ($current === null) {
            return;
        }
        // @phpstan-ignore property.notFound (dynamische pivot-kolom, withPivot('notify'))
        $activity->managers()->updateExistingPivot($person->id, ['notify' => ! $current->pivot->notify]);
    }

    private function managedActivity(int $id): ?Activity
    {
        $person = auth()->user()?->person;
        if ($person === null) {
            return null;
        }

        $activity = Activity::query()->find($id);
        if ($activity === null || ! $activity->isManagedBy($person)) {
            return null;
        }

        return $activity;
    }

    public function render(): View
    {
        $person = auth()->user()?->person;

        $activities = $person === null
            ? collect()
            : Activity::query()
                ->where(function ($query) use ($person) {
                    $query->whereHas('managers', fn ($q) => $q->where('persons.id', $person->id))
                        ->orWhereHas('managerGroups.members', fn ($q) => $q->where('persons.id', $person->id));
                })
                ->with(['category', 'enrollments.person', 'managers', 'managerGroups.members'])
                ->orderBy('starts_at')
                ->get();

        return view('livewire.portal.beheerde-activiteiten', [
            'activities' => $activities,
            'statuses' => ActivityStatus::cases(),
            'ownPersonId' => $person?->id,
        ]);
    }
}
