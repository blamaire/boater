<?php

namespace App\Livewire\Portal;

use App\Enums\ProposalStatus;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReservableObject;
use App\Services\Proposals\Exceptions\ProposalStateException;
use App\Services\Proposals\Handlers\ReservationProposalHandler;
use App\Services\Proposals\ProposalEngine;
use App\Services\Reservations\ReservationSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Bewerken van een eigen, nog openstaande reserveringsaanvraag (subject_type
 * reservation.create). Object en begunstigde blijven bewust vast — dat
 * aanpassen is inhoudelijk een nieuwe aanvraag, geen bewerking van deze.
 * Opslaan trekt het oude voorstel in en dient de nieuwe periode opnieuw in
 * via ReservationSubmissionService — dezelfde service als de normale
 * reserveer-flow (App\Livewire\Portal\Reserveren), zodat drempel-evaluatie
 * en bypass-gedrag identiek blijven.
 */
#[Layout('layouts.app', ['header' => 'Reservering aanpassen'])]
class ReserveringBewerken extends Component
{
    public Proposal $proposal;

    public ?ReservableObject $object = null;

    public ?Person $beneficiary = null;

    public ?Person $requester = null;

    public string $startsAt = '';

    public string $endsAt = '';

    public string $note = '';

    public ?string $errorMessage = null;

    public function mount(Proposal $proposal): void
    {
        $person = $this->requirePerson();

        abort_unless($proposal->subject_type === ReservationProposalHandler::SUBJECT_TYPE, 404);
        abort_unless($proposal->proposed_by_person_id === $person->id, 403);
        abort_unless(
            $proposal->status->isOpen() || $proposal->status === ProposalStatus::Rejected,
            403,
            'Dit voorstel is al afgehandeld en kan niet meer worden aangepast.',
        );

        $this->proposal = $proposal;

        $payload = $proposal->payload;
        $this->object = ReservableObject::query()->find($payload['reservable_object_id'] ?? null);
        $this->beneficiary = Person::query()->find($payload['person_id'] ?? null);
        $this->requester = isset($payload['requested_by_person_id'])
            ? Person::query()->find($payload['requested_by_person_id'])
            : null;

        abort_if($this->object === null || $this->beneficiary === null, 404);

        $this->startsAt = isset($payload['starts_at']) ? Carbon::parse((string) $payload['starts_at'])->format('Y-m-d\TH:i') : '';
        $this->endsAt = isset($payload['ends_at']) ? Carbon::parse((string) $payload['ends_at'])->format('Y-m-d\TH:i') : '';
        $this->note = (string) ($payload['note'] ?? '');
    }

    public function save(ReservationSubmissionService $service, ProposalEngine $engine): void
    {
        $person = $this->requirePerson();
        $this->errorMessage = null;

        $this->validate([
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $requester = $this->requester ?? $person;

        try {
            if ($this->proposal->status->isOpen()) {
                $engine->withdraw($this->proposal, $person);
            }

            $outcome = $service->submit(
                $this->object,
                null,
                $this->beneficiary,
                Carbon::parse($this->startsAt),
                Carbon::parse($this->endsAt),
                $requester,
                $this->note !== '' ? $this->note : null,
            );

            if ($this->proposal->status === ProposalStatus::Rejected) {
                $engine->archive($this->proposal, $person);
            }
        } catch (ProposalStateException|RuntimeException $e) {
            // ProposalConflictException extends RuntimeException, dus die
            // wordt hier ook al gevangen.
            $this->errorMessage = $e->getMessage();

            return;
        }

        session()->flash('status', $outcome->wasReviewed()
            ? 'Je aangepaste aanvraag is opnieuw ingediend voor goedkeuring.'
            : "Reservering bijgewerkt en direct vastgelegd voor [{$this->object->name}].");
        $this->redirectRoute('portal.wijzigingsvoorstellen');
    }

    public function render(): View
    {
        return view('livewire.portal.reservering-bewerken');
    }

    private function requirePerson(): Person
    {
        $person = auth()->user()?->person;
        abort_if($person === null, 403, 'Je account is niet gekoppeld aan een persoon.');

        return $person;
    }
}
