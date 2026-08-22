<?php

namespace App\Console\Commands;

use App\Enums\WordpressImportStatus;
use App\Models\WordpressImportItem;
use App\Models\WordpressImportMediaItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

/**
 * Leest een WordPress WXR-exportbestand (§25) en zet elke pagina/bericht om
 * in een `WordpressImportItem` in staging — losstaand van `pages`. Een
 * beheerder beslist daarna via het beheerscherm per item of het wordt
 * overgenomen als CMS-pagina of gearchiveerd.
 */
class ImportWordpress extends Command
{
    protected $signature = 'rzvg:import-wordpress {file : Pad naar het WXR-exportbestand (XML)}';

    protected $description = 'Importeer pagina\'s en berichten uit een WordPress WXR-exportbestand naar de importstaging.';

    private const WP_NAMESPACE = 'http://wordpress.org/export/1.2/';

    private const CONTENT_NAMESPACE = 'http://purl.org/rss/1.0/modules/content/';

    private const EXCERPT_NAMESPACE = 'http://wordpress.org/export/1.2/excerpt/';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Bestand [{$path}] bestaat niet of is niet leesbaar.");

            return self::FAILURE;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);

        if ($xml === false) {
            $this->error('Het bestand kon niet als XML worden gelezen:');
            foreach (libxml_get_errors() as $error) {
                $this->error(trim($error->message));
            }
            libxml_clear_errors();

            return self::FAILURE;
        }

        $found = 0;
        $created = 0;
        $updated = 0;
        $skippedType = 0;
        $skippedTrash = 0;
        $skippedAlreadyImported = 0;
        $mediaFound = 0;
        $mediaStaged = 0;
        $mediaSkippedOrphaned = 0;
        $mediaSkippedTrash = 0;
        $mediaDiscoveredInContent = 0;

        DB::transaction(function () use (
            $xml,
            &$found,
            &$created,
            &$updated,
            &$skippedType,
            &$skippedTrash,
            &$skippedAlreadyImported,
            &$mediaFound,
            &$mediaStaged,
            &$mediaSkippedOrphaned,
            &$mediaSkippedTrash,
            &$mediaDiscoveredInContent,
        ): void {
            $attachmentNodes = [];
            $processedItems = [];

            foreach ($xml->channel->item as $item) {
                $found++;

                $wp = $this->childrenOf($item, self::WP_NAMESPACE);
                $content = $this->childrenOf($item, self::CONTENT_NAMESPACE);
                $excerptNs = $this->childrenOf($item, self::EXCERPT_NAMESPACE);

                $postType = (string) $wp->post_type;

                if ($postType === 'attachment') {
                    $mediaFound++;
                    $attachmentNodes[] = $item;

                    continue;
                }

                if (! in_array($postType, ['page', 'post'], true)) {
                    $skippedType++;

                    continue;
                }

                $wpStatus = (string) $wp->status;
                if ($wpStatus === 'trash') {
                    $skippedTrash++;

                    continue;
                }

                $wordpressId = (int) $wp->post_id;

                $existing = WordpressImportItem::query()->where('wordpress_id', $wordpressId)->first();

                if ($existing !== null && $existing->status === WordpressImportStatus::Imported) {
                    $skippedAlreadyImported++;

                    continue;
                }

                $attributes = [
                    'title' => (string) $item->title,
                    'slug' => (string) $wp->post_name,
                    'content_html' => $this->autoParagraph((string) $content->encoded),
                    'excerpt' => $this->parseExcerpt($excerptNs),
                    'wordpress_published_at' => $this->parsePubDate((string) $item->pubDate),
                    'wordpress_parent_id' => ((int) $wp->post_parent) ?: null,
                    'raw_meta' => $this->parseRawMeta($item, $wpStatus),
                ];

                $stagedItem = WordpressImportItem::query()->updateOrCreate(
                    ['wordpress_id' => $wordpressId],
                    array_merge(['wordpress_type' => $postType], $attributes),
                );

                if ($existing === null) {
                    $created++;
                } else {
                    $updated++;
                }

                $processedItems[] = $stagedItem;
            }

            foreach ($attachmentNodes as $item) {
                $wp = $this->childrenOf($item, self::WP_NAMESPACE);

                if ((string) $wp->status === 'trash') {
                    $mediaSkippedTrash++;

                    continue;
                }

                $parentWordpressId = (int) $wp->post_parent;
                $parentItem = $parentWordpressId > 0
                    ? WordpressImportItem::query()->where('wordpress_id', $parentWordpressId)->first()
                    : null;

                $attachmentUrl = trim((string) $wp->attachment_url);

                if ($parentItem === null || $attachmentUrl === '') {
                    $mediaSkippedOrphaned++;

                    continue;
                }

                WordpressImportMediaItem::query()->updateOrCreate(
                    ['wordpress_id' => (int) $wp->post_id],
                    [
                        'wordpress_import_item_id' => $parentItem->id,
                        'title' => (string) $item->title ?: $attachmentUrl,
                        'url' => $attachmentUrl,
                        'mime_type' => (string) $wp->post_mime_type ?: null,
                    ],
                );

                $mediaStaged++;
            }

            // Fase 3: afbeeldingen die letterlijk in de content staan maar
            // nergens als WXR-bijlage voorkomen (ook niet gekoppeld aan een
            // ánder item — WordPress-pagina's hergebruiken elkaars uploads),
            // alsnog als losse bijlage vastleggen zodat ze op de detailpagina
            // te kiezen zijn. wordpress_id blijft leeg: er is geen WXR-herkomst.
            $knownBaseFilenames = WordpressImportMediaItem::query()->pluck('url')
                ->map(fn (string $url): string => WordpressImportMediaItem::normalizedBaseFilename($url))
                ->filter(fn (string $base): bool => $base !== '')
                ->flip()
                ->all();

            foreach ($processedItems as $stagedItem) {
                preg_match_all('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $stagedItem->content_html, $imgMatches);

                foreach ($imgMatches[1] as $src) {
                    $src = trim($src);
                    $base = WordpressImportMediaItem::normalizedBaseFilename($src);

                    if ($base === '' || isset($knownBaseFilenames[$base])) {
                        continue;
                    }

                    WordpressImportMediaItem::query()->create([
                        'wordpress_import_item_id' => $stagedItem->id,
                        'wordpress_id' => null,
                        'title' => basename(parse_url($src, PHP_URL_PATH) ?: '') ?: $src,
                        'url' => $src,
                        'mime_type' => $this->guessMimeFromExtension($src),
                    ]);

                    $knownBaseFilenames[$base] = true;
                    $mediaDiscoveredInContent++;
                }
            }
        });

        $this->info("Gevonden items in export: {$found}.");
        $this->info("Aangemaakt: {$created}.");
        $this->info("Bijgewerkt: {$updated}.");
        $this->info("Overgeslagen — ander type (geen pagina/bericht): {$skippedType}.");
        $this->info("Overgeslagen — in de prullenbak: {$skippedTrash}.");
        $this->info("Overgeslagen — al overgenomen: {$skippedAlreadyImported}.");
        $this->info("Bijlagen gevonden in export: {$mediaFound}.");
        $this->info("Bijlagen gekoppeld aan een gestaged item: {$mediaStaged}.");
        $this->info("Bijlagen overgeslagen — geen gekoppelde pagina/bericht: {$mediaSkippedOrphaned}.");
        $this->info("Bijlagen overgeslagen — in de prullenbak: {$mediaSkippedTrash}.");
        $this->info("Bijlagen ontdekt via de content zelf (geen WXR-herkomst): {$mediaDiscoveredInContent}.");
        $this->warn('Let op: alleen bijlagen die je bij het overnemen aanvinkt worden gedownload; overige afbeeldingen in de HTML blijven naar de oude WordPress-site verwijzen.');

        return self::SUCCESS;
    }

    private function childrenOf(SimpleXMLElement $node, string $namespace): SimpleXMLElement
    {
        return $node->children($namespace) ?? new SimpleXMLElement('<leeg/>');
    }

    /**
     * WordPress' klassieke editor slaat content op als platte tekst met
     * blanco regels tussen alinea's, zonder `<p>`-tags — die worden pas bij
     * weergave op de oude site toegevoegd door WordPress' eigen `wpautop()`.
     * Zonder die stap smelt de hele tekst tot één blok samen (browsers
     * negeren losse newlines). Elk door blanco regels gescheiden stuk wordt
     * apart beoordeeld: begint het al met blokniveau-HTML (bv. een kop die
     * de auteur zelf met de HTML-editor invoegde), dan blijft dát specifieke
     * stuk ongemoeid — de rest van de content kan alsnog platte tekst zijn
     * die wél inpakken nodig heeft (zoals een los kopje boven een verder
     * onopgemaakte tekst). Zo'n kop staat vaak zónder blanco regel ertussen
     * direct boven of onder platte tekst; een blokniveau-tag vormt daarom
     * ook zonder omringende blanco regel al een eigen alinea-grens, anders
     * wordt de tekst die er direct op volgt/aan voorafgaat ten onrechte als
     * "al opgemaakt" gezien en dus niet ingepakt.
     */
    private function autoParagraph(string $html): string
    {
        $html = trim(str_replace(["\r\n", "\r"], "\n", $html));

        if ($html === '') {
            return $html;
        }

        $blockTags = 'p|div|table|blockquote|ul|ol|h[1-6]|figure|section|article|pre';
        $html = preg_replace('/(<\/(?:'.$blockTags.')>)\n(?!\n)/i', "$1\n\n", $html) ?? $html;
        $html = preg_replace('/(?<!\n)\n(<(?:'.$blockTags.')\b)/i', "\n\n$1", $html) ?? $html;

        return collect(preg_split('/\n{2,}/', $html))
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter(fn (string $paragraph): bool => $paragraph !== '')
            ->map(fn (string $paragraph): string => preg_match('/^<(?:'.$blockTags.')[\s>]/i', $paragraph) === 1
                ? $paragraph
                : '<p>'.nl2br($paragraph).'</p>')
            ->implode("\n");
    }

    private function guessMimeFromExtension(string $url): ?string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => null,
        };
    }

    private function parseExcerpt(SimpleXMLElement $excerptNs): ?string
    {
        $value = (string) $excerptNs->encoded;

        return $value !== '' ? $value : null;
    }

    private function parsePubDate(string $raw): ?Carbon
    {
        if (trim($raw) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }

        if ($date->year < 1971) {
            return null;
        }

        return $date;
    }

    /**
     * @return array{wp_status: string, categories: array<int, string>, tags: array<int, string>}
     */
    private function parseRawMeta(SimpleXMLElement $item, string $wpStatus): array
    {
        $categories = [];
        $tags = [];

        foreach ($item->category as $category) {
            $domain = (string) $category['domain'];
            $value = (string) $category;

            if ($domain === 'category') {
                $categories[] = $value;
            } elseif ($domain === 'post_tag') {
                $tags[] = $value;
            }
        }

        return [
            'wp_status' => $wpStatus,
            'categories' => $categories,
            'tags' => $tags,
        ];
    }
}
