<?php

namespace App\Http\Controllers;

use App\Enums\PageType;
use App\Enums\PageVisibility;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PublicPageController extends Controller
{
    public function __invoke(Request $request, string $path): View
    {
        $segments = collect(explode('/', trim($path, '/')))
            ->filter(fn ($s) => $s !== '')
            ->values();

        $page = $this->resolvePage($segments->all());
        abort_unless($page !== null, 404);

        return $this->renderPage($page, $request);
    }

    public function home(Request $request): View
    {
        // "/" toont uitsluitend een systeempagina (type=system, slug=home).
        // Een gewone content-pagina met slug=home hoort op /pagina/home.
        $home = Page::query()
            ->whereNull('parent_id')
            ->where('slug', 'home')
            ->where('type', PageType::System->value)
            ->first();

        if ($home === null || $home->published_version_id === null) {
            return view('welcome');
        }

        return $this->renderPage($home, $request);
    }

    private function renderPage(Page $page, Request $request): View
    {
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
            return null;
        }

        // /pagina/{path} toont uitsluitend content-pagina's. Systeempagina's
        // (bv. de home op /) zijn hier niet bereikbaar en botsen dus niet.
        $parentId = null;
        $current = null;

        foreach ($segments as $slug) {
            $current = Page::query()
                ->where('slug', $slug)
                ->where('parent_id', $parentId)
                ->where('type', PageType::Content->value)
                ->first();

            if ($current === null) {
                return null;
            }

            $parentId = $current->id;
        }

        return $current;
    }

    /**
     * `beperkt` = ingelogd + actief lidmaatschap OF een inzage-rol
     * (redacteur/beheerder via `pages.view`). Oud-leden zonder actief
     * lidmaatschap krijgen geen toegang (zie §11 + §26.4).
     */
    private function guardVisibility(Page $page, Request $request): void
    {
        if ($page->visibility === PageVisibility::Public) {
            return;
        }

        $user = $request->user();
        abort_unless($user !== null, 403, 'Deze pagina is alleen voor ingelogde leden.');

        if ($user->can('pages.view')) {
            return;
        }

        abort_unless($user->person?->hasActiveMembership() === true, 403);
    }
}
