<?php

namespace App\Livewire\Admin;

use App\Enums\AssigneeType;
use App\Models\ApproverGroup;
use App\Models\Person;
use App\Models\ReviewPolicy;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Centraal beheerscherm voor goedkeuringsgroepen (§8/§20). Elke policy
 * verwijst naar één van deze groepen; de kruisverwijzing wordt hier
 * getoond. Beheerder-rol houders zitten impliciet in alle groepen (zie
 * ReviewerResolver) — een leeg gelaten groep blokkeert daarmee nooit
 * de goedkeuringsflow.
 */
#[Layout('layouts.app', ['header' => 'Goedkeuringsgroepen'])]
class GoedkeuringsgroepBeheer extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public ?int $addMemberInput = null;

    public ?int $expandedId = null;

    public ?string $statusMessage = null;

    public function edit(int $id): void
    {
        $group = ApproverGroup::query()->findOrFail($id);
        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->description = $group->description ?? '';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description']);
    }

    public function save(AuditLogger $audit): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($audit): void {
            if ($this->editingId === null) {
                $group = ApproverGroup::create([
                    'name' => $this->name,
                    'description' => $this->description !== '' ? $this->description : null,
                ]);
                $audit->log('approver_group.created', $group, after: ['name' => $group->name]);
                $this->statusMessage = "Groep [{$group->name}] toegevoegd.";
            } else {
                $group = ApproverGroup::query()->findOrFail($this->editingId);
                $before = ['name' => $group->name, 'description' => $group->description];
                $group->update([
                    'name' => $this->name,
                    'description' => $this->description !== '' ? $this->description : null,
                ]);
                $audit->log('approver_group.updated', $group, before: $before, after: [
                    'name' => $group->name,
                    'description' => $group->description,
                ]);
                $this->statusMessage = "Groep [{$group->name}] bijgewerkt.";
            }
        });

        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $group = ApproverGroup::query()->findOrFail($id);

        // Voorkom weesreferenties: als een policy naar deze groep verwijst,
        // moet die eerst herconfigureerd worden.
        $referencingPolicies = $this->policiesForGroup($id);
        if ($referencingPolicies->isNotEmpty()) {
            $this->statusMessage = "Groep [{$group->name}] kan niet worden verwijderd — de policy [{$referencingPolicies->pluck('name')->implode(', ')}] verwijst er nog naar.";

            return;
        }

        DB::transaction(function () use ($group, $audit): void {
            $audit->log('approver_group.deleted', $group, before: ['name' => $group->name]);
            $group->members()->detach();
            $group->delete();
        });
        $this->statusMessage = "Groep [{$group->name}] verwijderd.";
    }

    public function toggle(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
        $this->addMemberInput = null;
    }

    public function addMember(int $groupId, AuditLogger $audit): void
    {
        if ($this->addMemberInput === null) {
            return;
        }
        $group = ApproverGroup::query()->findOrFail($groupId);
        $person = Person::query()->findOrFail($this->addMemberInput);

        if ($group->members()->where('persons.id', $person->id)->exists()) {
            $this->statusMessage = "{$person->first_name} zit al in [{$group->name}].";

            return;
        }

        $group->members()->attach($person->id);
        $audit->log('approver_group.member_added', $group, after: ['person_id' => $person->id]);
        $this->addMemberInput = null;
        $this->statusMessage = "{$person->first_name} toegevoegd aan [{$group->name}].";
    }

    public function removeMember(int $groupId, int $personId, AuditLogger $audit): void
    {
        $group = ApproverGroup::query()->findOrFail($groupId);
        $person = Person::query()->findOrFail($personId);
        $group->members()->detach($person->id);
        $audit->log('approver_group.member_removed', $group, before: ['person_id' => $person->id]);
        $this->statusMessage = "{$person->first_name} verwijderd uit [{$group->name}].";
    }

    public function render(): View
    {
        $beheerderRoleId = Role::query()->where('name', 'Beheerder')->value('id');
        $beheerders = $beheerderRoleId === null
            ? collect()
            : Person::query()
                ->whereHas('roleAssignments', fn ($q) => $q->where('role_id', $beheerderRoleId)->where('status', 'active'))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

        $policiesByGroup = ReviewPolicy::query()
            ->get()
            ->groupBy(function (ReviewPolicy $p) {
                foreach ($p->steps as $step) {
                    if ($step['assignee_type'] === AssigneeType::Group->value) {
                        return $step['assignee_id'];
                    }
                }

                return 0;
            });

        return view('livewire.admin.goedkeuringsgroep-beheer', [
            'groups' => ApproverGroup::query()->with('members')->orderBy('name')->get(),
            'personsForAssignment' => Person::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(500)
                ->get(),
            'beheerders' => $beheerders,
            'policiesByGroup' => $policiesByGroup,
        ]);
    }

    /**
     * @return Collection<int, ReviewPolicy>
     */
    private function policiesForGroup(int $groupId): Collection
    {
        return ReviewPolicy::query()
            ->get()
            ->filter(function (ReviewPolicy $p) use ($groupId): bool {
                foreach ($p->steps as $step) {
                    if ($step['assignee_type'] === AssigneeType::Group->value
                        && $step['assignee_id'] === $groupId) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }
}
