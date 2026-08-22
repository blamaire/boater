<?php

namespace App\View\Composers;

use App\Models\NavItem;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicNavComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        $manual = $this->manualItems($user);
        if ($manual->isNotEmpty()) {
            $view->with('publicNav', $manual);
            $view->with('publicNavSource', 'manual');

            return;
        }

        $view->with('publicNav', $this->autoFallback($user));
        $view->with('publicNavSource', 'auto');
    }

    /**
     * Handmatig door de beheerder samengestelde menu-items. Een item zonder
     * gekoppelde pagina (vrije URL via `href`) heeft geen zichtbaarheidsniveau
     * en blijft altijd staan; een item mét pagina volgt `Page::isVisibleTo()`
     * — zo komen Beperkt-pagina's gewoon tussen de Publieke items te staan
     * voor wie ze mag bekijken, zonder apart kopje.
     *
     * @return Collection<int, NavItem>
     */
    private function manualItems(?User $user): Collection
    {
        return NavItem::query()
            ->where('menu', 'main')
            ->whereNull('parent_id')
            ->where('visible', true)
            ->orderBy('sort_order')
            ->with([
                'page',
                'children' => fn ($q) => $q->where('visible', true)->orderBy('sort_order')->with('page'),
            ])
            ->get()
            ->filter(fn (NavItem $item): bool => $item->page === null || $item->page->isVisibleTo($user))
            ->values()
            ->each(function (NavItem $item) use ($user): void {
                $item->setRelation('children', $item->children
                    ->filter(fn (NavItem $child): bool => $child->page === null || $child->page->isVisibleTo($user))
                    ->values());
            });
    }

    /**
     * Auto-fallback: root-CMS-pagina's als menu, exclusief home-pagina.
     * Kinderen volgen onafhankelijk dezelfde zichtbaarheidsregel als hun ouder.
     *
     * @return Collection<int, Page>
     */
    private function autoFallback(?User $user): Collection
    {
        return Page::query()
            ->whereNull('parent_id')
            ->whereNotNull('published_version_id')
            ->where('slug', '!=', 'home')
            ->orderBy('title')
            ->with([
                'children' => fn ($q) => $q
                    ->whereNotNull('published_version_id')
                    ->orderBy('title'),
            ])
            ->get()
            ->filter(fn (Page $page): bool => $page->isVisibleTo($user))
            ->values()
            ->each(function (Page $page) use ($user): void {
                $page->setRelation('children', $page->children
                    ->filter(fn (Page $child): bool => $child->isVisibleTo($user))
                    ->values());
            });
    }
}
