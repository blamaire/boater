<?php

namespace App\Services\Proposals;

use App\Enums\AssigneeType;
use App\Enums\ProposalStatus;
use App\Enums\ReviewStepStatus;
use App\Models\Person;
use App\Models\ReviewStep;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReviewerResolver
{
    /**
     * Mag deze persoon over deze stap beslissen?
     *
     * - Persoonstoewijzing: alleen die persoon.
     * - Roltoewijzing: een actieve, niet-verlopen role_assignment voor die rol.
     * - Groepstoewijzing: lid van de groep — één goedkeuring volstaat (§20.4 Groepsstap).
     *   Beheerders zitten impliciet in élke groep zodat er nooit iets vast
     *   komt te zitten wanneer een groep leeg is.
     */
    public function canDecide(ReviewStep $step, Person $decider): bool
    {
        return match ($step->assignee_type) {
            AssigneeType::Person => $decider->id === $step->assignee_id,
            AssigneeType::Role => $this->hasActiveRole($decider, $step->assignee_id),
            AssigneeType::Group => $this->isGroupMember($decider, $step->assignee_id) || $this->isBeheerder($decider),
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

    private function isBeheerder(Person $decider): bool
    {
        $beheerderId = Role::query()->where('name', 'Beheerder')->value('id');
        if ($beheerderId === null) {
            return false;
        }

        return $this->hasActiveRole($decider, (int) $beheerderId);
    }

    /**
     * Alle stappen die deze persoon nu daadwerkelijk kan beslissen: enkel de
     * huidige actieve stap per voorstel (join op `proposals.current_step`) —
     * niet elke 'pending' rij, want een meerstaps-beleid maakt bij het
     * indienen alle stappen alvast als pending aan (§20.3 createSteps),
     * terwijl alleen de stap op `current_step` daadwerkelijk beslisbaar is.
     *
     * @return Builder<ReviewStep>
     */
    public function decidableStepsQuery(Person $person): Builder
    {
        $now = Carbon::now();

        $roleIds = $person->roleAssignments()
            ->where('status', 'active')
            ->whereNull('deactivated_at')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->pluck('role_id');

        $groupIds = $person->approverGroups()->pluck('approver_groups.id');
        $isBeheerder = $this->isBeheerder($person);

        return ReviewStep::query()
            ->join('proposals', 'proposals.id', '=', 'review_steps.proposal_id')
            ->whereColumn('review_steps.sequence', 'proposals.current_step')
            ->where('review_steps.status', ReviewStepStatus::Pending->value)
            ->whereIn('proposals.status', [ProposalStatus::Submitted->value, ProposalStatus::InReview->value])
            ->where(function (Builder $query) use ($person, $roleIds, $groupIds, $isBeheerder) {
                $query->where(function (Builder $q) use ($person) {
                    $q->where('review_steps.assignee_type', AssigneeType::Person->value)
                        ->where('review_steps.assignee_id', $person->id);
                });

                if ($roleIds->isNotEmpty()) {
                    $query->orWhere(function (Builder $q) use ($roleIds) {
                        $q->where('review_steps.assignee_type', AssigneeType::Role->value)
                            ->whereIn('review_steps.assignee_id', $roleIds->all());
                    });
                }

                if ($isBeheerder) {
                    $query->orWhere('review_steps.assignee_type', AssigneeType::Group->value);
                } elseif ($groupIds->isNotEmpty()) {
                    $query->orWhere(function (Builder $q) use ($groupIds) {
                        $q->where('review_steps.assignee_type', AssigneeType::Group->value)
                            ->whereIn('review_steps.assignee_id', $groupIds->all());
                    });
                }
            })
            ->select('review_steps.*');
    }
}
