<?php

namespace App\Services\Cms;

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Band;
use App\Models\Block;
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
        $visibility = PageVisibility::from((string) $pageData['visibility']);
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

        return DB::transaction(function () use ($slug, $title, $type, $visibility, $parentId, $template, $versionData) {
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

            $this->hydrateBands($version, $versionData['bands'] ?? []);

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
     */
    private function hydrateBands(PageVersion $version, array $bands): void
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
                    'content' => (array) ($blockData['content'] ?? []),
                    'visibility' => PageVisibility::from((string) $blockData['visibility']),
                ]);
            }
        }
    }
}
