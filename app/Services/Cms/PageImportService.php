<?php

namespace App\Services\Cms;

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Band;
use App\Models\Block;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Neemt een payload van {@see PagePushService} en verwerkt die tot een nieuwe
 * conceptversie op de doel-omgeving. Bij bestaande slug wordt een conceptversie
 * onder de bestaande pagina aangemaakt; anders wordt de pagina eerst aangemaakt.
 */
class PageImportService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, page_id: int, page_version_id: int, created: bool}
     */
    public function import(array $payload): array
    {
        $pageData = $payload['page'] ?? null;
        $versionData = $payload['version'] ?? null;

        if (! is_array($pageData) || ! is_array($versionData)) {
            throw new RuntimeException('Payload mist page of version sectie.');
        }

        $slug = (string) ($pageData['slug'] ?? '');
        $title = (string) ($pageData['title'] ?? '');
        $type = PageType::from((string) $pageData['type']);
        $visibility = $this->parseVisibility((string) $pageData['visibility']);
        $parentSlug = $pageData['parent_slug'] ?? null;
        $templateName = (string) ($pageData['template_name'] ?? '');

        if ($slug === '' || $title === '' || $templateName === '') {
            throw new RuntimeException('Payload mist verplichte pagina-velden (slug, title, template_name).');
        }

        $template = Template::query()->where('name', $templateName)->first();
        if ($template === null) {
            throw new RuntimeException("Doel-omgeving kent geen template met naam [{$templateName}].");
        }

        $parentId = null;
        if (is_string($parentSlug) && $parentSlug !== '') {
            $parent = Page::query()->where('slug', $parentSlug)->first();
            if ($parent === null) {
                throw new RuntimeException("Ouder-pagina met slug [{$parentSlug}] bestaat niet op doel-omgeving.");
            }
            $parentId = $parent->id;
        }

        // Bouw een vertaal-tabel van source-asset-IDs naar lokale IDs, op
        // basis van de UUIDs in `media_uuid_map`. Assets die op deze omgeving
        // ontbreken worden overgeslagen (payload verwijst dan naar 'null'),
        // maar normaliter heeft de source ze eerst via /api/media/upload
        // gepusht zodat elke UUID matcht.
        $sourceIdToLocalId = [];
        $rawMap = $payload['media_uuid_map'] ?? [];
        if (is_array($rawMap) && $rawMap !== []) {
            $localAssetsByUuid = MediaAsset::query()
                ->whereIn('uuid', array_values($rawMap))
                ->pluck('id', 'uuid');
            foreach ($rawMap as $sourceId => $uuid) {
                $localId = $localAssetsByUuid->get((string) $uuid);
                if ($localId !== null) {
                    $sourceIdToLocalId[(int) $sourceId] = (int) $localId;
                }
            }
        }

        return DB::transaction(function () use ($slug, $title, $type, $visibility, $parentId, $template, $versionData, $sourceIdToLocalId) {
            $page = Page::query()->where('slug', $slug)->where('parent_id', $parentId)->first();
            $created = false;

            if ($page === null) {
                $page = Page::query()->create([
                    'slug' => $slug,
                    'title' => $title,
                    'type' => $type,
                    'visibility' => $visibility,
                    'parent_id' => $parentId,
                    'template_id' => $template->id,
                ]);
                $created = true;
            }

            $nextVersionNo = (int) ($page->versions()->max('version_no') ?? 0) + 1;

            $version = PageVersion::query()->create([
                'page_id' => $page->id,
                'version_no' => $nextVersionNo,
                'status' => PageVersionStatus::Draft,
                'base_version_id' => $page->published_version_id,
                'created_by_person_id' => null,
            ]);

            $this->hydrateBands($version, $versionData['bands'] ?? [], $sourceIdToLocalId);

            return [
                'status' => 'ok',
                'page_id' => $page->id,
                'page_version_id' => $version->id,
                'created' => $created,
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     * @param  array<int, int>  $sourceIdToLocalId
     */
    private function hydrateBands(PageVersion $version, array $bands, array $sourceIdToLocalId): void
    {
        foreach ($bands as $bandData) {
            $band = Band::query()->create([
                'page_version_id' => $version->id,
                'origin_band_id' => null,
                'zone' => (string) ($bandData['zone'] ?? ''),
                'layout' => BandLayout::from((string) $bandData['layout']),
                'sort_order' => (int) ($bandData['sort_order'] ?? 0),
            ]);

            $blocks = $bandData['blocks'] ?? [];
            if (! is_array($blocks)) {
                continue;
            }

            foreach ($blocks as $blockData) {
                Block::query()->create([
                    'band_id' => $band->id,
                    'origin_block_id' => null,
                    'column_index' => (int) ($blockData['column_index'] ?? 0),
                    'sort_order' => (int) ($blockData['sort_order'] ?? 0),
                    'type' => BlockType::from((string) $blockData['type']),
                    'content' => $this->rewriteMediaIds((array) ($blockData['content'] ?? []), $sourceIdToLocalId),
                    'visibility' => $this->parseVisibility((string) $blockData['visibility']),
                ]);
            }
        }
    }

    /**
     * Vervangt in de block-content alle keys eindigend op `media_asset_id`
     * met de lokale ID die bij de UUID hoort. Ontbrekende assets → null.
     *
     * @param  array<string, mixed>  $content
     * @param  array<int, int>  $sourceIdToLocalId
     * @return array<string, mixed>
     */
    /**
     * Oude exports kennen nog de waarde `leden`; die is samengevoegd met
     * `beperkt` (zie 2026_07_11_120000_migrate_leden_visibility_to_beperkt).
     */
    private function parseVisibility(string $raw): PageVisibility
    {
        if ($raw === 'leden') {
            return PageVisibility::Restricted;
        }

        return PageVisibility::from($raw);
    }

    private function rewriteMediaIds(array $content, array $sourceIdToLocalId): array
    {
        foreach ($content as $key => $value) {
            if (! str_ends_with((string) $key, 'media_asset_id') || $value === null || $value === '') {
                continue;
            }
            $sourceId = (int) $value;
            $content[$key] = $sourceIdToLocalId[$sourceId] ?? null;
        }

        return $content;
    }
}
