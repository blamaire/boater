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

    public function decideMedia(int $mediaItemId, ?bool $accept): void
    {
        $this->authorizeManage();

        // Bewust de status van hét bekeken item (niet van $mediaItem->importItem,
        // het item waar WordPress de bijlage ooit formeel aan koppelde via
        // wp:post_parent) — media wordt sinds de project-brede content-matching
        // vaak getoond bij een ánder item dan waar ze oorspronkelijk bij hoorde.
        abort_unless($this->item->status === WordpressImportStatus::New, 403);

        $mediaItem = WordpressImportMediaItem::query()->findOrFail($mediaItemId);
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
     * Markeert elke `<img>` in de voorvertoning met vier knoppen midden op de
     * afbeelding: nog niet bepaald / overnemen / niet overnemen / openen op
     * volledige grootte. Elke knop heeft een eigen ondoorzichtige achtergrond
     * (wit als inactief, gekleurd als actief) — bewust geen overlay-vlak over
     * de hele foto, alleen achter de knoppen zelf, en de knoppen zijn absoluut
     * gepositioneerd zodat de tekstuitlijning van de omringende content niet
     * verschuift. Kan (via `decideMedia()`) niet via de gewone Blade-syntax:
     * dit is kale HTML die als string in reeds gerenderde content geïnjecteerd
     * wordt, dus geen `<x-action-icon>`-componenten maar losse inline SVG's.
     *
     * @param  Collection<int, WordpressImportMediaItem>  $mediaItems
     */
    private function annotatePreviewImages(string $html, Collection $mediaItems): string
    {
        if ($html === '' || $mediaItems->isEmpty()) {
            return $html;
        }

        $isNew = $this->item->status === WordpressImportStatus::New;

        // WordPress wikkelt een afbeelding vaak zelf al in <a href="...volledige-grootte...">
        // (standaard "Link naar"-instelling bij het invoegen). Die omwikkelende
        // link (indien aanwezig) wordt hier meegepakt en laten vallen — anders
        // zit onze eigen knoppenrij ín die link, en opent elke klik ook de
        // oude link ernaast.
        return preg_replace_callback('/(?:<a\b[^>]*>\s*)?(<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>)(?:\s*<\/a>)?/i', function (array $match) use ($mediaItems, $isNew): string {
            $imgTag = $match[1];
            $base = WordpressImportMediaItem::normalizedBaseFilename($match[2]);

            $mediaItem = $mediaItems->first(
                fn (WordpressImportMediaItem $m): bool => WordpressImportMediaItem::normalizedBaseFilename($m->url) === $base
            );

            if ($mediaItem === null) {
                return $match[0];
            }

            $label = match (true) {
                $mediaItem->media_asset_id !== null => 'Overgenomen',
                $mediaItem->download_error !== null => 'Mislukt: '.$mediaItem->download_error,
                $mediaItem->selected === false => 'Niet overgenomen',
                $mediaItem->selected === true => 'Overnemen gekozen',
                default => 'Nog geen besluit',
            };

            $buttons = $isNew
                ? $this->previewIconButton('M5 12h14', 'Nog niet bepaald', $mediaItem->selected === null, wireClick: "decideMedia({$mediaItem->id}, null)", activeClass: 'bg-gray-800 text-white')
                    .$this->previewIconButton('m4.5 12.75 6 6 9-13.5', 'Overnemen', $mediaItem->selected === true, wireClick: "decideMedia({$mediaItem->id}, true)", activeClass: 'bg-green-600 text-white')
                    .$this->previewIconButton('M6 18 18 6M6 6l12 12', 'Niet overnemen', $mediaItem->selected === false, wireClick: "decideMedia({$mediaItem->id}, false)", activeClass: 'bg-red-600 text-white')
                : '';

            $buttons .= $this->previewIconButton(
                'M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15',
                'Openen op volledige grootte',
                active: false,
                href: $mediaItem->url,
            );

            // Een eventuele wp-align-*-class (zie BlockContentSanitizer /
            // WordpressAlignmentClassSanitizer) verplaatst hier naar de
            // wrapper i.p.v. op de <img> te blijven staan: de wrapper is
            // inline-block voor de knoppenoverlay, en een float op een kind
            // dáárvan blijft "gevangen" in die eigen block-formatting-context
            // (geen tekst-wrap-effect). Op de wrapper zelf werkt het wel.
            $wrapperClass = 'relative inline-block';
            if (preg_match('/\bclass=["\'](wp-align-(?:left|right|center))["\']/', $imgTag, $alignMatch) === 1) {
                $wrapperClass .= ' '.$alignMatch[1];
                $imgTag = preg_replace('/\s*class=["\'][^"\']*["\']/', '', $imgTag, 1) ?? $imgTag;
            }

            // Een grijze rechthoek achter/onder de knoppen zelf (niet de hele
            // inset-0-laag) geeft voldoende contrast op elke onderliggende
            // foto, ook lichte of witte achtergronden waar de losse witte
            // knoppen anders in wegvallen.
            return '<span class="'.$wrapperClass.'" title="'.e($label).'">'.$imgTag
                .'<span class="absolute inset-0 flex items-center justify-center">'
                .'<span class="flex items-center gap-1.5 rounded-lg bg-gray-700/80 p-1.5 shadow">'.$buttons.'</span>'
                .'</span></span>';
        }, $html) ?? $html;
    }

    private function previewIconButton(string $iconPath, string $title, bool $active, ?string $wireClick = null, ?string $href = null, string $activeClass = 'bg-gray-800 text-white'): string
    {
        $classes = 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full shadow '
            .($active ? $activeClass : 'bg-white text-gray-500 hover:text-gray-900');

        $icon = '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
            .'<path stroke-linecap="round" stroke-linejoin="round" d="'.$iconPath.'"></path></svg>';

        if ($href !== null) {
            return '<a href="'.e($href).'" target="_blank" rel="noopener" title="'.e($title).'" class="'.$classes.'">'.$icon.'</a>';
        }

        return '<button type="button" wire:click="'.$wireClick.'" title="'.e($title).'" class="'.$classes.'">'.$icon.'</button>';
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
