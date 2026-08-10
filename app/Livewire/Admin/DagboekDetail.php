<?php

namespace App\Livewire\Admin;

use App\Models\Dagboek;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Read-only overzicht van de journaalposten in een dagboek. Permissie:
 * `dagboeken.manage` (zelfde als het dagboekbeheer zelf).
 */
#[Layout('layouts.app', ['header' => 'Dagboek'])]
class DagboekDetail extends Component
{
    public Dagboek $dagboek;

    public function mount(Dagboek $dagboek): void
    {
        $this->dagboek = $dagboek;
    }

    public function render(): View
    {
        return view('livewire.admin.dagboek-detail', [
            'entries' => $this->dagboek->journalEntries()
                ->with('lines.account')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
