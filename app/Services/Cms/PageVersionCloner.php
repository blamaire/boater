<?php

namespace App\Services\Cms;

use App\Http\Controllers\Admin\PageEditorController;
use App\Http\Controllers\Admin\PageHistoryController;
use App\Models\PageVersion;

/**
 * Kopieert de volledige inhoud van een {@see PageVersion} (meta-/OG-velden
 * plus banden en blokken) naar een andere, lege versie. Gedeeld door het
 * aanmaken van een nieuwe conceptversie ({@see PageEditorController})
 * en het herstellen van een oudere versie vanuit de historie
 * ({@see PageHistoryController::restore()}), zodat
 * beide plekken exact dezelfde velden meenemen.
 */
class PageVersionCloner
{
    public function clone(PageVersion $source, PageVersion $target): void
    {
        $target->update([
            'meta_description' => $source->meta_description,
            'og_title' => $source->og_title,
            'og_description' => $source->og_description,
            'og_image_media_asset_id' => $source->og_image_media_asset_id,
        ]);

        foreach ($source->bands()->with('blocks')->get() as $band) {
            $newBand = $target->bands()->create([
                'origin_band_id' => $band->origin_band_id ?? $band->id,
                'zone' => $band->zone,
                'layout' => $band->layout,
                'sort_order' => $band->sort_order,
            ]);

            foreach ($band->blocks as $block) {
                $newBand->blocks()->create([
                    'origin_block_id' => $block->origin_block_id ?? $block->id,
                    'column_index' => $block->column_index,
                    'sort_order' => $block->sort_order,
                    'type' => $block->type,
                    'content' => $block->content,
                    'visibility' => $block->visibility,
                ]);
            }
        }
    }
}
