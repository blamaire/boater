<?php

namespace App\Services\Cms;

use App\Models\Block;
use App\Models\PageVersion;

/**
 * Serializeert een `ConflictReport` en `PageVersion`s naar arrays die
 * we in de historie-diff blade als JSON kunnen tonen. Bestaat naast de
 * bestaande visuele weergave zodat pagina-beheerders zelf een precieze
 * gestructureerde diff kunnen inzien.
 */
class PageVersionDiffSerializer
{
    /**
     * Per blok een compacte structuur: type verschil, welke sleutels
     * botsen, en de content van v-A / v-B (base weggelaten in de
     * two-way historie-diff omdat er geen gemeenschappelijke voorouder
     * meegegeven wordt).
     *
     * @return list<array{
     *     origin_block_id: int,
     *     type: string,
     *     conflicting_keys: list<string>,
     *     a: ?array{type: string, content: array<string, mixed>|null},
     *     b: ?array{type: string, content: array<string, mixed>|null},
     * }>
     */
    public function structured(ConflictReport $report): array
    {
        return $report->entries
            ->map(fn (BlockDiff $diff): array => [
                'origin_block_id' => $diff->originBlockId,
                'type' => $diff->type,
                'conflicting_keys' => $diff->conflictingKeys,
                'a' => $this->blockPayload($diff->mine),
                'b' => $this->blockPayload($diff->theirs),
            ])
            ->all();
    }

    /**
     * De hele versie-inhoud als geordend array (banden → kolommen → blokken),
     * zodat een beheerder de rauwe JSON-vorm van een specifieke versie kan
     * inspecteren of kopiëren.
     *
     * @return array{
     *     version_no: int,
     *     status: string,
     *     bands: list<array{
     *         zone: string,
     *         layout: int,
     *         sort_order: int,
     *         blocks: list<array{origin_block_id: ?int, column_index: int, sort_order: int, type: string, content: array<string, mixed>|null}>
     *     }>
     * }
     */
    public function raw(PageVersion $version): array
    {
        $bands = $version->bands()->with('blocks')->orderBy('sort_order')->get();

        return [
            'version_no' => $version->version_no,
            'status' => $version->status->value,
            'bands' => $bands->map(fn ($band): array => [
                'zone' => (string) $band->zone,
                'layout' => (int) $band->layout->value,
                'sort_order' => (int) $band->sort_order,
                'blocks' => $band->blocks
                    ->sortBy([['column_index', 'asc'], ['sort_order', 'asc']])
                    ->values()
                    ->map(fn (Block $b): array => [
                        'origin_block_id' => $b->origin_block_id,
                        'column_index' => (int) $b->column_index,
                        'sort_order' => (int) $b->sort_order,
                        'type' => (string) $b->type->value,
                        'content' => $b->content,
                    ])
                    ->all(),
            ])->all(),
        ];
    }

    /**
     * @return ?array{type: string, content: array<string, mixed>|null}
     */
    private function blockPayload(?Block $block): ?array
    {
        if ($block === null) {
            return null;
        }

        return [
            'type' => (string) $block->type->value,
            'content' => $block->content,
        ];
    }
}
