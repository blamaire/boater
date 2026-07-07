<?php

namespace App\Livewire\Admin;

use App\Models\ObjectCategory;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['header' => 'Objectcategorieën'])]
class ObjectCategoryBeheer extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public bool $requiresBoatRight = false;

    public int $sortOrder = 50;

    public ?string $statusMessage = null;

    public function edit(int $id): void
    {
        $cat = ObjectCategory::query()->findOrFail($id);
        $this->editingId = $cat->id;
        $this->name = $cat->name;
        $this->requiresBoatRight = $cat->requires_boat_right;
        $this->sortOrder = $cat->sort_order;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name']);
        $this->requiresBoatRight = false;
        $this->sortOrder = 50;
    }

    public function save(AuditLogger $audit): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'requiresBoatRight' => ['boolean'],
            'sortOrder' => ['integer', 'min:0', 'max:999'],
        ]);

        DB::transaction(function () use ($audit): void {
            if ($this->editingId === null) {
                $cat = ObjectCategory::query()->create([
                    'name' => $this->name,
                    'slug' => Str::slug($this->name),
                    'requires_boat_right' => $this->requiresBoatRight,
                    'sort_order' => $this->sortOrder,
                ]);
                $audit->log('object_category.created', $cat, after: ['name' => $cat->name, 'requires_boat_right' => $cat->requires_boat_right]);
                $this->statusMessage = "Categorie [{$cat->name}] toegevoegd.";
            } else {
                $cat = ObjectCategory::query()->findOrFail($this->editingId);
                $before = ['name' => $cat->name, 'requires_boat_right' => $cat->requires_boat_right, 'sort_order' => $cat->sort_order];
                $cat->update([
                    'name' => $this->name,
                    'requires_boat_right' => $this->requiresBoatRight,
                    'sort_order' => $this->sortOrder,
                ]);
                $audit->log('object_category.updated', $cat, before: $before, after: [
                    'name' => $cat->name,
                    'requires_boat_right' => $cat->requires_boat_right,
                    'sort_order' => $cat->sort_order,
                ]);
                $this->statusMessage = "Categorie [{$cat->name}] bijgewerkt.";
            }
        });

        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $cat = ObjectCategory::query()->findOrFail($id);
        if ($cat->objects()->exists()) {
            $this->statusMessage = "Categorie [{$cat->name}] kan niet worden verwijderd — er zijn objecten aan gekoppeld.";

            return;
        }
        DB::transaction(function () use ($cat, $audit): void {
            $audit->log('object_category.deleted', $cat, before: ['name' => $cat->name]);
            $cat->delete();
        });
        $this->statusMessage = "Categorie [{$cat->name}] verwijderd.";
    }

    public function render(): View
    {
        return view('livewire.admin.object-category-beheer', [
            'categories' => ObjectCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
