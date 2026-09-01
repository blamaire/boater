<?php

namespace App\Livewire\Portal;

use App\Models\InAppNotification;
use App\Models\Person;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Volledige lijst van in-app-meldingen (§24.2) — de header-bel toont alleen
 * de meest recente. Bekijken van deze pagina markeert niets automatisch als
 * gelezen (dat gebeurt per melding via markAsRead, zodat een lid ook
 * ongelezen meldingen kan laten staan als "nog te doen").
 */
#[Layout('layouts.app', ['header' => 'Meldingen'])]
class Meldingen extends Component
{
    use WithPagination;

    public function markAsRead(int $id): void
    {
        $person = $this->requirePerson();

        $notification = InAppNotification::query()->where('person_id', $person->id)->find($id);
        $notification?->markAsRead();
    }

    public function markAllAsRead(): void
    {
        $person = $this->requirePerson();

        InAppNotification::query()
            ->where('person_id', $person->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $person = $this->requirePerson();

        /** @var LengthAwarePaginator<int, InAppNotification> $notifications */
        $notifications = InAppNotification::query()
            ->where('person_id', $person->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.portal.meldingen', ['notifications' => $notifications]);
    }

    private function requirePerson(): Person
    {
        $person = auth()->user()?->person;
        abort_if($person === null, 403, 'Je account is niet gekoppeld aan een persoon.');

        return $person;
    }
}
