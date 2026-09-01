<?php

namespace App\Livewire\Portal;

use App\Models\InAppNotification;
use App\Models\Person;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Bel-icoon in de header (§24.2) — ongelezen-teller + dropdown met de
 * meest recente meldingen. Volledige lijst staat op `/mijn/meldingen`
 * (App\Livewire\Portal\Meldingen). Rendert stil (0, geen dropdown-inhoud)
 * voor een ingelogde user zonder gekoppelde Person.
 */
class NotificationBell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markAsRead(int $id): void
    {
        $person = $this->currentPerson();
        if ($person === null) {
            return;
        }

        $notification = InAppNotification::query()->where('person_id', $person->id)->find($id);
        $notification?->markAsRead();
    }

    #[Computed]
    public function unreadCount(): int
    {
        $person = $this->currentPerson();
        if ($person === null) {
            return 0;
        }

        return InAppNotification::query()
            ->where('person_id', $person->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return Collection<int, InAppNotification>
     */
    #[Computed]
    public function recent(): Collection
    {
        $person = $this->currentPerson();
        if ($person === null) {
            /** @var Collection<int, InAppNotification> $empty */
            $empty = new Collection;

            return $empty;
        }

        return InAppNotification::query()
            ->where('person_id', $person->id)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.portal.notification-bell');
    }

    private function currentPerson(): ?Person
    {
        return auth()->user()?->person;
    }
}
