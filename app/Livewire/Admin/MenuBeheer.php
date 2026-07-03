<?php

namespace App\Livewire\Admin;

use App\Models\NavItem;
use App\Models\Page;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor het handmatige hoofdmenu (§16). Als hier één of meer
 * zichtbare NavItems staan, gebruikt de PublicNavComposer die als menu;
 * zolang de tabel leeg is toont de publieke site automatisch de root-CMS-
 * pagina's als fallback.
 */
#[Layout('layouts.app')]
class MenuBeheer extends Component
{
    public string $newLabel = '';

    public ?int $newPageId = null;

    public string $newHref = '';

    public ?int $newParentId = null;

    public ?string $statusMessage = null;

    public function add(AuditLogger $audit): void
    {
        $this->validate([
            'newLabel' => 'nullable|string|max:100',
            'newPageId' => 'nullable|integer|exists:pages,id',
            'newHref' => 'nullable|string|max:500',
            'newParentId' => 'nullable|integer|exists:nav_items,id',
        ]);

        if ($this->newPageId === null && trim($this->newHref) === '') {
            $this->addError('newHref', 'Kies een CMS-pagina of vul een URL in.');

            return;
        }

        if ($this->newPageId === null && trim($this->newLabel) === '') {
            $this->addError('newLabel', 'Bij een vrije URL is een label verplicht.');

            return;
        }

        DB::transaction(function () use ($audit) {
            $sort = (NavItem::query()
                ->where('menu', 'main')
                ->where('parent_id', $this->newParentId)
                ->max('sort_order') ?? 0) + 10;

            $item = NavItem::create([
                'menu' => 'main',
                'parent_id' => $this->newParentId,
                'page_id' => $this->newPageId,
                'label' => trim($this->newLabel) !== '' ? trim($this->newLabel) : null,
                'href' => trim($this->newHref) !== '' ? trim($this->newHref) : null,
                'sort_order' => $sort,
                'visible' => true,
            ]);

            $audit->log('menu.item_added', $item, after: $this->snapshot($item));
        });

        $this->reset(['newLabel', 'newPageId', 'newHref', 'newParentId']);
        $this->statusMessage = 'Menu-item toegevoegd.';
    }

    public function toggleVisible(int $itemId, AuditLogger $audit): void
    {
        $item = NavItem::query()->findOrFail($itemId);
        $before = $this->snapshot($item);
        $item->visible = ! $item->visible;
        $item->save();
        $audit->log('menu.item_updated', $item, before: $before, after: $this->snapshot($item));
    }

    public function moveUp(int $itemId, AuditLogger $audit): void
    {
        $this->swapWithSibling($itemId, direction: -1, audit: $audit);
    }

    public function moveDown(int $itemId, AuditLogger $audit): void
    {
        $this->swapWithSibling($itemId, direction: 1, audit: $audit);
    }

    public function delete(int $itemId, AuditLogger $audit): void
    {
        $item = NavItem::query()->findOrFail($itemId);
        $before = $this->snapshot($item);
        DB::transaction(function () use ($item, $before, $audit) {
            $audit->log('menu.item_deleted', $item, before: $before);
            $item->delete();
        });
        $this->statusMessage = 'Menu-item verwijderd.';
    }

    private function swapWithSibling(int $itemId, int $direction, AuditLogger $audit): void
    {
        $item = NavItem::query()->findOrFail($itemId);
        $siblings = NavItem::query()
            ->where('menu', $item->menu)
            ->where('parent_id', $item->parent_id)
            ->orderBy('sort_order')
            ->get()
            ->values();

        $index = $siblings->search(fn (NavItem $x) => $x->id === $item->id);
        $swapIndex = $index + $direction;
        if ($swapIndex < 0 || $swapIndex >= $siblings->count()) {
            return;
        }

        $other = $siblings->get($swapIndex);
        DB::transaction(function () use ($item, $other, $audit) {
            $before = $this->snapshot($item);
            [$item->sort_order, $other->sort_order] = [$other->sort_order, $item->sort_order];
            $item->save();
            $other->save();
            $audit->log('menu.item_updated', $item, before: $before, after: $this->snapshot($item));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(NavItem $item): array
    {
        return [
            'menu' => $item->menu,
            'page_id' => $item->page_id,
            'parent_id' => $item->parent_id,
            'label' => $item->label,
            'href' => $item->href,
            'sort_order' => $item->sort_order,
            'visible' => $item->visible,
        ];
    }

    public function render(): View
    {
        $items = NavItem::query()
            ->where('menu', 'main')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with([
                'page',
                'children' => fn ($q) => $q->orderBy('sort_order')->with('page'),
            ])
            ->get();

        $pages = Page::query()->orderBy('title')->get(['id', 'title', 'slug', 'parent_id', 'type']);

        return view('livewire.admin.menu-beheer', [
            'items' => $items,
            'pages' => $pages,
        ]);
    }
}
