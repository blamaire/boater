<?php

namespace App\Livewire\Admin;

use App\Enums\BlockType;
use App\Enums\PageVisibility;
use App\Enums\WordpressContentType;
use App\Enums\WordpressImportStatus;
use App\Models\Page;
use App\Models\WordpressImportItem;
use App\Models\WordpressImportMediaItem;
use App\Services\Audit\AuditLogger;
use App\Services\Cms\BlockContentSanitizer;
use App\Services\Cms\WordpressImportService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Detailpagina voor één gestaged WordPress-import-item (§25). Hier beslist
 * een beheerder: overnemen (wordt een echte CMS-pagina, als concept),
 * archiveren, of — na een eerdere beslissing — terugzetten naar nieuw.
 * "Overnemen/archiveren en volgende" springt door naar het eerstvolgende
 * nog-niet-besliste item in dezelfde sortering/filter als het overzicht.
 * Permissie: `wordpress_import.manage`.
 */
#[Layout('layouts.app', ['header' => 'WordPress-import'])]
class WordpressImportDetail extends Component
{
    public WordpressImportItem $item;

    public string $sortField = 'wordpress_published_at';

    public string $sortDirection = 'desc';

    public string $filterType = '';

    public string $filterStatus = '';

    public string $visibility = PageVisibility::Restricted->value;

    public ?int $parentId = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(WordpressImportItem $item): void
    {
        $this->item = $item->load('mediaItems');

        $sort = request()->query('sort');
        $this->sortField = is_string($sort) && in_array($sort, WordpressImportItem::SORTABLE_COLUMNS, true)
            ? $sort
            : 'wordpress_published_at';

        $this->sortDirection = request()->query('direction') === 'asc' ? 'asc' : 'desc';

        $filterType = request()->query('filterType');
        $this->filterType = is_string($filterType) && in_array($filterType, array_column(WordpressContentType::cases(), 'value'), true)
            ? $filterType
            : '';

        $filterStatus = request()->query('filterStatus');
        $this->filterStatus = is_string($filterStatus) && in_array($filterStatus, array_column(WordpressImportStatus::cases(), 'value'), true)
            ? $filterStatus
            : '';

        if ($this->item->wordpress_type === WordpressContentType::Page && $this->item->wordpress_parent_id !== null) {
            $oldParent = WordpressImportItem::query()->where('wordpress_id', $this->item->wordpress_parent_id)->first();

            if ($oldParent?->page_id !== null) {
                $this->parentId = $oldParent->page_id;
            }
        }
    }

    public function decideMedia(int $mediaItemId, bool $accept): void
    {
        $this->authorizeManage();

        $mediaItem = WordpressImportMediaItem::with('importItem')->findOrFail($mediaItemId);

        abort_unless($mediaItem->importItem->status === WordpressImportStatus::New, 403);

        $mediaItem->update(['selected' => $accept]);

        $this->item->load('mediaItems');
    }

    public function acceptAllMedia(): void
    {
        $this->authorizeManage();
        abort_unless($this->item->status === WordpressImportStatus::New, 403);

        $ids = $this->visibleMediaItems()->whereNull('media_asset_id')->pluck('id');
        WordpressImportMediaItem::query()->whereIn('id', $ids)->update(['selected' => true]);
        $this->item->load('mediaItems');
    }

    public function rejectAllMedia(): void
    {
        $this->authorizeManage();
        abort_unless($this->item->status === WordpressImportStatus::New, 403);

        $ids = $this->visibleMediaItems()->whereNull('media_asset_id')->pluck('id');
        WordpressImportMediaItem::query()->whereIn('id', $ids)->update(['selected' => false]);
        $this->item->load('mediaItems');
    }

    public function accept(bool $andNext, WordpressImportService $service, AuditLogger $audit): void
    {
        $this->authorizeManage();

        $next = $andNext ? $this->nextItem() : null;

        try {
            $page = $service->takeOver($this->item, $audit, PageVisibility::from($this->visibility), $this->parentId);
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->errorMessage = null;

        if ($andNext) {
            $this->goToNextOrIndex($next);

            return;
        }

        $this->redirectRoute('admin.pages.editor', ['page' => $page->id], navigate: false);
    }

    public function archive(bool $andNext, AuditLogger $audit): void
    {
        $this->authorizeManage();

        if ($this->item->status !== WordpressImportStatus::New) {
            $this->errorMessage = 'Alleen nieuwe items kunnen gearchiveerd worden.';

            return;
        }

        $next = $andNext ? $this->nextItem() : null;

        $this->item->update(['status' => WordpressImportStatus::Archived]);

        $audit->log('wordpress_import.archived', $this->item, after: [
            'wordpress_id' => $this->item->wordpress_id,
            'title' => $this->item->title,
        ]);

        $this->errorMessage = null;

        if ($andNext) {
            $this->goToNextOrIndex($next);

            return;
        }

        $this->statusMessage = 'Item gearchiveerd.';
    }

    public function restoreToNew(AuditLogger $audit): void
    {
        $this->authorizeManage();

        $allowed = $this->item->status === WordpressImportStatus::Archived
            || ($this->item->status === WordpressImportStatus::Imported && $this->item->page_id === null);

        if (! $allowed) {
            $this->errorMessage = 'Dit item kan niet worden teruggezet naar nieuw.';

            return;
        }

        $this->item->update(['status' => WordpressImportStatus::New, 'page_id' => null]);

        $audit->log('wordpress_import.reset_to_new', $this->item, after: [
            'wordpress_id' => $this->item->wordpress_id,
            'title' => $this->item->title,
        ]);

        $this->errorMessage = null;
        $this->statusMessage = 'Item teruggezet naar nieuw.';
    }

    public function render(BlockContentSanitizer $sanitizer): View
    {
        $sanitized = $sanitizer->sanitize(BlockType::Text, ['html' => $this->item->content_html]);
        $position = $this->position();
        $visibleMediaItems = $this->visibleMediaItems();

        return view('livewire.admin.wordpress-import-detail', [
            'previewHtml' => $this->annotatePreviewImages($sanitized['html'] ?? '', $visibleMediaItems),
            'position' => $position['position'],
            'total' => $position['total'],
            'pages' => Page::query()->orderBy('title')->get(),
            'visibilities' => PageVisibility::cases(),
            'ancestors' => $this->ancestorChain(),
            'visibleMediaItems' => $visibleMediaItems,
        ]);
    }

    /**
     * Markeert elke `<img>` in de voorvertoning met een gekleurde rand en een
     * label ónder de afbeelding (bewust niet overlappend eróvér — de foto zelf
     * kan elke kleur hebben, dus alleen een label op de eigen paginakleur
     * blijft altijd leesbaar) dat de beslisstatus van de bijbehorende bijlage
     * toont. Zelfde kleurenpalet als de statuspil elders op deze pagina.
     *
     * @param  Collection<int, WordpressImportMediaItem>  $mediaItems
     */
    private function annotatePreviewImages(string $html, Collection $mediaItems): string
    {
        if ($html === '' || $mediaItems->isEmpty()) {
            return $html;
        }

        return preg_replace_callback('/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', function (array $match) use ($mediaItems): string {
            $base = WordpressImportMediaItem::normalizedBaseFilename($match[1]);

            $mediaItem = $mediaItems->first(
                fn (WordpressImportMediaItem $m): bool => WordpressImportMediaItem::normalizedBaseFilename($m->url) === $base
            );

            if ($mediaItem === null) {
                return $match[0];
            }

            [$label, $borderClass, $badgeClass] = match (true) {
                $mediaItem->media_asset_id !== null => ['Overgenomen', 'border-green-500', 'bg-green-50 text-green-800 border-green-200'],
                $mediaItem->download_error !== null => ['Mislukt', 'border-red-500', 'bg-red-50 text-red-800 border-red-200'],
                $mediaItem->selected === false => ['Niet overgenomen', 'border-gray-400', 'bg-gray-100 text-gray-600 border-gray-200'],
                default => ['Nog geen besluit', 'border-yellow-500', 'bg-yellow-50 text-yellow-800 border-yellow-200'],
            };

            $imgTag = preg_replace('/^<img\b/i', '<img class="border-4 rounded '.$borderClass.'"', $match[0], 1);

            return '<span class="inline-block align-top">'.$imgTag
                .'<span class="block mt-1 w-fit rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap '.$badgeClass.'">'
                .e($label).'</span></span>';
        }, $html) ?? $html;
    }

    /**
     * Alle bijlagen uit de hele import die daadwerkelijk in de content van dit
     * item voorkomen — enkel in die context is te beoordelen of iets
     * overgenomen moet worden. Bewust project-breed gezocht, niet beperkt tot
     * bijlagen die WordPress via wp:post_parent aan dit item koppelde: een
     * afbeelding die ooit via pagina A geüpload is, wordt in de praktijk vaak
     * ook op pagina B getoond. Bijlagen die nergens in de tekst gebruikt
     * worden, blijven hier bewust buiten beeld.
     *
     * @return Collection<int, WordpressImportMediaItem>
     */
    private function visibleMediaItems(): Collection
    {
        return WordpressImportMediaItem::query()
            ->get()
            ->filter(fn (WordpressImportMediaItem $m): bool => $m->isReferencedIn($this->item->content_html))
            ->values();
    }

    /**
     * Loopt de oude WordPress-ouderketen van dit item op, root eerst. Alleen
     * zinvol voor pagina's (WordPress kent daar een hiërarchie voor, berichten
     * niet). Stopt bij een ontbrekende schakel (geen gestaged item met dat
     * wordpress_id) of na 20 niveaus als cirkelbescherming.
     *
     * @return array<int, WordpressImportItem>
     */
    private function ancestorChain(): array
    {
        if ($this->item->wordpress_type !== WordpressContentType::Page) {
            return [];
        }

        $chain = [];
        $seen = [];
        $parentWordpressId = $this->item->wordpress_parent_id;

        while ($parentWordpressId !== null && count($chain) < 20 && ! in_array($parentWordpressId, $seen, true)) {
            $seen[] = $parentWordpressId;

            $ancestor = WordpressImportItem::query()->where('wordpress_id', $parentWordpressId)->first();

            if ($ancestor === null) {
                break;
            }

            $chain[] = $ancestor;
            $parentWordpressId = $ancestor->wordpress_parent_id;
        }

        return array_reverse($chain);
    }

    /**
     * @return array{position: int|null, total: int}
     */
    private function position(): array
    {
        $ids = WordpressImportItem::query()
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterType !== '', fn ($q) => $q->where('wordpress_type', $this->filterType))
            ->orderBy($this->sortField, $this->sortDirection)
            ->orderBy('id')
            ->pluck('id');

        $index = $ids->search($this->item->id);

        return ['position' => $index === false ? null : $index + 1, 'total' => $ids->count()];
    }

    /**
     * Het eerstvolgende item ná het huidige in de actieve sortering/filter dat
     * nog niet geaccepteerd/gearchiveerd is. Wordt bewust vóór de mutatie van
     * `$this->item->status` bepaald: op dat moment staat het huidige item nog
     * in de Nieuw-subset, dus we vinden gewoon de eerstvolgende rij erna.
     */
    private function nextItem(): ?WordpressImportItem
    {
        $ids = WordpressImportItem::query()
            ->where('status', WordpressImportStatus::New)
            ->when($this->filterType !== '', fn ($q) => $q->where('wordpress_type', $this->filterType))
            ->orderBy($this->sortField, $this->sortDirection)
            ->orderBy('id')
            ->pluck('id');

        $position = $ids->search($this->item->id);
        $nextId = $position === false ? $ids->first() : $ids->get($position + 1);

        return $nextId ? WordpressImportItem::find($nextId) : null;
    }

    private function goToNextOrIndex(?WordpressImportItem $next): void
    {
        if ($next === null) {
            session()->flash('wordpress_import_status', 'Geen openstaande items meer met de huidige filter.');
            $this->redirectRoute('admin.wordpress-import.index', navigate: false);

            return;
        }

        $this->redirectRoute('admin.wordpress-import.show', [
            'item' => $next->id,
            'sort' => $this->sortField,
            'direction' => $this->sortDirection,
            'filterType' => $this->filterType,
            'filterStatus' => $this->filterStatus,
        ], navigate: false);
    }

    /**
     * `Livewire::test()` omzeilt route-middleware bij losse actie-calls, dus de
     * route-`can:wordpress_import.manage` dekt in tests alleen het bezoeken van
     * de pagina. Deze expliciete check zorgt dat mutaties ook los van dat
     * mechanisme afgedwongen blijven (zie ook `WordpressImportBeheer`, voorheen).
     */
    private function authorizeManage(): void
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->can('wordpress_import.manage'), 403);
    }
}
