<?php

namespace App\Livewire\Portal;

use App\Enums\PageVersionStatus;
use App\Enums\ProposalStatus;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReviewStep;
use App\Services\Proposals\Exceptions\ProposalConflictException;
use App\Services\Proposals\Exceptions\ProposalStateException;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\ProposalEngine;
use App\Services\Proposals\ReviewerResolver;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Wijzigingsvoorstellen" — overzicht voor een ingelogd lid van (a) eigen
 * ingediende voorstellen, met inzage en intrekken, en (b) voorstellen waarop
 * het lid als reviewer is toegewezen, met goedkeuren/afwijzen/terugsturen.
 * Werkt voor alle subject_types via ProposalPresenter (zie
 * resources/views/components/proposal-change.blade.php) — dit component
 * kent zelf geen per-type logica, enkel de generieke Proposal/ReviewStep-
 * workflow via ProposalEngine.
 */
#[Layout('layouts.app', ['header' => 'Wijzigingsvoorstellen'])]
class Wijzigingsvoorstellen extends Component
{
    public string $statusMessage = '';

    public string $errorMessage = '';

    /** @var array<int, string> */
    public array $reasonInputs = [];

    /**
     * Trek een eigen ingediend voorstel in.
     */
    public function withdraw(int $proposalId, ProposalEngine $engine): void
    {
        $person = $this->requirePerson();

        $proposal = Proposal::query()
            ->where('id', $proposalId)
            ->where('proposed_by_person_id', $person->id)
            ->firstOrFail();

        $this->runEngineAction(
            fn () => $engine->withdraw($proposal, $person),
            'Je voorstel is ingetrokken.',
        );
    }

    /**
     * Trek een eigen open pagina-voorstel in (of archiveer 'm als hij al
     * afgewezen is) en stuur door naar de paginabewerker om verder te
     * bewerken — de onderliggende conceptversie blijft intact dankzij
     * PageVersionProposalHandler::onWithdrawn() (open) resp. de directe
     * status-reset hieronder (afgewezen, want daar grijpt withdraw() niet
     * aan).
     */
    public function editPageProposal(int $proposalId, ProposalEngine $engine): void
    {
        $person = $this->requirePerson();

        $proposal = Proposal::query()
            ->where('id', $proposalId)
            ->where('proposed_by_person_id', $person->id)
            ->where('subject_type', PageVersionProposalHandler::SUBJECT_TYPE)
            ->firstOrFail();

        abort_unless($proposal->status->isOpen() || $proposal->status === ProposalStatus::Rejected, 403);

        $pageId = PageVersion::query()->whereKey($proposal->subject_id)->value('page_id');

        $this->errorMessage = '';
        $this->statusMessage = '';

        try {
            if ($proposal->status->isOpen()) {
                $engine->withdraw($proposal, $person);
            } else {
                $engine->archive($proposal, $person);
                PageVersion::query()
                    ->whereKey($proposal->subject_id)
                    ->where('status', PageVersionStatus::InReview->value)
                    ->update(['status' => PageVersionStatus::Draft->value]);
            }
        } catch (ProposalStateException|ProposalConflictException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        if ($pageId === null) {
            $this->errorMessage = 'De pagina voor dit voorstel bestaat niet meer.';

            return;
        }

        $this->redirectRoute('admin.pages.editor', ['page' => $pageId]);
    }

    /**
     * Markeer een afgewezen voorstel als afgehandeld zonder opnieuw in te
     * dienen.
     */
    public function archive(int $proposalId, ProposalEngine $engine): void
    {
        $person = $this->requirePerson();

        $proposal = Proposal::query()
            ->where('id', $proposalId)
            ->where('proposed_by_person_id', $person->id)
            ->firstOrFail();

        $this->runEngineAction(
            fn () => $engine->archive($proposal, $person),
            'Voorstel gearchiveerd.',
        );
    }

    /**
     * Keur de huidige stap van een voorstel goed (waarop je als beslisser
     * bent toegewezen). Een optioneel bijgeschreven toelichting gaat mee.
     */
    public function approve(int $stepId, ProposalEngine $engine): void
    {
        $person = $this->requirePerson();
        $step = ReviewStep::query()->findOrFail($stepId);
        $reason = $this->consumeReason($stepId);

        $this->runEngineAction(
            fn () => $engine->approveStep($step, $person, $reason),
            'Je hebt het voorstel goedgekeurd.',
        );
    }

    public function reject(int $stepId, ProposalEngine $engine): void
    {
        $person = $this->requirePerson();
        $reason = trim($this->reasonInputs[$stepId] ?? '');

        if ($reason === '') {
            $this->addError("reason.{$stepId}", 'Geef een reden op voor het afwijzen.');

            return;
        }

        $step = ReviewStep::query()->findOrFail($stepId);

        $this->runEngineAction(
            fn () => $engine->reject($step, $person, $reason),
            'Je hebt het voorstel afgewezen.',
        );
        unset($this->reasonInputs[$stepId]);
    }

    public function returnToSubmitter(int $stepId, ProposalEngine $engine): void
    {
        $person = $this->requirePerson();
        $reason = trim($this->reasonInputs[$stepId] ?? '');

        if ($reason === '') {
            $this->addError("reason.{$stepId}", 'Geef een reden op voor het terugsturen.');

            return;
        }

        $step = ReviewStep::query()->findOrFail($stepId);

        $this->runEngineAction(
            fn () => $engine->returnToSubmitter($step, $person, $reason),
            'Je hebt het voorstel teruggestuurd naar de indiener.',
        );
        unset($this->reasonInputs[$stepId]);
    }

    /**
     * Alle eigen ingediende voorstellen, ongeacht status — nieuwste eerst.
     *
     * @return Collection<int, Proposal>
     */
    #[Computed]
    public function myProposals(): Collection
    {
        $person = $this->currentPerson();
        if ($person === null) {
            return new Collection;
        }

        return Proposal::query()
            ->where('proposed_by_person_id', $person->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Proposal>
     */
    #[Computed]
    public function myOpenProposals(): Collection
    {
        return $this->myProposals()->filter(fn (Proposal $p) => $p->status->isOpen())->values();
    }

    /**
     * Afgewezen voorstellen die de indiener nog niet heeft afgehandeld —
     * blijven zichtbaar totdat gekozen is tussen opnieuw indienen en
     * archiveren.
     *
     * @return Collection<int, Proposal>
     */
    #[Computed]
    public function myRejectedProposals(): Collection
    {
        return $this->myProposals()->filter(fn (Proposal $p) => $p->needsRejectionAction())->values();
    }

    /**
     * @return Collection<int, Proposal>
     */
    #[Computed]
    public function myClosedProposals(): Collection
    {
        return $this->myProposals()
            ->filter(fn (Proposal $p) => $p->status->isClosed() && ! $p->needsRejectionAction())
            ->values();
    }

    /**
     * Stappen waarop het ingelogde lid nu daadwerkelijk kan beslissen
     * (persoon/rol/groep-toewijzing, inclusief impliciete Beheerder-toegang).
     *
     * @return Collection<int, ReviewStep>
     */
    #[Computed]
    public function decidableSteps(): Collection
    {
        $person = $this->currentPerson();
        if ($person === null) {
            return new Collection;
        }

        return app(ReviewerResolver::class)
            ->decidableStepsQuery($person)
            ->with('proposal')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.portal.wijzigingsvoorstellen', [
            'myOpenProposals' => $this->myOpenProposals(),
            'myRejectedProposals' => $this->myRejectedProposals(),
            'myClosedProposals' => $this->myClosedProposals(),
            'decidableSteps' => $this->decidableSteps(),
        ]);
    }

    private function consumeReason(int $stepId): ?string
    {
        $reason = trim($this->reasonInputs[$stepId] ?? '');
        unset($this->reasonInputs[$stepId]);

        return $reason !== '' ? $reason : null;
    }

    private function runEngineAction(Closure $action, string $successMessage): void
    {
        $this->errorMessage = '';
        $this->statusMessage = '';

        try {
            $action();
        } catch (ProposalStateException|ProposalConflictException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = $successMessage;
    }

    private function currentPerson(): ?Person
    {
        return auth()->user()?->person;
    }

    private function requirePerson(): Person
    {
        $person = $this->currentPerson();
        abort_if($person === null, 403, 'Je account is niet gekoppeld aan een persoon.');

        return $person;
    }
}
