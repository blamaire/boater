<?php

namespace App\Livewire\Admin;

use App\Models\ActivityCategory;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['header' => 'Activiteitcategorieën'])]
class ActivityCategoryBeheer extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public int $sortOrder = 50;

    public ?string $statusMessage = null;

    public function edit(int $id): void
    {
        $cat = ActivityCategory::query()->findOrFail($id);
        $this->editingId = $cat->id;
        $this->name = $cat->name;
        $this->sortOrder = $cat->sort_order;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name']);
        $this->sortOrder = 50;
    }

    public function save(AuditLogger $audit): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'sortOrder' => ['integer', 'min:0', 'max:999'],
        ]);

        DB::transaction(function () use ($audit): void {
            if ($this->editingId === null) {
                $cat = ActivityCategory::query()->create([
                    'name' => $this->name,
                    'slug' => Str::slug($this->name),
                    'sort_order' => $this->sortOrder,
                ]);
                $audit->log('activity_category.created', $cat, after: ['name' => $cat->name]);
                $this->statusMessage = "Categorie [{$cat->name}] toegevoegd.";
            } else {
                $cat = ActivityCategory::query()->findOrFail($this->editingId);
                $before = ['name' => $cat->name, 'sort_order' => $cat->sort_order];
                $cat->update([
                    'name' => $this->name,
                    'sort_order' => $this->sortOrder,
                ]);
                $audit->log('activity_category.updated', $cat, before: $before, after: ['name' => $cat->name, 'sort_order' => $cat->sort_order]);
                $this->statusMessage = "Categorie [{$cat->name}] bijgewerkt.";
            }
        });

        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $cat = ActivityCategory::query()->findOrFail($id);
        if ($cat->activities()->exists()) {
            $this->statusMessage = "Categorie [{$cat->name}] kan niet worden verwijderd — er zijn nog activiteiten aan gekoppeld.";

            return;
        }
        $before = ['name' => $cat->name];
        DB::transaction(function () use ($cat, $before, $audit): void {
            $audit->log('activity_category.deleted', $cat, before: $before);
            $cat->delete();
        });
        $this->statusMessage = "Categorie [{$cat->name}] verwijderd.";
    }

    public function render(): View
    {
        return view('livewire.admin.activity-category-beheer', [
            'categories' => ActivityCategory::query()->orderBy('sort_order')->get(),
        ]);
    }
}
