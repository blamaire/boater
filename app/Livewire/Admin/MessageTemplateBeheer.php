<?php

namespace App\Livewire\Admin;

use App\Enums\MessageBlockType;
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
 * versturen. `body` is een blokkenlijst (§24, `MessageBlockType`), geen
 * platte HTML-string. Permissie: `message_templates.manage`.
 */
#[Layout('layouts.app', ['header' => 'Berichtsjablonen'])]
class MessageTemplateBeheer extends Component
{
    public ?int $editingId = null;

    public string $key = '';

    public string $name = '';

    public string $subject = '';

    /**
     * Publieke Livewire-property (dus in theorie client-manipuleerbaar) —
     * bewust een losse `array<string, mixed>` i.p.v. een strikte shape, zodat
     * `validateBlocks()` een ontbrekende/vervormde `type`/`content`-sleutel
     * altijd nog netjes als validatiefout kan afvangen i.p.v. een PHP-warning.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $blocks = [];

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
        $this->blocks = $template->body;
        $this->type = $template->type->value;
        $this->refreshAvailableVariables();

        $this->dispatch('open-modal', 'message-template-form');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'key', 'name', 'subject', 'blocks']);
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

    public function addBlock(string $type): void
    {
        $blockType = MessageBlockType::tryFrom($type);
        if ($blockType === null) {
            return;
        }

        $this->blocks[] = ['type' => $blockType->value, 'content' => $blockType->defaultContent()];
    }

    public function removeBlock(int $index): void
    {
        unset($this->blocks[$index]);
        $this->blocks = array_values($this->blocks);
    }

    public function moveBlock(int $index, string $direction): void
    {
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($target < 0 || $target >= count($this->blocks)) {
            return;
        }

        [$this->blocks[$index], $this->blocks[$target]] = [$this->blocks[$target], $this->blocks[$index]];
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
            'type' => ['required', 'in:transactioneel,redactioneel'],
        ], [
            'key.regex' => 'Alleen kleine letters, cijfers en underscores.',
        ]);

        if (! $this->validateBlocks()) {
            return;
        }

        $attributes = [
            'key' => $data['key'],
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $this->blocks,
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

    /**
     * Blocks worden niet via `$this->validate()` gevalideerd — een dynamische
     * lijst met per-type-verschillende verplichte velden leent zich beter
     * voor een handmatige check dan een generieke array-regelset.
     */
    private function validateBlocks(): bool
    {
        if (count($this->blocks) === 0) {
            $this->addError('blocks', 'Voeg minstens één blok toe.');

            return false;
        }

        foreach ($this->blocks as $index => $block) {
            $type = MessageBlockType::tryFrom($block['type'] ?? '');
            if ($type === null) {
                $this->addError("blocks.{$index}", 'Onbekend bloktype.');

                return false;
            }

            $content = $block['content'] ?? [];
            $incomplete = match ($type) {
                MessageBlockType::Text => trim((string) ($content['html'] ?? '')) === '',
                MessageBlockType::Heading => trim((string) ($content['text'] ?? '')) === '',
                MessageBlockType::Button => trim((string) ($content['label'] ?? '')) === '' || trim((string) ($content['href'] ?? '')) === '',
                MessageBlockType::Image => trim((string) ($content['url'] ?? '')) === '',
                MessageBlockType::Divider => false,
                MessageBlockType::Quote => trim((string) ($content['text'] ?? '')) === '',
            };

            if ($incomplete) {
                $this->addError("blocks.{$index}", 'Vul de verplichte velden van dit blok in.');

                return false;
            }
        }

        return true;
    }

    public function render(): View
    {
        return view('livewire.admin.message-template-beheer', [
            'templates' => MessageTemplate::query()->orderBy('name')->get(),
            'blockTypes' => MessageBlockType::cases(),
        ]);
    }
}
