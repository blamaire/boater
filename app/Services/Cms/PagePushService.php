<?php

namespace App\Services\Cms;

use App\Models\Environment;
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
 *     'page' => ['slug','title','type','visibility','parent_slug'],
 *     'version' => [
 *       'bands' => [
 *         ['zone','layout','sort_order','blocks' => [ ['type','column_index','sort_order','content','visibility'] ]],
 *       ],
 *     ],
 *   ]
 */
class PagePushService
{
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
        ];

        try {
            $response = Http::withToken($environment->api_token)
                ->acceptJson()
                ->timeout(30)
                ->post($environment->baseUrl().'/api/pages/import', $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Kan omgeving [{$environment->name}] niet bereiken: {$e->getMessage()}");
        }

        return $this->parseResponse($response, $environment);
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
