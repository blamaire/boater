<?php

namespace App\Services\Cms;

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Enums\WordpressImportStatus;
use App\Models\Band;
use App\Models\Block;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Models\WordpressImportItem;
use App\Models\WordpressImportMediaItem;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Neemt een gestaged WordPress-import-item (§25) over als een echte
 * CMS-pagina — als concept, zodat een redacteur de inhoud eerst controleert
 * en zelf publiceert. De volledige `content:encoded`-HTML gaat in één
 * Tekst-blok; `BlockObserver`/`BlockContentSanitizer` saneren die HTML al
 * bij het opslaan van het blok. Geselecteerde bijlagen worden vóór het
 * aanmaken van de pagina gedownload en als `MediaAsset` opgeslagen; hun URL
 * wordt in de HTML herschreven naar de nieuwe media-URL. Bijlagen die niet
 * overgenomen zijn (afgewezen, nooit besloten, of een mislukte download)
 * worden vervangen door een leesbare melding i.p.v. een dode link naar de
 * oude site — zie {@see missingMediaNotice()}.
 */
class WordpressImportService
{
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
    ) {}

    public function takeOver(WordpressImportItem $item, AuditLogger $audit, PageVisibility $visibility, ?int $parentId = null): Page
    {
        if ($item->status !== WordpressImportStatus::New) {
            throw new RuntimeException('Dit item is al overgenomen of gearchiveerd en kan niet nogmaals overgenomen worden.');
        }

        $contentHtml = $item->content_html;

        // Project-breed gezocht, niet beperkt tot bijlagen die WordPress via
        // wp:post_parent aan dit item koppelde: dezelfde afbeelding wordt in
        // de praktijk vaak op meerdere pagina's hergebruikt (zie
        // WordpressImportDetail::visibleMediaItems(), die dezelfde matching
        // gebruikt om te bepalen wat er te kiezen valt).
        $referencedMedia = WordpressImportMediaItem::query()
            ->get()
            ->filter(fn (WordpressImportMediaItem $m): bool => $m->isReferencedIn($contentHtml));

        foreach ($referencedMedia as $mediaItem) {
            if ($mediaItem->selected === true && $mediaItem->media_asset_id === null) {
                try {
                    $asset = $this->downloadAndStore($mediaItem);
                    $mediaItem->update(['media_asset_id' => $asset->id, 'download_error' => null]);
                } catch (\Throwable $e) {
                    $mediaItem->update(['download_error' => $e->getMessage()]);
                }
            }
        }

        // Elke <img> die daadwerkelijk als MediaAsset is overgenomen (nu of
        // eerder, via een andere pagina die 'm ook gebruikt) krijgt de nieuwe
        // URL; wat niet overgenomen is (bewust afgewezen, nooit besloten, of
        // een mislukte download) wordt vervangen door een leesbare melding
        // i.p.v. een dode link naar de oude site — zodat een redacteur het in
        // de pagina-editor kan terugvinden en verwijderen of vervangen.
        $contentHtml = WordpressImportMediaItem::replaceMatchingImages($contentHtml, $referencedMedia, function (WordpressImportMediaItem $mediaItem, string $imgTag): string {
            if ($mediaItem->media_asset_id !== null) {
                $newSrc = 'src="'.e($mediaItem->mediaAsset->displayUrl()).'"';

                // preg_replace_callback i.p.v. preg_replace: de vervanging kan
                // niet per ongeluk als backreference ($1, \1, ...) uitgelegd
                // worden, ongeacht wat displayUrl() teruggeeft.
                return preg_replace_callback('/\bsrc=["\'][^"\']+["\']/', fn (): string => $newSrc, $imgTag, 1) ?? $imgTag;
            }

            return $this->missingMediaNotice($mediaItem);
        });

        return DB::transaction(function () use ($item, $audit, $contentHtml, $visibility, $parentId) {
            $template = Template::query()->where('name', 'Standaard')->firstOrFail();

            $page = Page::query()->create([
                'slug' => $this->uniqueSlug($item, $parentId),
                'title' => $item->title,
                'type' => PageType::Content,
                'visibility' => $visibility,
                'parent_id' => $parentId,
                'template_id' => $template->id,
            ]);

            $version = PageVersion::query()->create([
                'page_id' => $page->id,
                'version_no' => 1,
                'status' => PageVersionStatus::Draft,
            ]);

            $band = Band::query()->create([
                'page_version_id' => $version->id,
                'zone' => 'hoofd',
                'layout' => BandLayout::OneColumn,
                'sort_order' => 0,
            ]);

            Block::query()->create([
                'band_id' => $band->id,
                'column_index' => 0,
                'sort_order' => 0,
                'type' => BlockType::Heading,
                'content' => ['level' => 1, 'text' => $item->title],
                'visibility' => $visibility,
            ]);

            Block::query()->create([
                'band_id' => $band->id,
                'column_index' => 0,
                'sort_order' => 1,
                'type' => BlockType::Text,
                'content' => ['html' => $contentHtml],
                'visibility' => $visibility,
            ]);

            $item->update([
                'status' => WordpressImportStatus::Imported,
                'page_id' => $page->id,
            ]);

            $audit->log('wordpress_import.taken_over', $page, after: [
                'wordpress_import_item_id' => $item->id,
                'wordpress_id' => $item->wordpress_id,
                'title' => $item->title,
                'visibility' => $visibility->value,
                'parent_id' => $parentId,
            ]);

            return $page;
        });
    }

    /**
     * Leesbare vervanging voor een `<img>` die niet als `MediaAsset`
     * overgenomen is, zodat een redacteur in de pagina-editor ziet dát er
     * iets ontbrak en zelf kan besluiten de tekst te verwijderen of te
     * vervangen door eigen content — in plaats van een stille, dode link naar
     * de oude WordPress-site.
     */
    private function missingMediaNotice(WordpressImportMediaItem $mediaItem): string
    {
        $notice = 'Media niet overgenomen: "'.$mediaItem->title.'".';

        if ($mediaItem->download_error !== null) {
            $notice .= ' Download mislukt: '.$mediaItem->download_error;
        }

        $notice .= ' Verwijder deze tekst of vervang ze door eigen content.';

        return '<strong>['.e($notice).']</strong>';
    }

    private function downloadAndStore(WordpressImportMediaItem $mediaItem): MediaAsset
    {
        try {
            $response = Http::timeout(30)->get($mediaItem->url);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Kan {$mediaItem->url} niet bereiken: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            throw new RuntimeException("Download van {$mediaItem->url} mislukt (HTTP {$response->status()}).");
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'wpmedia_');
        file_put_contents($tmpPath, $response->body());

        try {
            $originalName = basename((string) parse_url($mediaItem->url, PHP_URL_PATH)) ?: $mediaItem->title;
            $uploadedFile = new UploadedFile($tmpPath, $originalName, $mediaItem->mime_type, null, true);

            return $this->mediaUploadService->store($uploadedFile, alt: $mediaItem->title);
        } finally {
            @unlink($tmpPath);
        }
    }

    private function uniqueSlug(WordpressImportItem $item, ?int $parentId): string
    {
        $base = Str::slug($item->slug) ?: Str::slug($item->title) ?: 'pagina';
        $slug = $base;
        $suffix = 2;

        while (Page::query()->where('parent_id', $parentId)->where('slug', $slug)->where('type', PageType::Content)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
