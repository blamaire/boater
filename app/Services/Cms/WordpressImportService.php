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
 * aanmaken van de pagina gedownload en als `MediaAsset` opgeslagen; hun
 * URL wordt in de HTML herschreven naar de nieuwe media-URL.
 */
class WordpressImportService
{
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
    ) {}

    public function takeOver(WordpressImportItem $item, AuditLogger $audit): Page
    {
        if ($item->status !== WordpressImportStatus::New) {
            throw new RuntimeException('Dit item is al overgenomen of gearchiveerd en kan niet nogmaals overgenomen worden.');
        }

        $contentHtml = $item->content_html;

        foreach ($item->mediaItems()->where('selected', true)->whereNull('media_asset_id')->get() as $mediaItem) {
            try {
                $asset = $this->downloadAndStore($mediaItem);
                $contentHtml = str_replace($mediaItem->url, $asset->displayUrl(), $contentHtml);
                $mediaItem->update(['media_asset_id' => $asset->id, 'download_error' => null]);
            } catch (\Throwable $e) {
                $mediaItem->update(['download_error' => $e->getMessage()]);
            }
        }

        return DB::transaction(function () use ($item, $audit, $contentHtml) {
            $template = Template::query()->where('name', 'Standaard')->firstOrFail();

            $page = Page::query()->create([
                'slug' => $this->uniqueSlug($item),
                'title' => $item->title,
                'type' => PageType::Content,
                'visibility' => PageVisibility::Public,
                'parent_id' => null,
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
                'type' => BlockType::Text,
                'content' => ['html' => $contentHtml],
                'visibility' => PageVisibility::Public,
            ]);

            $item->update([
                'status' => WordpressImportStatus::Imported,
                'page_id' => $page->id,
            ]);

            $audit->log('wordpress_import.taken_over', $page, after: [
                'wordpress_import_item_id' => $item->id,
                'wordpress_id' => $item->wordpress_id,
                'title' => $item->title,
            ]);

            return $page;
        });
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

    private function uniqueSlug(WordpressImportItem $item): string
    {
        $base = Str::slug($item->slug) ?: Str::slug($item->title) ?: 'pagina';
        $slug = $base;
        $suffix = 2;

        while (Page::query()->whereNull('parent_id')->where('slug', $slug)->where('type', PageType::Content)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
