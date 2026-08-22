<?php

namespace App\Livewire\Admin;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Services\Contact\ContactRequestService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detailscherm van één contactverzoek — dit ís de pagina waar de
 * notificatiemail naar de verantwoordelijke naartoe linkt, zodat die er
 * meteen de status kan bijwerken. Permissie: `contact_requests.manage`
 * (zelfde als het lijstscherm).
 */
#[Layout('layouts.app', ['header' => 'Contactverzoek'])]
class ContactVerzoekDetail extends Component
{
    public ContactRequest $contactRequest;

    public function mount(ContactRequest $contactRequest): void
    {
        $this->contactRequest = $contactRequest->load('topic.responsible');
    }

    public function updateStatus(string $status, ContactRequestService $service): void
    {
        $newStatus = ContactRequestStatus::tryFrom($status);
        if ($newStatus === null) {
            return;
        }

        $actor = auth()->user()?->person;
        if ($actor === null) {
            return;
        }

        $service->changeStatus($this->contactRequest, $newStatus, $actor);
        $this->contactRequest->refresh();
    }

    public function render(): View
    {
        return view('livewire.admin.contact-verzoek-detail', [
            'statuses' => ContactRequestStatus::cases(),
        ]);
    }
}
