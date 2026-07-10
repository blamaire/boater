<?php

namespace App\Livewire\Admin;

use App\Enums\ReservationConstraintType;
use App\Models\ObjectCategory;
use App\Models\ReservationRule;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * CRUD voor `reservation_rules` (§18.3). Elk record geldt inclusief
 * subcategorieën; per_person bepaalt of de teller per lid of totaal is.
 */
#[Layout('layouts.app', ['header' => 'Reserveringsregels'])]
class ReserveringsregelBeheer extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public ?int $categoryId = null;

    public string $constraintType = 'gelijktijdig';

    public int $limitValue = 1;

    public bool $perPerson = true;

    public ?string $statusMessage = null;

    public function edit(int $id): void
    {
        $rule = ReservationRule::query()->findOrFail($id);
        $this->editingId = $rule->id;
        $this->name = $rule->name;
        $this->categoryId = $rule->object_category_id;
        $this->constraintType = $rule->constraint_type->value;
        $this->limitValue = $rule->limit_value;
        $this->perPerson = $rule->per_person;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'categoryId']);
        $this->constraintType = 'gelijktijdig';
        $this->limitValue = 1;
        $this->perPerson = true;
    }

    public function save(AuditLogger $audit): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'categoryId' => ['required', 'integer', 'exists:object_categories,id'],
            'constraintType' => ['required', 'string', 'in:gelijktijdig,per_dag,duur'],
            'limitValue' => ['integer', 'min:1', 'max:100000'],
        ]);

        DB::transaction(function () use ($audit): void {
            if ($this->editingId === null) {
                $rule = ReservationRule::create([
                    'name' => $this->name,
                    'object_category_id' => $this->categoryId,
                    'constraint_type' => ReservationConstraintType::from($this->constraintType),
                    'limit_value' => $this->limitValue,
                    'per_person' => $this->perPerson,
                ]);
                $audit->log('reservation_rule.created', $rule, after: [
                    'name' => $rule->name,
                    'category_id' => $rule->object_category_id,
                    'constraint_type' => $rule->constraint_type->value,
                    'limit_value' => $rule->limit_value,
                    'per_person' => $rule->per_person,
                ]);
                $this->statusMessage = "Regel [{$rule->name}] toegevoegd.";
            } else {
                $rule = ReservationRule::query()->findOrFail($this->editingId);
                $before = [
                    'name' => $rule->name,
                    'category_id' => $rule->object_category_id,
                    'constraint_type' => $rule->constraint_type->value,
                    'limit_value' => $rule->limit_value,
                    'per_person' => $rule->per_person,
                ];
                $rule->update([
                    'name' => $this->name,
                    'object_category_id' => $this->categoryId,
                    'constraint_type' => ReservationConstraintType::from($this->constraintType),
                    'limit_value' => $this->limitValue,
                    'per_person' => $this->perPerson,
                ]);
                $audit->log('reservation_rule.updated', $rule, before: $before, after: [
                    'name' => $rule->name,
                    'category_id' => $rule->object_category_id,
                    'constraint_type' => $rule->constraint_type->value,
                    'limit_value' => $rule->limit_value,
                    'per_person' => $rule->per_person,
                ]);
                $this->statusMessage = "Regel [{$rule->name}] bijgewerkt.";
            }
        });

        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $rule = ReservationRule::query()->findOrFail($id);
        DB::transaction(function () use ($rule, $audit): void {
            $audit->log('reservation_rule.deleted', $rule, before: [
                'name' => $rule->name,
                'category_id' => $rule->object_category_id,
                'constraint_type' => $rule->constraint_type->value,
                'limit_value' => $rule->limit_value,
                'per_person' => $rule->per_person,
            ]);
            $rule->delete();
        });
        $this->statusMessage = "Regel [{$rule->name}] verwijderd.";
    }

    public function render(): View
    {
        return view('livewire.admin.reserveringsregel-beheer', [
            'rules' => ReservationRule::query()
                ->with('category.parent')
                ->orderBy('object_category_id')
                ->orderBy('constraint_type')
                ->get(),
            'categories' => ObjectCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'constraintTypes' => ReservationConstraintType::cases(),
        ]);
    }
}
