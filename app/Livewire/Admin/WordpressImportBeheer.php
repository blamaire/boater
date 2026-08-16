<?php

namespace App\Livewire\Admin;

use App\Enums\WordpressContentType;
use App\Enums\WordpressImportStatus;
use App\Models\WordpressImportItem;
use App\Models\WordpressImportMediaItem;
use App\Services\Audit\AuditLogger;
use App\Services\Cms\WordpressImportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Beheerscherm voor de gestagede WordPress-import (§25). Toont de items uit
 * `wordpress_import_items` en laat een beheerder per item beslissen: overnemen
 * (wordt een echte CMS-pagina, als concept) of archiveren (blijft alleen in
 * staging). Permissie: `wordpress_import.manage`.
 */
#[Layout('layouts.app', ['header' => 'WordPress-import'])]
class WordpressImportBeheer extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public string $filterType = '';

    public ?int $viewingId = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function view(int $id): void
    {
        $this->viewingId = $id;
        $this->dispatch('open-modal', 'wordpress-import-preview');
    }

    public function takeOver(int $id, WordpressImportService $service, AuditLogger $audit): void
    {
        $this->authorizeManage();

        $item = WordpressImportItem::findOrFail($id);

        try {
            $page = $service->takeOver($item, $audit);
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->errorMessage = null;

        $this->redirectRoute('admin.pages.editor', ['page' => $page->id], navigate: false);
    }

    public function toggleMedia(int $mediaItemId): void
    {
        $this->authorizeManage();

        $mediaItem = WordpressImportMediaItem::with('importItem')->findOrFail($mediaItemId);

        abort_unless($mediaItem->importItem->status === WordpressImportStatus::New, 403);

        $mediaItem->update(['selected' => ! $mediaItem->selected]);
    }

    public function archive(int $id, AuditLogger $audit): void
    {
        $this->authorizeManage();

        $item = WordpressImportItem::findOrFail($id);

        if ($item->status !== WordpressImportStatus::New) {
            $this->errorMessage = 'Alleen nieuwe items kunnen gearchiveerd worden.';

            return;
        }

        $item->update(['status' => WordpressImportStatus::Archived]);

        $audit->log('wordpress_import.archived', $item, after: [
            'wordpress_id' => $item->wordpress_id,
            'title' => $item->title,
        ]);

        $this->errorMessage = null;
        $this->statusMessage = 'Item gearchiveerd.';
    }

    public function restoreToNew(int $id, AuditLogger $audit): void
    {
        $this->authorizeManage();

        $item = WordpressImportItem::findOrFail($id);

        $allowed = $item->status === WordpressImportStatus::Archived
            || ($item->status === WordpressImportStatus::Imported && $item->page_id === null);

        if (! $allowed) {
            $this->errorMessage = 'Dit item kan niet worden teruggezet naar nieuw.';

            return;
        }

        $item->update(['status' => WordpressImportStatus::New, 'page_id' => null]);

        $audit->log('wordpress_import.reset_to_new', $item, after: [
            'wordpress_id' => $item->wordpress_id,
            'title' => $item->title,
        ]);

        $this->errorMessage = null;
        $this->statusMessage = 'Item teruggezet naar nieuw.';
    }

    public function render(): View
    {
        return view('livewire.admin.wordpress-import-beheer', [
            'items' => $this->items(),
            'statuses' => WordpressImportStatus::cases(),
            'types' => WordpressContentType::cases(),
            'viewingItem' => $this->viewingId ? WordpressImportItem::with('mediaItems')->find($this->viewingId) : null,
        ]);
    }

    /**
     * `Livewire::test()` omzeilt route-middleware bij losse actie-calls (zie
     * Livewire's `RequestBroker::temporarilyDisableExceptionHandlingAndMiddleware`),
     * dus de route-`can:wordpress_import.manage` dekt in tests alleen het
     * bezoeken van de pagina. Deze expliciete check zorgt dat mutaties ook
     * los van dat mechanisme afgedwongen blijven (zie ook `MediaLibrary::deleteAsset()`).
     */
    private function authorizeManage(): void
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->can('wordpress_import.manage'), 403);
    }

    private function items(): LengthAwarePaginator
    {
        return WordpressImportItem::query()
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterType !== '', fn ($q) => $q->where('wordpress_type', $this->filterType))
            ->orderByDesc('wordpress_published_at')
            ->paginate(25);
    }
}
