<?php

namespace Database\Seeders;

use App\Enums\BandBackground;
use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVisibility;
use App\Models\Band;
use App\Models\Block;
use App\Models\PageVersion;

/**
 * Gedeelde opbouw voor seeders die een pagina in banden en blokken zaaien
 * (homepage, lokale sportpagina's). Een band is [achtergrond, [kolom =>
 * [[bloktype, inhoud], …]]]; het aantal kolommen bepaalt de bandlayout en
 * de inhoud wordt aangevuld met de standaardinhoud van het bloktype.
 */
trait BuildsPageBands
{
    /**
     * @param  list<array{0: BandBackground, 1: list<list<array{0: BlockType, 1: array<string, mixed>}>>}>  $bands
     */
    protected function createBands(PageVersion $version, array $bands): void
    {
        foreach ($bands as $sortOrder => [$background, $columns]) {
            $band = Band::query()->create([
                'page_version_id' => $version->id,
                'zone' => 'hoofd',
                'layout' => BandLayout::from(count($columns)),
                'background' => $background,
                'sort_order' => $sortOrder,
            ]);

            foreach ($columns as $columnIndex => $blocks) {
                foreach ($blocks as $blockOrder => [$type, $content]) {
                    Block::query()->create([
                        'band_id' => $band->id,
                        'column_index' => $columnIndex,
                        'sort_order' => $blockOrder,
                        'type' => $type,
                        'content' => array_merge($type->defaultContent(), $content),
                        'visibility' => PageVisibility::Public,
                    ]);
                }
            }
        }
    }
}
