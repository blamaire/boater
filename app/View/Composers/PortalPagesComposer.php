<?php

namespace App\View\Composers;

use App\Enums\PageType;
use App\Enums\PageVisibility;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Vult `$portalPages` — de root-`beperkt`-CMS-pagina's die in de portaalbalk
 * verschijnen zodra de huidige gebruiker er toegang toe zou hebben
 * (ingelogd + actief lid, of een inzage-rol). Zonder access blijft de
 * collectie leeg en wordt de balk niet gerenderd.
 */
class PortalPagesComposer
{
    public function compose(View $view): void
    {
        $view->with('portalPages', $this->pagesFor());
    }

    /**
     * @return Collection<int, Page>
     */
    private function pagesFor(): Collection
    {
        $user = auth()->user();
        if ($user === null) {
            return collect();
        }

        $mayView = $user->can('pages.view')
            || ($user->person?->hasActiveMembership() ?? false);

        if (! $mayView) {
            return collect();
        }

        return Page::query()
            ->whereNull('parent_id')
            ->where('visibility', PageVisibility::Restricted->value)
            ->where('type', PageType::Content->value)
            ->whereNotNull('published_version_id')
            ->orderBy('title')
            ->with([
                'children' => fn ($q) => $q
                    ->where('visibility', PageVisibility::Restricted->value)
                    ->where('type', PageType::Content->value)
                    ->whereNotNull('published_version_id')
                    ->orderBy('title'),
            ])
            ->get();
    }
}
