<?php

namespace App\Http\Controllers;

use App\Enums\PageVisibility;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PublicPageController extends Controller
{
    public function __invoke(Request $request, ?string $path = null): View
    {
        $segments = collect(explode('/', trim($path ?? '', '/')))
            ->filter(fn ($s) => $s !== '')
            ->values();

        $page = $this->resolvePage($segments->all());

        if ($page === null && $segments->isEmpty()) {
            return view('welcome');
        }

        abort_unless($page !== null, 404);

        $this->guardVisibility($page, $request);

        $version = $page->publishedVersion;
        abort_unless($version !== null, 404);

        $version->load(['bands.blocks']);

        return view('public.page', [
            'page' => $page,
            'version' => $version,
        ]);
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function resolvePage(array $segments): ?Page
    {
        if ($segments === []) {
            return Page::query()
                ->whereNull('parent_id')
                ->where('slug', 'home')
                ->first();
        }

        $parentId = null;
        $current = null;

        foreach ($segments as $slug) {
            $current = Page::query()
                ->where('slug', $slug)
                ->where('parent_id', $parentId)
                ->first();

            if ($current === null) {
                return null;
            }

            $parentId = $current->id;
        }

        return $current;
    }

    private function guardVisibility(Page $page, Request $request): void
    {
        if ($page->visibility === PageVisibility::Public) {
            return;
        }

        $user = $request->user();
        abort_unless($user !== null, 403, 'Deze pagina is alleen voor ingelogde leden.');

        if ($page->visibility === PageVisibility::Restricted) {
            abort_unless($user->can('pages.view'), 403);
        }
    }
}
