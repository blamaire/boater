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
        ): void {
            $attachmentNodes = [];

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
                    'content_html' => (string) $content->encoded,
                    'excerpt' => $this->parseExcerpt($excerptNs),
                    'wordpress_published_at' => $this->parsePubDate((string) $item->pubDate),
                    'raw_meta' => $this->parseRawMeta($item, $wpStatus),
                ];

                WordpressImportItem::query()->updateOrCreate(
                    ['wordpress_id' => $wordpressId],
                    array_merge(['wordpress_type' => $postType], $attributes),
                );

                if ($existing === null) {
                    $created++;
                } else {
                    $updated++;
                }
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
        $this->warn('Let op: alleen bijlagen die je bij het overnemen aanvinkt worden gedownload; overige afbeeldingen in de HTML blijven naar de oude WordPress-site verwijzen.');

        return self::SUCCESS;
    }

    private function childrenOf(SimpleXMLElement $node, string $namespace): SimpleXMLElement
    {
        return $node->children($namespace) ?? new SimpleXMLElement('<leeg/>');
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
