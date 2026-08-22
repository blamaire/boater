<?php

namespace App\Livewire\Admin;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\ContactTopic;
use App\Services\Contact\ContactRequestService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Inbox van via het publieke contactformulier ingediende verzoeken. Puur een
 * logboek met een statusveld — geen goedkeuringsworkflow. Permissie:
 * `contact_requests.manage`.
 */
#[Layout('layouts.app', ['header' => 'Contactverzoeken'])]
class ContactVerzoekBeheer extends Component
{
    use WithPagination;

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public ?int $filterTopicId = null;

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTopicId(): void
    {
        $this->resetPage();
    }

    public function updateStatus(int $requestId, string $status, ContactRequestService $service): void
    {
        $newStatus = ContactRequestStatus::tryFrom($status);
        if ($newStatus === null) {
            return;
        }

        $actor = auth()->user()?->person;
        if ($actor === null) {
            return;
        }

        $request = ContactRequest::query()->findOrFail($requestId);
        $service->changeStatus($request, $newStatus, $actor);
    }

    public function render(): View
    {
        return view('livewire.admin.contact-verzoek-beheer', [
            'items' => $this->items(),
            'statuses' => ContactRequestStatus::cases(),
            'topics' => ContactTopic::query()->orderBy('name')->get(),
        ]);
    }

    private function items(): LengthAwarePaginator
    {
        return ContactRequest::query()
            ->with('topic')
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterTopicId !== null, fn ($q) => $q->where('contact_topic_id', $this->filterTopicId))
            ->orderByDesc('created_at')
            ->paginate(25);
    }
}
