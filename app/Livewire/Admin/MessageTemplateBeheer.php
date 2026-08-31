<?php

namespace App\Livewire\Admin;

use App\Enums\MessageBlockType;
use App\Enums\MessageType;
use App\Models\MessageTemplate;
use App\Models\MessageTemplateFolder;
use App\Services\Audit\AuditLogger;
use App\Services\Communication\MessageVariableRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor berichtsjablonen (§24.4 MESSAGE_TEMPLATE) — een geneste
 * mappenstructuur met twee vaste root-mappen: "Systeemberichten"
 * (transactioneel, code-gestuurd — niet aan te maken of te verwijderen door
 * een beheerder) en "Mailings" (redactioneel, vrij te beheren). `type` en
 * `key` worden niet meer los ingevoerd: `type` volgt uit de gekozen map,
 * `key` wordt bij aanmaken server-side gegenereerd en nergens getoond.
 * Permissie: `message_templates.manage`.
 */
#[Layout('layouts.app', ['header' => 'Berichtsjablonen'])]
class MessageTemplateBeheer extends Component
{
    /** Huidige map in de drill-down-browser; null = virtuele root (toont alleen de 2 root-mappen). */
    public ?int $currentFolderId = null;

    public string $newFolderName = '';

    public ?int $editingFolderId = null;

    public string $editingFolderName = '';

    public ?int $editingId = null;

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

    /**
     * Systeembepaald (§24.4) — komt uit `MessageVariableRegistry`, niet iets
     * wat de beheerder zelf invult. Puur voor de "variabele invoegen"-UI.
     *
     * @var array<int, string>
     */
    public array $availableVariables = [];

    public ?string $statusMessage = null;

    public function openFolder(?int $id = null): void
    {
        $this->currentFolderId = $id;
        $this->newFolderName = '';
        $this->resetErrorBag('newFolderName');
        $this->cancelRenameFolder();
    }

    public function addFolder(AuditLogger $audit): void
    {
        if ($this->currentFolderId === null) {
            return;
        }

        $name = trim($this->newFolderName);
        if ($name === '') {
            $this->addError('newFolderName', 'Vul een naam in.');

            return;
        }

        $folder = MessageTemplateFolder::query()->create([
            'name' => $name,
            'parent_id' => $this->currentFolderId,
            'is_system' => false,
        ]);
        $audit->log('message_template_folder.created', $folder, after: ['name' => $folder->name, 'parent_id' => $folder->parent_id]);
        $this->newFolderName = '';
    }

    public function startRenameFolder(int $id): void
    {
        $folder = MessageTemplateFolder::query()->findOrFail($id);
        if ($folder->is_system) {
            return;
        }

        $this->editingFolderId = $folder->id;
        $this->editingFolderName = $folder->name;
    }

    public function cancelRenameFolder(): void
    {
        $this->editingFolderId = null;
        $this->editingFolderName = '';
    }

    public function renameFolder(AuditLogger $audit): void
    {
        if ($this->editingFolderId === null) {
            return;
        }

        $folder = MessageTemplateFolder::query()->findOrFail($this->editingFolderId);
        if ($folder->is_system) {
            return;
        }

        $name = trim($this->editingFolderName);
        if ($name === '') {
            $this->addError('editingFolderName', 'Vul een naam in.');

            return;
        }

        $before = ['name' => $folder->name];
        $folder->update(['name' => $name]);
        $audit->log('message_template_folder.updated', $folder, before: $before, after: ['name' => $folder->name]);
        $this->cancelRenameFolder();
    }

    public function deleteFolder(int $id, AuditLogger $audit): void
    {
        $folder = MessageTemplateFolder::query()->findOrFail($id);
        if ($folder->is_system) {
            return;
        }

        if ($folder->children()->exists()) {
            $this->statusMessage = "Map [{$folder->name}] kan niet worden verwijderd — er zijn submappen aan gekoppeld.";

            return;
        }
        if ($folder->templates()->exists()) {
            $this->statusMessage = "Map [{$folder->name}] kan niet worden verwijderd — er zijn sjablonen aan gekoppeld.";

            return;
        }

        $audit->log('message_template_folder.deleted', $folder, before: ['name' => $folder->name]);
        $folder->delete();
        $this->statusMessage = "Map [{$folder->name}] verwijderd.";
    }

    public function edit(int $id): void
    {
        $template = MessageTemplate::query()->findOrFail($id);
        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->subject = $template->subject;
        $this->blocks = $template->body;
        $this->availableVariables = MessageVariableRegistry::for($template->key);

        $this->dispatch('open-modal', 'message-template-form');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'subject', 'blocks']);
        $this->availableVariables = [];
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

    public function deleteTemplate(int $id, AuditLogger $audit): void
    {
        $template = MessageTemplate::query()->findOrFail($id);
        if ($template->type === MessageType::Transactioneel) {
            return;
        }

        $audit->log('message_template.deleted', $template, before: ['key' => $template->key, 'name' => $template->name]);
        $template->delete();
        $this->statusMessage = "Sjabloon [{$template->name}] verwijderd.";
    }

    public function save(AuditLogger $audit): void
    {
        $creating = $this->editingId === null;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
        ]);

        if (! $this->validateBlocks()) {
            return;
        }

        if ($creating) {
            if ($this->currentFolderId === null) {
                $this->addError('name', 'Kies eerst een map onder Mailings om een sjabloon aan te maken.');

                return;
            }

            $folder = MessageTemplateFolder::query()->findOrFail($this->currentFolderId);
            if ($folder->root()->name === 'Systeemberichten') {
                $this->addError('name', 'Er kunnen geen sjablonen worden aangemaakt in Systeemberichten.');

                return;
            }

            $attributes = [
                'key' => $this->generateUniqueKey($data['name']),
                'name' => $data['name'],
                'subject' => $data['subject'],
                'body' => $this->blocks,
                'type' => MessageType::Redactioneel,
                'message_template_folder_id' => $folder->id,
            ];
            $template = MessageTemplate::query()->create($attributes);
            $audit->log('message_template.created', $template, after: $attributes);
            $this->statusMessage = "Sjabloon [{$template->name}] aangemaakt.";
        } else {
            $template = MessageTemplate::query()->findOrFail($this->editingId);
            $attributes = [
                'name' => $data['name'],
                'subject' => $data['subject'],
                'body' => $this->blocks,
            ];
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

    /**
     * `key` wordt nergens meer getoond of ingevoerd (§24.4) — bij aanmaken
     * server-side afgeleid van de titel, met een oplopende suffix bij
     * botsing. Bij bewerken blijft de bestaande `key` ongewijzigd (triggers
     * verwijzen er hardcoded naar).
     */
    private function generateUniqueKey(string $name): string
    {
        $base = Str::slug($name, '_');
        if ($base === '') {
            $base = 'sjabloon';
        }

        $key = $base;
        $suffix = 2;
        while (MessageTemplate::query()->where('key', $key)->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }

    public function render(): View
    {
        $currentFolder = $this->currentFolderId !== null
            ? MessageTemplateFolder::query()->find($this->currentFolderId)
            : null;

        $subFolders = $currentFolder === null
            ? MessageTemplateFolder::query()->whereNull('parent_id')->orderBy('name')->get()
            : $currentFolder->children()->orderBy('name')->get();

        $breadcrumbs = $currentFolder === null ? [] : [...array_reverse($currentFolder->ancestors()), $currentFolder];

        $templatesInFolder = $currentFolder === null
            ? collect()
            : MessageTemplate::query()->where('message_template_folder_id', $currentFolder->id)->orderBy('name')->get();

        return view('livewire.admin.message-template-beheer', [
            'currentFolder' => $currentFolder,
            'subFolders' => $subFolders,
            'breadcrumbs' => $breadcrumbs,
            'templatesInFolder' => $templatesInFolder,
            'canCreateHere' => $currentFolder !== null && $currentFolder->root()->name === 'Mailings',
            'blockTypes' => MessageBlockType::cases(),
        ]);
    }
}
