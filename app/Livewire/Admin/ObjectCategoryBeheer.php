<?php

namespace App\Livewire\Admin;

use App\Models\CategoryResponsible;
use App\Models\ObjectCategory;
use App\Models\Person;
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

    public ?int $parentId = null;

    public bool $requiresBoatRight = false;

    public int $sortOrder = 50;

    public ?string $statusMessage = null;

    // Per-categorie: gekozen persoon in het "verantwoordelijke toevoegen"-veld.
    /** @var array<int, ?int> */
    public array $responsibleInput = [];

    public function edit(int $id): void
    {
        $cat = ObjectCategory::query()->findOrFail($id);
        $this->editingId = $cat->id;
        $this->name = $cat->name;
        $this->parentId = $cat->parent_id;
        $this->requiresBoatRight = $cat->requires_boat_right;
        $this->sortOrder = $cat->sort_order;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'parentId']);
        $this->requiresBoatRight = false;
        $this->sortOrder = 50;
    }

    public function save(AuditLogger $audit): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'parentId' => ['nullable', 'integer', 'exists:object_categories,id'],
            'requiresBoatRight' => ['boolean'],
            'sortOrder' => ['integer', 'min:0', 'max:999'],
        ]);

        // Voorkomt dat een categorie zichzelf of een afstammeling als parent
        // krijgt — dan zou de hiërarchie een cycle worden.
        if ($this->editingId !== null && $this->parentId !== null) {
            if ($this->parentId === $this->editingId) {
                $this->addError('parentId', 'Een categorie kan niet zijn eigen parent zijn.');

                return;
            }
            $candidate = ObjectCategory::query()->find($this->parentId);
            if ($candidate !== null) {
                foreach ([$candidate, ...$candidate->ancestors()] as $node) {
                    if ($node->id === $this->editingId) {
                        $this->addError('parentId', 'Parent mag geen afstammeling van deze categorie zijn.');

                        return;
                    }
                }
            }
        }

        DB::transaction(function () use ($audit): void {
            if ($this->editingId === null) {
                $cat = ObjectCategory::query()->create([
                    'name' => $this->name,
                    'slug' => Str::slug($this->name),
                    'parent_id' => $this->parentId,
                    'requires_boat_right' => $this->requiresBoatRight,
                    'sort_order' => $this->sortOrder,
                ]);
                $audit->log('object_category.created', $cat, after: [
                    'name' => $cat->name,
                    'parent_id' => $cat->parent_id,
                    'requires_boat_right' => $cat->requires_boat_right,
                ]);
                $this->statusMessage = "Categorie [{$cat->name}] toegevoegd.";
            } else {
                $cat = ObjectCategory::query()->findOrFail($this->editingId);
                $before = [
                    'name' => $cat->name,
                    'parent_id' => $cat->parent_id,
                    'requires_boat_right' => $cat->requires_boat_right,
                    'sort_order' => $cat->sort_order,
                ];
                $cat->update([
                    'name' => $this->name,
                    'parent_id' => $this->parentId,
                    'requires_boat_right' => $this->requiresBoatRight,
                    'sort_order' => $this->sortOrder,
                ]);
                $audit->log('object_category.updated', $cat, before: $before, after: [
                    'name' => $cat->name,
                    'parent_id' => $cat->parent_id,
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
        if ($cat->children()->exists()) {
            $this->statusMessage = "Categorie [{$cat->name}] kan niet worden verwijderd — er zijn subcategorieën aan gekoppeld.";

            return;
        }
        DB::transaction(function () use ($cat, $audit): void {
            $audit->log('object_category.deleted', $cat, before: ['name' => $cat->name]);
            $cat->delete();
        });
        $this->statusMessage = "Categorie [{$cat->name}] verwijderd.";
    }

    public function addResponsible(int $categoryId, AuditLogger $audit): void
    {
        $personId = $this->responsibleInput[$categoryId] ?? null;
        if ($personId === null) {
            return;
        }

        $cat = ObjectCategory::query()->findOrFail($categoryId);
        $person = Person::query()->findOrFail($personId);

        $exists = CategoryResponsible::query()
            ->where('object_category_id', $cat->id)
            ->where('person_id', $person->id)
            ->exists();
        if ($exists) {
            $this->statusMessage = "{$person->first_name} was al verantwoordelijk voor [{$cat->name}].";

            return;
        }

        CategoryResponsible::create([
            'object_category_id' => $cat->id,
            'person_id' => $person->id,
        ]);
        $audit->log('category_responsible.added', $cat, after: ['person_id' => $person->id]);
        $this->responsibleInput[$categoryId] = null;
        $this->statusMessage = "{$person->first_name} toegevoegd als verantwoordelijke voor [{$cat->name}].";
    }

    public function removeResponsible(int $responsibleId, AuditLogger $audit): void
    {
        $link = CategoryResponsible::query()->with('category', 'person')->findOrFail($responsibleId);
        $categoryName = $link->category->name;
        $personName = $link->person->first_name.' '.$link->person->last_name;
        $audit->log('category_responsible.removed', $link->category, before: ['person_id' => $link->person_id]);
        $link->delete();
        $this->statusMessage = "{$personName} verwijderd als verantwoordelijke voor [{$categoryName}].";
    }

    public function render(): View
    {
        return view('livewire.admin.object-category-beheer', [
            'categories' => ObjectCategory::query()
                ->with(['parent', 'responsibles.person'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'personsForResponsibility' => Person::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(500)
                ->get(),
        ]);
    }
}
