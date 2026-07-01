<?php

namespace App\View\Composers;

use App\Enums\PageVisibility;
use App\Models\Page;
use Illuminate\View\View;

class PublicNavComposer
{
    public function compose(View $view): void
    {
        $rootPages = Page::query()
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

        $view->with('publicNav', $rootPages);
    }
}
