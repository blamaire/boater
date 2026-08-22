<?php

namespace App\Livewire\Admin;

use App\Models\ContactRequest;
use App\Models\ContactTopic;
use App\Models\Person;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer van contact-onderwerpen (naam + verantwoordelijke persoon) voor het
 * publieke contactformulier. Eenvoudige 2-velden-CRUD → inline-add, geen
 * apart formulier/modal nodig. Permissie: `contact_topics.manage`.
 */
#[Layout('layouts.app', ['header' => 'Contact-onderwerpen'])]
class ContactOnderwerpBeheer extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public ?int $responsible_person_id = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function edit(int $id): void
    {
        $topic = ContactTopic::query()->findOrFail($id);
        $this->editingId = $topic->id;
        $this->name = $topic->name;
        $this->responsible_person_id = $topic->responsible_person_id;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'responsible_person_id']);
    }

    public function save(AuditLogger $audit): void
    {
        $creating = $this->editingId === null;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'responsible_person_id' => ['required', 'integer', 'exists:persons,id'],
        ]);

        if ($creating) {
            $topic = ContactTopic::create($data);
            $audit->log('contact_topic.created', $topic, after: $data);
            $this->statusMessage = "Onderwerp [{$topic->name}] aangemaakt.";
        } else {
            $topic = ContactTopic::query()->findOrFail($this->editingId);
            $before = ['name' => $topic->name, 'responsible_person_id' => $topic->responsible_person_id];
            $topic->update($data);
            $audit->log('contact_topic.updated', $topic, before: $before, after: $data);
            $this->statusMessage = "Onderwerp [{$topic->name}] bijgewerkt.";
        }

        $this->errorMessage = null;
        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $topic = ContactTopic::query()->findOrFail($id);

        if (ContactRequest::query()->where('contact_topic_id', $topic->id)->exists()) {
            $this->errorMessage = "Onderwerp [{$topic->name}] heeft al contactverzoeken en kan niet verwijderd worden.";

            return;
        }

        $audit->log('contact_topic.deleted', $topic, before: ['name' => $topic->name]);
        $topic->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }
        $this->errorMessage = null;
        $this->statusMessage = 'Onderwerp verwijderd.';
    }

    public function render(): View
    {
        return view('livewire.admin.contact-onderwerp-beheer', [
            'topics' => ContactTopic::query()->with('responsible')->orderBy('sort_order')->orderBy('name')->get(),
            'persons' => Person::query()->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }
}
