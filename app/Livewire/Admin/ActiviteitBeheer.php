<?php

namespace App\Livewire\Admin;

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor activiteiten. Aanmaken, wijzigen, publiceren en afgelasten.
 * Toegang tot de lijst/route loopt via `activities.view`; wijzigen valt onder
 * `activities.update`.
 */
#[Layout('layouts.app', ['header' => 'Activiteiten'])]
class ActiviteitBeheer extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $categoryId = null;

    public string $title = '';

    public string $description = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $location = '';

    public ?int $capacity = null;

    public string $visibility = 'members';

    public string $status = 'gepubliceerd';

    public string $filterStatus = 'all';

    public bool $hideHistory = true;

    public ?string $statusMessage = null;

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        if (! $this->showForm) {
            $this->resetForm();
        }
    }

    public function editActivity(int $id): void
    {
        $activity = Activity::query()->findOrFail($id);
        $this->editingId = $activity->id;
        $this->categoryId = $activity->activity_category_id;
        $this->title = $activity->title;
        $this->description = $activity->description ?? '';
        $this->startsAt = $activity->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = $activity->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->location = $activity->location ?? '';
        $this->capacity = $activity->capacity;
        $this->visibility = $activity->visibility->value;
        $this->status = $activity->status->value;
        $this->showForm = true;
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'categoryId', 'title', 'description', 'startsAt',
            'endsAt', 'location', 'capacity',
        ]);
        $this->visibility = 'members';
        $this->status = 'gepubliceerd';
    }

    public function save(AuditLogger $audit): void
    {
        $this->validate([
            'categoryId' => ['required', 'integer', 'exists:activity_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'visibility' => ['required', 'in:public,members,staff'],
            'status' => ['required', 'in:concept,gepubliceerd,afgelast'],
        ], [
            'startsAt.required' => 'Startdatum en -tijd zijn verplicht.',
            'endsAt.after_or_equal' => 'De einddatum kan niet vóór de startdatum liggen.',
        ]);

        DB::transaction(function () use ($audit): void {
            if ($this->editingId === null) {
                $activity = Activity::query()->create($this->activityAttributes());
                $audit->log('activity.created', $activity, after: $this->activityAttributes());
                $this->statusMessage = "Activiteit [{$activity->title}] aangemaakt.";
            } else {
                $activity = Activity::query()->findOrFail($this->editingId);
                $before = $activity->only(array_keys($this->activityAttributes()));
                $activity->update($this->activityAttributes());
                $audit->log('activity.updated', $activity, before: $before, after: $this->activityAttributes());
                $this->statusMessage = "Activiteit [{$activity->title}] bijgewerkt.";
            }
        });

        $this->resetForm();
        $this->showForm = false;
    }

    public function cancel(int $id, AuditLogger $audit): void
    {
        $activity = Activity::query()->findOrFail($id);
        $before = ['status' => $activity->status->value];
        $activity->update(['status' => ActivityStatus::Cancelled]);
        $audit->log('activity.cancelled', $activity, before: $before, after: ['status' => 'afgelast']);
        $this->statusMessage = "Activiteit [{$activity->title}] afgelast.";
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $activity = Activity::query()->findOrFail($id);
        $before = $activity->only(array_keys($this->activityAttributes()));
        DB::transaction(function () use ($activity, $before, $audit): void {
            $audit->log('activity.deleted', $activity, before: $before);
            $activity->delete();
        });
        $this->statusMessage = "Activiteit [{$activity->title}] verwijderd.";
    }

    /**
     * @return array<string, mixed>
     */
    private function activityAttributes(): array
    {
        return [
            'activity_category_id' => $this->categoryId,
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : null,
            'starts_at' => Carbon::parse($this->startsAt),
            'ends_at' => $this->endsAt !== '' ? Carbon::parse($this->endsAt) : null,
            'location' => $this->location !== '' ? $this->location : null,
            'capacity' => $this->capacity,
            'visibility' => $this->visibility,
            'status' => $this->status,
            'created_by_person_id' => auth()->user()?->person?->id,
        ];
    }

    public function render(): View
    {
        $query = Activity::query()
            ->with(['category', 'enrollments'])
            ->orderBy('starts_at');

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->hideHistory) {
            $query->where('starts_at', '>=', Carbon::now()->startOfDay());
        }

        return view('livewire.admin.activiteit-beheer', [
            'activities' => $query->get(),
            'categories' => ActivityCategory::query()->orderBy('sort_order')->get(),
            'visibilities' => ActivityVisibility::cases(),
            'statuses' => ActivityStatus::cases(),
        ]);
    }
}
