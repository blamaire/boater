<?php

namespace App\Livewire\Admin;

use App\Models\WordpressImportMediaItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Projectbreed overzicht van de status van alle gestagede WordPress-bijlagen
 * (§25): overgenomen, mislukt, niet overgenomen of nog geen besluit. Puur
 * rapportage — beslissingen over een bijlage blijven aan de detailpagina van
 * het bijbehorende item voorbehouden (alleen dáár is de content-context
 * zichtbaar). Permissie: `wordpress_import.manage`.
 */
#[Layout('layouts.app', ['header' => 'WordPress-import — media'])]
class WordpressImportMediaOverzicht extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.admin.wordpress-import-media-overzicht', [
            'mediaItems' => $this->mediaItems(),
        ]);
    }

    private function mediaItems(): LengthAwarePaginator
    {
        return WordpressImportMediaItem::query()
            ->with('importItem')
            ->when($this->filterStatus === 'overgenomen', fn ($q) => $q->whereNotNull('media_asset_id'))
            ->when($this->filterStatus === 'mislukt', fn ($q) => $q->whereNull('media_asset_id')->whereNotNull('download_error'))
            ->when($this->filterStatus === 'niet_overgenomen', fn ($q) => $q->where('selected', false))
            ->when($this->filterStatus === 'nieuw', fn ($q) => $q->whereNull('selected'))
            ->orderByDesc('id')
            ->paginate(50);
    }
}
