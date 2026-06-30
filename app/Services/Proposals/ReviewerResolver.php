<?php

namespace App\Services\Proposals;

use App\Enums\AssigneeType;
use App\Models\Person;
use App\Models\ReviewStep;
use Illuminate\Support\Carbon;

class ReviewerResolver
{
    /**
     * Mag deze persoon over deze stap beslissen?
     *
     * - Persoonstoewijzing: alleen die persoon.
     * - Roltoewijzing: een actieve, niet-verlopen role_assignment voor die rol.
     * - Groepstoewijzing: lid van de groep — één goedkeuring volstaat (§20.4 Groepsstap).
     */
    public function canDecide(ReviewStep $step, Person $decider): bool
    {
        return match ($step->assignee_type) {
            AssigneeType::Person => $decider->id === $step->assignee_id,
            AssigneeType::Role => $this->hasActiveRole($decider, $step->assignee_id),
            AssigneeType::Group => $this->isGroupMember($decider, $step->assignee_id),
        };
    }

    private function hasActiveRole(Person $decider, int $roleId): bool
    {
        $now = Carbon::now();

        return $decider->roleAssignments()
            ->where('role_id', $roleId)
            ->where('status', 'active')
            ->whereNull('deactivated_at')
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            })
            ->exists();
    }

    private function isGroupMember(Person $decider, int $groupId): bool
    {
        return $decider->approverGroups()->where('approver_groups.id', $groupId)->exists();
    }
}
