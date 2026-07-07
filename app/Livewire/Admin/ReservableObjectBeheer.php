<?php

namespace App\Livewire\Admin;

use App\Enums\ReservableObjectStatus;
use App\Models\ObjectCategory;
use App\Models\ReservableObject;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['header' => 'Reserveerbare objecten'])]
class ReservableObjectBeheer extends Component
{
    public ?int $editingId = null;

    public ?int $categoryId = null;

    public string $name = '';

    public string $location = '';

    public string $status = 'beschikbaar';

    public ?string $statusMessage = null;

    public ?int $filterCategoryId = null;

    public string $filterStatus = 'all';

    public function edit(int $id): void
    {
        $obj = ReservableObject::query()->findOrFail($id);
        $this->editingId = $obj->id;
        $this->categoryId = $obj->object_category_id;
        $this->name = $obj->name;
        $this->location = $obj->location ?? '';
        $this->status = $obj->status->value;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'categoryId', 'name', 'location']);
        $this->status = 'beschikbaar';
    }

    public function save(AuditLogger $audit): void
    {
        $this->validate([
            'categoryId' => ['required', 'integer', 'exists:object_categories,id'],
            'name' => ['required', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:200'],
            'status' => ['required', 'in:beschikbaar,buiten_gebruik'],
        ]);

        DB::transaction(function () use ($audit): void {
            $attributes = [
                'object_category_id' => $this->categoryId,
                'name' => $this->name,
                'location' => $this->location !== '' ? $this->location : null,
                'status' => $this->status,
            ];

            if ($this->editingId === null) {
                $obj = ReservableObject::query()->create($attributes);
                $audit->log('reservable_object.created', $obj, after: $attributes);
                $this->statusMessage = "Object [{$obj->name}] toegevoegd.";
            } else {
                $obj = ReservableObject::query()->findOrFail($this->editingId);
                $before = [
                    'object_category_id' => $obj->object_category_id,
                    'name' => $obj->name,
                    'location' => $obj->location,
                    'status' => $obj->status->value,
                ];
                $obj->update($attributes);
                $audit->log('reservable_object.updated', $obj, before: $before, after: $attributes);
                $this->statusMessage = "Object [{$obj->name}] bijgewerkt.";
            }
        });

        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $obj = ReservableObject::query()->findOrFail($id);
        DB::transaction(function () use ($obj, $audit): void {
            $audit->log('reservable_object.deleted', $obj, before: ['name' => $obj->name]);
            $obj->delete();
        });
        $this->statusMessage = "Object [{$obj->name}] verwijderd. Bestaande reserveringen zijn ook gewist.";
    }

    public function render(): View
    {
        $query = ReservableObject::query()
            ->with('category')
            ->orderBy('name');

        if ($this->filterCategoryId !== null) {
            $query->where('object_category_id', $this->filterCategoryId);
        }
        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        return view('livewire.admin.reservable-object-beheer', [
            'objects' => $query->get(),
            'categories' => ObjectCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'statuses' => ReservableObjectStatus::cases(),
        ]);
    }
}
