<?php

namespace App\Livewire\Admin;

use App\Enums\WordpressContentType;
use App\Enums\WordpressImportStatus;
use App\Models\WordpressImportItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Overzicht van de gestagede WordPress-import (§25). Toont de items uit
 * `wordpress_import_items`, sorteerbaar en filterbaar op status/type. Alle
 * beslissingen (overnemen/archiveren/terugzetten) gebeuren op de detailpagina
 * (`WordpressImportDetail`), niet hier. Permissie: `wordpress_import.manage`.
 * Filter/sortering staan in de URL (`#[Url]`) — niet enkel om te kunnen
 * bookmarken, maar vooral omdat de detailpagina dezelfde parameternamen
 * gebruikt om terug te linken: zonder dit verloor "Terug naar overzicht" de
 * actieve filtering, terwijl de dropdowns zelf (browserstate) 'm nog wél
 * toonden.
 */
#[Layout('layouts.app', ['header' => 'WordPress-import'])]
class WordpressImportBeheer extends Component
{
    use WithPagination;

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public string $filterType = '';

    #[Url(as: 'sort')]
    public string $sortField = 'wordpress_published_at';

    #[Url(as: 'direction')]
    public string $sortDirection = 'desc';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->statusMessage = session('wordpress_import_status');
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, WordpressImportItem::SORTABLE_COLUMNS, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.admin.wordpress-import-beheer', [
            'items' => $this->items(),
            'statuses' => WordpressImportStatus::cases(),
            'types' => WordpressContentType::cases(),
        ]);
    }

    private function items(): LengthAwarePaginator
    {
        return WordpressImportItem::query()
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterType !== '', fn ($q) => $q->where('wordpress_type', $this->filterType))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(25);
    }
}
