<?php

namespace App\Livewire\Admin;

use App\Enums\DagboekType;
use App\Models\Dagboek;
use App\Models\JournalEntry;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor dagboeken. Verkoop/Inkoop/Memoriaal zijn singleton en al
 * geseed (`DagboekSeeder`); dit scherm laat alleen extra Bank-/Kas-dagboeken
 * aanmaken. Permissie: `dagboeken.manage`.
 */
#[Layout('layouts.app', ['header' => 'Dagboeken'])]
class DagboekBeheer extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'bank';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function edit(int $id): void
    {
        $dagboek = Dagboek::query()->findOrFail($id);
        $this->editingId = $dagboek->id;
        $this->name = $dagboek->name;
        $this->type = $dagboek->type->value;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name']);
        $this->type = 'bank';
    }

    public function save(AuditLogger $audit): void
    {
        $creating = $this->editingId === null;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:'.implode(',', array_column(DagboekType::cases(), 'value'))],
        ]);

        if ($creating && DagboekType::from($data['type'])->isSingleton()) {
            $this->addError('type', 'Dit dagboek bestaat al (singleton) en kan niet nogmaals aangemaakt worden.');

            return;
        }

        if ($creating) {
            $dagboek = Dagboek::create($data);
            $audit->log('dagboek.created', $dagboek, after: $data);
            $this->statusMessage = "Dagboek [{$dagboek->name}] aangemaakt.";
        } else {
            $dagboek = Dagboek::query()->findOrFail($this->editingId);
            $before = ['name' => $dagboek->name];
            $dagboek->update(['name' => $data['name']]);
            $audit->log('dagboek.updated', $dagboek, before: $before, after: ['name' => $data['name']]);
            $this->statusMessage = "Dagboek [{$dagboek->name}] bijgewerkt.";
        }

        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $dagboek = Dagboek::query()->findOrFail($id);

        if ($dagboek->type->isSingleton()) {
            $this->errorMessage = "Dagboek [{$dagboek->name}] is een vast dagboek (Verkoop/Inkoop/Memoriaal) en kan niet verwijderd worden.";

            return;
        }

        if (JournalEntry::query()->where('dagboek_id', $dagboek->id)->exists()) {
            $this->errorMessage = "Dagboek [{$dagboek->name}] heeft al journaalposten en kan niet verwijderd worden.";

            return;
        }

        $audit->log('dagboek.deleted', $dagboek, before: ['name' => $dagboek->name, 'type' => $dagboek->type->value]);
        $dagboek->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }
        $this->errorMessage = null;
        $this->statusMessage = 'Dagboek verwijderd.';
    }

    public function render(): View
    {
        return view('livewire.admin.dagboek-beheer', [
            'dagboeken' => Dagboek::query()->orderBy('type')->orderBy('name')->get(),
            'createableTypes' => array_filter(DagboekType::cases(), fn (DagboekType $t): bool => ! $t->isSingleton()),
        ]);
    }
}
