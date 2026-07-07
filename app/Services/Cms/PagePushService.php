<?php

namespace App\Services\Cms;

use App\Models\Environment;
use App\Models\MediaAsset;
use App\Models\Page;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Push van een gepubliceerde pagina-versie naar een andere RZVG-omgeving.
 *
 * Payload structuur:
 *   [
 *     'page' => ['slug','title','type','visibility','parent_slug','template_name'],
 *     'version' => [
 *       'bands' => [
 *         ['zone','layout','sort_order','blocks' => [ ['type','column_index','sort_order','content','visibility'] ]],
 *       ],
 *     ],
 *     // Portable ↔ lokale asset-IDs: source stuurt de UUID per lokale ID mee,
 *     // target vervangt in de block-content de source-IDs door zijn eigen IDs.
 *     'media_uuid_map' => [
 *       '42' => 'uuid-...',
 *     ],
 *   ]
 */
class PagePushService
{
    public function __construct(private readonly MediaSyncService $mediaSync) {}

    /**
     * @return array<string, mixed> Antwoord van target ('created'|'updated'|'error', 'page_id' indien beschikbaar).
     */
    public function push(Page $page, Environment $environment): array
    {
        $version = $page->publishedVersion;
        if ($version === null) {
            throw new RuntimeException('Pagina heeft nog geen gepubliceerde versie om te pushen.');
        }

        if (! $environment->is_active) {
            throw new RuntimeException("Omgeving [{$environment->name}] is niet actief.");
        }

        $version->load(['bands.blocks']);
        $page->loadMissing(['template', 'parent']);

        // 1. Verzamel alle media_asset_ids die in de blocks staan.
        $localAssetIds = [];
        foreach ($version->bands as $band) {
            foreach ($band->blocks as $block) {
                foreach ($this->extractMediaAssetIds($block->content) as $id) {
                    $localAssetIds[$id] = true;
                }
            }
        }
        $localAssetIds = array_keys($localAssetIds);

        // 2. Haal UUIDs op en synchroniseer ontbrekende assets naar target.
        $uuidMap = [];
        if ($localAssetIds !== []) {
            /** @var array<int, MediaAsset> $assets */
            $assets = MediaAsset::query()->whereIn('id', $localAssetIds)->get()->keyBy('id')->all();

            foreach ($localAssetIds as $id) {
                if (! isset($assets[$id])) {
                    // Verwijzing naar een verwijderde asset — sla over.
                    continue;
                }
                $uuidMap[(string) $id] = $assets[$id]->uuid;
            }

            $uuids = array_values($uuidMap);
            $missingUuids = $this->mediaSync->probe($environment, $uuids);

            foreach ($missingUuids as $missingUuid) {
                $asset = collect($assets)->firstWhere('uuid', $missingUuid);
                if ($asset === null) {
                    continue;
                }
                $this->mediaSync->upload($environment, $asset);
            }
        }

        // 3. Bouw de payload.
        $payload = [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'type' => $page->type->value,
                'visibility' => $page->visibility->value,
                'parent_slug' => $page->parent?->slug,
                'template_name' => $page->template->name,
            ],
            'version' => [
                'bands' => $version->bands->sortBy('sort_order')->values()->map(fn ($band) => [
                    'zone' => $band->zone,
                    'layout' => $band->layout->value,
                    'sort_order' => $band->sort_order,
                    'blocks' => $band->blocks->sortBy('sort_order')->values()->map(fn ($block) => [
                        'type' => $block->type->value,
                        'column_index' => $block->column_index,
                        'sort_order' => $block->sort_order,
                        'content' => $block->content,
                        'visibility' => $block->visibility->value,
                    ])->all(),
                ])->all(),
            ],
            'media_uuid_map' => $uuidMap,
        ];

        try {
            $response = Http::withToken($environment->api_token)
                ->acceptJson()
                ->timeout(60)
                ->post($environment->baseUrl().'/api/pages/import', $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Kan omgeving [{$environment->name}] niet bereiken: {$e->getMessage()}");
        }

        return $this->parseResponse($response, $environment);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<int, int>
     */
    private function extractMediaAssetIds(array $content): array
    {
        $ids = [];
        foreach ($content as $key => $value) {
            if (str_ends_with($key, 'media_asset_id') && $value !== null && $value !== '') {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(Response $response, Environment $environment): array
    {
        if (! $response->successful()) {
            $body = $response->body();
            throw new RuntimeException(
                "Omgeving [{$environment->name}] weigerde de push (HTTP {$response->status()}): {$body}"
            );
        }

        return $response->json() ?? ['status' => 'ok'];
    }
}
