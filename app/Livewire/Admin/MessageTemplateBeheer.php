<?php

namespace App\Livewire\Admin;

use App\Enums\MessageType;
use App\Models\MessageTemplate;
use App\Services\Audit\AuditLogger;
use App\Services\Communication\MessageVariableRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor berichtsjablonen (§24.4 MESSAGE_TEMPLATE) — transactionele
 * en redactionele sjablonen die `MessageDispatcher` gebruikt om e-mail te
 * versturen. Permissie: `message_templates.manage`.
 */
#[Layout('layouts.app', ['header' => 'Berichtsjablonen'])]
class MessageTemplateBeheer extends Component
{
    public ?int $editingId = null;

    public string $key = '';

    public string $name = '';

    public string $subject = '';

    public string $body = '';

    public string $type = 'transactioneel';

    /**
     * Systeembepaald (§24.4) — komt uit `MessageVariableRegistry`, niet iets
     * wat de beheerder zelf invult. Puur voor de "variabele invoegen"-UI.
     *
     * @var array<int, string>
     */
    public array $availableVariables = [];

    public ?string $statusMessage = null;

    public function edit(int $id): void
    {
        $template = MessageTemplate::query()->findOrFail($id);
        $this->editingId = $template->id;
        $this->key = $template->key;
        $this->name = $template->name;
        $this->subject = $template->subject;
        $this->body = $template->body;
        $this->type = $template->type->value;
        $this->refreshAvailableVariables();

        $this->dispatch('open-modal', 'message-template-form');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'key', 'name', 'subject', 'body']);
        $this->type = MessageType::Transactioneel->value;
        $this->refreshAvailableVariables();
    }

    public function updatedKey(): void
    {
        $this->refreshAvailableVariables();
    }

    private function refreshAvailableVariables(): void
    {
        $this->availableVariables = MessageVariableRegistry::for($this->key);
    }

    public function save(AuditLogger $audit): void
    {
        $creating = $this->editingId === null;

        $data = $this->validate([
            'key' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                $creating ? 'unique:message_templates,key' : 'in:'.$this->key,
            ],
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'type' => ['required', 'in:transactioneel,redactioneel'],
        ], [
            'key.regex' => 'Alleen kleine letters, cijfers en underscores.',
        ]);

        $attributes = [
            'key' => $data['key'],
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'type' => $data['type'],
        ];

        if ($creating) {
            $template = MessageTemplate::query()->create($attributes);
            $audit->log('message_template.created', $template, after: $attributes);
            $this->statusMessage = "Sjabloon [{$template->name}] aangemaakt.";
        } else {
            $template = MessageTemplate::query()->findOrFail($this->editingId);
            $before = $template->only(array_keys($attributes));
            $template->update($attributes);
            $audit->log('message_template.updated', $template, before: $before, after: $attributes);
            $this->statusMessage = "Sjabloon [{$template->name}] bijgewerkt.";
        }

        $this->resetForm();
        $this->dispatch('close-modal', 'message-template-form');
    }

    public function render(): View
    {
        return view('livewire.admin.message-template-beheer', [
            'templates' => MessageTemplate::query()->orderBy('name')->get(),
        ]);
    }
}
