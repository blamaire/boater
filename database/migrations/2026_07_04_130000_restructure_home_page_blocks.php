<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVisibility;
use App\Models\Band;
use App\Models\Block;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Vervang de inhoud van de home-systeempagina door de wireframe-structuur:
        // hero, video, roeien, zeilen, suppen. Beheerder vult media en tekst
        // daarna via de pagina-bewerker.
        $home = Page::query()
            ->whereNull('parent_id')
            ->where('slug', 'home')
            ->where('type', PageType::System)
            ->first();

        if ($home === null) {
            return;
        }

        $versionId = $home->published_version_id;
        if ($versionId === null) {
            return;
        }

        DB::transaction(function () use ($versionId) {
            $bandIds = Band::query()->where('page_version_id', $versionId)->pluck('id');
            Block::query()->whereIn('band_id', $bandIds)->delete();
            Band::query()->whereIn('id', $bandIds)->delete();

            $sections = [
                ['type' => BlockType::Hero, 'content' => [
                    'media_asset_id' => null,
                    'title' => 'Welkom bij RZVG',
                    'subtitle' => '',
                    'cta_label' => 'Lid worden',
                    'cta_href' => '/lid-worden',
                    'cta2_label' => '',
                    'cta2_href' => '',
                ]],
                ['type' => BlockType::Video, 'content' => ['media_asset_id' => null]],
                ['type' => BlockType::FeatureSection, 'content' => [
                    'media_asset_id' => null, 'title' => 'Roeien', 'body' => '',
                    'cta_label' => 'Meer over roeien', 'cta_href' => '#', 'image_side' => 'left',
                ]],
                ['type' => BlockType::FeatureSection, 'content' => [
                    'media_asset_id' => null, 'title' => 'Zeilen', 'body' => '',
                    'cta_label' => 'Meer over zeilen', 'cta_href' => '#', 'image_side' => 'right',
                ]],
                ['type' => BlockType::FeatureSection, 'content' => [
                    'media_asset_id' => null, 'title' => 'Suppen', 'body' => '',
                    'cta_label' => 'Meer over suppen', 'cta_href' => '#', 'image_side' => 'left',
                ]],
            ];

            foreach ($sections as $index => $section) {
                $band = Band::create([
                    'page_version_id' => $versionId,
                    'zone' => 'hoofd',
                    'layout' => BandLayout::OneColumn,
                    'sort_order' => $index * 10,
                ]);

                Block::create([
                    'band_id' => $band->id,
                    'column_index' => 0,
                    'sort_order' => 0,
                    'type' => $section['type'],
                    'content' => $section['content'],
                    'visibility' => PageVisibility::Public,
                ]);
            }
        });
    }
};
