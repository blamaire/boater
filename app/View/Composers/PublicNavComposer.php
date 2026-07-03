<?php

namespace App\View\Composers;

use App\Enums\PageVisibility;
use App\Models\NavItem;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicNavComposer
{
    public function compose(View $view): void
    {
        $manual = $this->manualItems();
        if ($manual->isNotEmpty()) {
            $view->with('publicNav', $manual);
            $view->with('publicNavSource', 'manual');

            return;
        }

        $view->with('publicNav', $this->autoFallback());
        $view->with('publicNavSource', 'auto');
    }

    /**
     * Handmatig door de beheerder samengestelde menu-items.
     *
     * @return Collection<int, NavItem>
     */
    private function manualItems(): Collection
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
            ->get();
    }

    /**
     * Auto-fallback: root-CMS-pagina's als menu, exclusief home-pagina.
     *
     * @return Collection<int, Page>
     */
    private function autoFallback(): Collection
    {
        return Page::query()
            ->whereNull('parent_id')
            ->where('visibility', PageVisibility::Public->value)
            ->whereNotNull('published_version_id')
            ->where('slug', '!=', 'home')
            ->orderBy('title')
            ->with([
                'children' => fn ($q) => $q
                    ->where('visibility', PageVisibility::Public->value)
                    ->whereNotNull('published_version_id')
                    ->orderBy('title'),
            ])
            ->get();
    }
}
