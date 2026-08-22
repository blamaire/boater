<?php

namespace App\Services\Cms;

use App\Enums\BlockType;
use App\Enums\PageType;
use App\Models\NavItem;
use App\Models\Page;
use App\Models\PageVersion;
use Illuminate\Support\Collection;

/**
 * Vindt weespagina's: content-pagina's die vanaf het menu niet bereikbaar
 * zijn via een keten van zichtbare menu-items en links in gepubliceerde
 * blok-content (tekst-, knop-, kaart-, hero- en feature-sectie-blokken).
 * Alleen gepubliceerde content telt mee als bron van uitgaande links — een
 * bezoeker ziet nooit een conceptversie. Een pagina zonder gepubliceerde
 * versie levert dus ook geen uitgaande links; ze verschijnt zelf wel in de
 * lijst tenzij een andere, wél gepubliceerde pagina ernaar linkt.
 */
class OrphanPageFinder
{
    /** @return Collection<int, Page> */
    public function find(): Collection
    {
        $pages = Page::query()
            ->with('publishedVersion.bands.blocks')
            ->get()
            ->keyBy('id');

        /** @var array<string, int> $urlToPageId */
        $urlToPageId = $pages
            ->mapWithKeys(fn (Page $page): array => [$this->pathOf($page->publicUrl()) => $page->id])
            ->all();

        $reachable = [];
        $queue = [];

        $markReachable = function (int $pageId) use (&$reachable, &$queue): void {
            if (! isset($reachable[$pageId])) {
                $reachable[$pageId] = true;
                $queue[] = $pageId;
            }
        };

        foreach (NavItem::query()->where('visible', true)->get() as $item) {
            $pageId = $item->page_id ?? $this->resolveInternalPageId($item->href, $urlToPageId);
            if ($pageId !== null) {
                $markReachable($pageId);
            }
        }

        $home = $pages->first(fn (Page $page): bool => $page->type === PageType::System
            && $page->parent_id === null
            && $page->slug === 'home');
        if ($home !== null) {
            $markReachable($home->id);
        }

        while ($queue !== []) {
            /** @var Page $page */
            $page = $pages[array_pop($queue)];
            $published = $page->publishedVersion;

            if ($published === null) {
                continue;
            }

            foreach ($this->linkedPageIds($published, $urlToPageId) as $linkedId) {
                $markReachable($linkedId);
            }
        }

        return $pages
            ->filter(fn (Page $page): bool => $page->type === PageType::Content && ! isset($reachable[$page->id]))
            ->sortBy('title')
            ->values();
    }

    /**
     * @param  array<string, int>  $urlToPageId
     * @return list<int>
     */
    private function linkedPageIds(PageVersion $version, array $urlToPageId): array
    {
        $hrefs = [];

        foreach ($version->bands as $band) {
            foreach ($band->blocks as $block) {
                $content = $block->content;

                array_push($hrefs, ...match ($block->type) {
                    BlockType::Text => $this->hrefsFromHtml((string) ($content['html'] ?? '')),
                    BlockType::Button, BlockType::Card => [(string) ($content['href'] ?? '')],
                    BlockType::Hero => [(string) ($content['cta_href'] ?? ''), (string) ($content['cta2_href'] ?? '')],
                    BlockType::FeatureSection => [(string) ($content['cta_href'] ?? '')],
                    default => [],
                });
            }
        }

        return collect($hrefs)
            ->map(fn (string $href): ?int => $this->resolveInternalPageId($href, $urlToPageId))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function hrefsFromHtml(string $html): array
    {
        if ($html === '' || preg_match_all('/<a\b[^>]*\bhref=["\']([^"\']+)["\']/i', $html, $matches) === false) {
            return [];
        }

        return $matches[1];
    }

    /**
     * @param  array<string, int>  $urlToPageId
     */
    private function resolveInternalPageId(?string $href, array $urlToPageId): ?int
    {
        if ($href === null) {
            return null;
        }

        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        $parts = parse_url($href);
        if ($parts === false || ! isset($parts['path']) || $parts['path'] === '') {
            return null;
        }

        // Alleen bij een absolute URL (met host) hoeft die host bij het eigen
        // domein te horen — een root-relatieve link ("/pagina/...") is
        // sowieso intern. Zo matcht een link naar de oude WordPress-site
        // nooit, terwijl "/pagina/zeilen" dat wel doet.
        if (isset($parts['host'])) {
            $ownHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($ownHost === null || strcasecmp($parts['host'], $ownHost) !== 0) {
                return null;
            }
        }

        return $urlToPageId[$this->pathOf($parts['path'])] ?? null;
    }

    private function pathOf(string $path): string
    {
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }
}
