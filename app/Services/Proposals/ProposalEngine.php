<?php

namespace App\Services\Proposals;

use App\Enums\AssigneeType;
use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Enums\ResubmitBehavior;
use App\Enums\ReviewStepStatus;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReviewPolicy;
use App\Models\ReviewStep;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\EffectivePermissions;
use App\Services\Proposals\Contracts\WithdrawableProposalHandler;
use App\Services\Proposals\Exceptions\ProposalConflictException;
use App\Services\Proposals\Exceptions\ProposalStateException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProposalEngine
{
    public function __construct(
        private readonly ProposalHandlerRegistry $handlers,
        private readonly ReviewerResolver $reviewerResolver,
        private readonly EffectivePermissions $permissions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Dien een voorstel in. Routering volgt §20.3:
     *   1. bypass-permissie → direct toepassen (gelogd);
     *   2. auto_apply binnen beleid → direct toepassen;
     *   3. anders reviewstappen aanmaken.
     *
     * $ignoreBypass slaat uitsluitend stap 1 over (de persoonsgebonden
     * bypass-permissie) — een expliciete "toch via goedkeuring indienen"-
     * keuze voor iemand die eigenlijk direct zou mogen toepassen. De
     * beleidsbrede auto_apply-vlag (stap 2) blijft ongemoeid: die geldt voor
     * iedereen, ongeacht wie indient.
     */
    public function submit(
        string $subjectType,
        ChangeType $changeType,
        array $payload,
        ?Person $proposer = null,
        ?int $subjectId = null,
        ?ReviewPolicy $policy = null,
        bool $ignoreBypass = false,
    ): Proposal {
        return DB::transaction(function () use ($subjectType, $changeType, $payload, $proposer, $subjectId, $policy, $ignoreBypass) {
            $policy ??= $this->resolvePolicy($subjectType);

            $proposal = Proposal::create([
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'change_type' => $changeType,
                'payload' => $payload,
                'proposed_by_person_id' => $proposer?->id,
                'status' => ProposalStatus::Submitted,
                'policy_id' => $policy?->id,
                'current_step' => 0,
            ]);

            $this->audit->log('proposal.submitted', $proposal, after: $this->snapshot($proposal));

            if (! $ignoreBypass && $policy && $proposer && $policy->bypass_permission && $this->permissions->has($proposer, $policy->bypass_permission)) {
                $this->audit->log('proposal.bypassed', $proposal, context: [
                    'bypass_permission' => $policy->bypass_permission,
                ]);

                return $this->applyApproved($proposal->refresh());
            }

            if ($policy && $policy->auto_apply) {
                return $this->applyApproved($proposal->refresh());
            }

            $this->createSteps($proposal, $policy);

            $proposal->update([
                'status' => ProposalStatus::InReview,
                'current_step' => 1,
            ]);

            $proposal->load('steps');

            return $proposal;
        });
    }

    /**
     * Keur de huidige stap goed. Bij de laatste stap volgt direct apply().
     */
    public function approveStep(ReviewStep $step, Person $decider, ?string $reason = null): Proposal
    {
        return DB::transaction(function () use ($step, $decider, $reason) {
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($step->proposal_id);

            $this->guardOpen($proposal);
            $this->guardStepIsCurrent($proposal, $step);
            $this->guardSeparationOfDuties($proposal, $decider);

            if (! $this->reviewerResolver->canDecide($step, $decider)) {
                throw new ProposalStateException('Deze beslisser is niet bevoegd voor deze stap.');
            }

            $step->update([
                'status' => ReviewStepStatus::Approved,
                'decided_by_person_id' => $decider->id,
                'decided_at' => Carbon::now(),
                'reason' => $reason,
            ]);

            $this->audit->log('proposal.step_approved', $proposal, context: [
                'step_id' => $step->id,
                'sequence' => $step->sequence,
                'decider_id' => $decider->id,
            ]);

            $next = $proposal->steps()
                ->where('sequence', '>', $step->sequence)
                ->where('status', ReviewStepStatus::Pending)
                ->orderBy('sequence')
                ->first();

            if ($next) {
                $proposal->update(['current_step' => $next->sequence]);
                $proposal->load('steps');

                return $proposal;
            }

            return $this->applyApproved($proposal);
        });
    }

    public function reject(ReviewStep $step, Person $decider, string $reason): Proposal
    {
        return DB::transaction(function () use ($step, $decider, $reason) {
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($step->proposal_id);

            $this->guardOpen($proposal);
            $this->guardStepIsCurrent($proposal, $step);
            $this->guardSeparationOfDuties($proposal, $decider);

            if (! $this->reviewerResolver->canDecide($step, $decider)) {
                throw new ProposalStateException('Deze beslisser is niet bevoegd voor deze stap.');
            }

            $step->update([
                'status' => ReviewStepStatus::Rejected,
                'decided_by_person_id' => $decider->id,
                'decided_at' => Carbon::now(),
                'reason' => $reason,
            ]);

            $this->skipRemainingSteps($proposal, $step->sequence);

            $proposal->update([
                'status' => ProposalStatus::Rejected,
                'decision_reason' => $reason,
            ]);

            $this->audit->log('proposal.rejected', $proposal, context: [
                'step_id' => $step->id,
                'decider_id' => $decider->id,
                'reason' => $reason,
            ]);

            $proposal->load('steps');

            return $proposal;
        });
    }

    public function returnToSubmitter(ReviewStep $step, Person $decider, string $reason): Proposal
    {
        return DB::transaction(function () use ($step, $decider, $reason) {
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($step->proposal_id);

            $this->guardOpen($proposal);
            $this->guardStepIsCurrent($proposal, $step);
            $this->guardSeparationOfDuties($proposal, $decider);

            if (! $this->reviewerResolver->canDecide($step, $decider)) {
                throw new ProposalStateException('Deze beslisser is niet bevoegd voor deze stap.');
            }

            $step->update([
                'status' => ReviewStepStatus::Returned,
                'decided_by_person_id' => $decider->id,
                'decided_at' => Carbon::now(),
                'reason' => $reason,
            ]);

            $proposal->update([
                'status' => ProposalStatus::Returned,
                'decision_reason' => $reason,
            ]);

            $this->audit->log('proposal.returned', $proposal, context: [
                'step_id' => $step->id,
                'decider_id' => $decider->id,
                'reason' => $reason,
            ]);

            $proposal->load('steps');

            return $proposal;
        });
    }

    /**
     * Een teruggestuurd voorstel opnieuw indienen. Het beleid bepaalt of
     * hervatting bij de huidige stap of vanaf stap 1 plaatsvindt (§20.4).
     */
    public function resubmit(Proposal $proposal, array $payload): Proposal
    {
        return DB::transaction(function () use ($proposal, $payload) {
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);

            if ($proposal->status !== ProposalStatus::Returned) {
                throw new ProposalStateException('Alleen teruggestuurde voorstellen kunnen opnieuw worden ingediend.');
            }

            // @phpstan-ignore nullsafe.neverNull (Larastan ziet niet dat policy_id nullable is)
            $behavior = $proposal->policy?->resubmit_behavior ?? ResubmitBehavior::Restart;

            $proposal->update([
                'payload' => $payload,
                'decision_reason' => null,
            ]);

            if ($behavior === ResubmitBehavior::Restart) {
                $proposal->steps()->delete();
                $this->createSteps($proposal, $proposal->policy);
                $proposal->update([
                    'status' => ProposalStatus::InReview,
                    'current_step' => 1,
                ]);
            } elseif ($behavior === ResubmitBehavior::Resume) {
                $returned = $proposal->steps()->where('status', ReviewStepStatus::Returned)->orderBy('sequence')->first();
                if ($returned) {
                    $returned->update([
                        'status' => ReviewStepStatus::Pending,
                        'decided_by_person_id' => null,
                        'decided_at' => null,
                        'reason' => null,
                    ]);
                    $proposal->update([
                        'status' => ProposalStatus::InReview,
                        'current_step' => $returned->sequence,
                    ]);
                }
            }

            $this->audit->log('proposal.resubmitted', $proposal, context: [
                'behavior' => $behavior->value,
            ]);

            $proposal->load('steps');

            return $proposal;
        });
    }

    public function withdraw(Proposal $proposal, Person $actor): Proposal
    {
        return DB::transaction(function () use ($proposal, $actor) {
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);

            $this->guardOpen($proposal);

            if ($actor->id !== $proposal->proposed_by_person_id) {
                throw new ProposalStateException('Alleen de indiener kan een voorstel intrekken.');
            }

            $this->skipRemainingSteps($proposal, 0);

            $proposal->update(['status' => ProposalStatus::Withdrawn]);

            // Alleen aanroepen als er een handler geregistreerd is — withdraw()
            // moet ook blijven werken voor ad-hoc/niet-geregistreerde subject_types.
            if ($this->handlers->has($proposal->subject_type)) {
                $handler = $this->handlers->for($proposal->subject_type);
                if ($handler instanceof WithdrawableProposalHandler) {
                    $handler->onWithdrawn($proposal);
                }
            }

            $this->audit->log('proposal.withdrawn', $proposal, context: ['actor_id' => $actor->id]);

            $proposal->load('steps');

            return $proposal;
        });
    }

    /**
     * Markeer een afgehandeld (gesloten) voorstel als gearchiveerd — puur
     * een zichtbaarheidsvlag voor de indiener bij "Mijn voorstellen"
     * (§20-uitbreiding: afgewezen voorstellen blijven zichtbaar totdat de
     * indiener kiest tussen opnieuw indienen en archiveren). Verandert de
     * status zelf niet en heeft geen effect op de onderliggende data.
     */
    public function archive(Proposal $proposal, Person $actor): Proposal
    {
        return DB::transaction(function () use ($proposal, $actor) {
            $proposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);

            if ($proposal->status->isOpen()) {
                throw new ProposalStateException('Alleen afgehandelde voorstellen kunnen worden gearchiveerd.');
            }

            if ($actor->id !== $proposal->proposed_by_person_id) {
                throw new ProposalStateException('Alleen de indiener kan een voorstel archiveren.');
            }

            $proposal->update(['archived_at' => Carbon::now()]);

            $this->audit->log('proposal.archived', $proposal, context: ['actor_id' => $actor->id]);

            return $proposal;
        });
    }

    /**
     * Pas een goedgekeurd voorstel toe. Voert apply-time hervalidatie uit
     * (§20.4); bij een conflict gaat het voorstel naar de status conflicted
     * en wordt apply() niet uitgevoerd.
     */
    private function applyApproved(Proposal $proposal): Proposal
    {
        $proposal->update(['status' => ProposalStatus::Approved]);

        $handler = $this->handlers->for($proposal->subject_type);

        try {
            $handler->revalidate($proposal);
        } catch (ProposalConflictException $e) {
            $proposal->update([
                'status' => ProposalStatus::Conflicted,
                'decision_reason' => $e->getMessage(),
            ]);
            $this->audit->log('proposal.conflicted', $proposal, context: ['message' => $e->getMessage()]);
            $proposal->load('steps');

            return $proposal;
        }

        $handler->apply($proposal);

        $proposal->update([
            'status' => ProposalStatus::Applied,
            'applied_at' => Carbon::now(),
        ]);

        $this->audit->log('proposal.applied', $proposal);
        $proposal->load('steps');

        return $proposal;
    }

    private function resolvePolicy(string $subjectType): ?ReviewPolicy
    {
        return ReviewPolicy::query()->where('subject_type', $subjectType)->orderBy('id')->first();
    }

    private function createSteps(Proposal $proposal, ?ReviewPolicy $policy): void
    {
        if ($policy === null) {
            return;
        }

        foreach ($policy->steps as $index => $config) {
            ReviewStep::create([
                'proposal_id' => $proposal->id,
                'sequence' => $index + 1,
                'assignee_type' => AssigneeType::from($config['assignee_type']),
                'assignee_id' => (int) $config['assignee_id'],
                'status' => ReviewStepStatus::Pending,
                'due_at' => $policy->reminder_after_days
                    ? Carbon::now()->addDays($policy->reminder_after_days)
                    : null,
            ]);
        }
    }

    private function skipRemainingSteps(Proposal $proposal, int $fromSequenceExclusive): void
    {
        $proposal->steps()
            ->where('sequence', '>', $fromSequenceExclusive)
            ->where('status', ReviewStepStatus::Pending)
            ->update([
                'status' => ReviewStepStatus::Skipped,
                'updated_at' => Carbon::now(),
            ]);
    }

    private function guardOpen(Proposal $proposal): void
    {
        if ($proposal->status->isClosed()) {
            throw new ProposalStateException("Voorstel is niet meer open (status: {$proposal->status->value}).");
        }
    }

    private function guardStepIsCurrent(Proposal $proposal, ReviewStep $step): void
    {
        if ($step->proposal_id !== $proposal->id) {
            throw new ProposalStateException('Stap hoort niet bij dit voorstel.');
        }
        if ($step->sequence !== $proposal->current_step) {
            throw new ProposalStateException('Stap is niet de huidige actieve stap.');
        }
        if ($step->status !== ReviewStepStatus::Pending) {
            throw new ProposalStateException('Stap is al beslist.');
        }
    }

    private function guardSeparationOfDuties(Proposal $proposal, Person $decider): void
    {
        // Bij anonieme (publieke) voorstellen is er geen indiener-persoon om
        // tegen te vergelijken; functiescheiding is dan een non-issue.
        if ($proposal->proposed_by_person_id !== null && $decider->id === $proposal->proposed_by_person_id) {
            throw new ProposalStateException('Functiescheiding: de indiener mag niet zelf beslissen.');
        }
    }

    private function snapshot(Proposal $proposal): array
    {
        return [
            'subject_type' => $proposal->subject_type,
            'subject_id' => $proposal->subject_id,
            'change_type' => $proposal->change_type->value,
            'status' => $proposal->status->value,
            'policy_id' => $proposal->policy_id,
        ];
    }
}
