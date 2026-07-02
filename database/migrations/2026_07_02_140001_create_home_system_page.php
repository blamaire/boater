<?php

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
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Bestaat er al een home-systeempagina? Dan niets doen (idempotent).
        $exists = DB::table('pages')
            ->whereNull('parent_id')
            ->where('slug', 'home')
            ->where('type', PageType::System->value)
            ->exists();

        if ($exists) {
            return;
        }

        $template = Template::query()->orderBy('id')->first();
        if ($template === null) {
            // Geen sjabloon geseed — sla over; bij een echte install
            // is TemplateSeeder al gedraaid.
            return;
        }

        DB::transaction(function () use ($template) {
            $page = Page::create([
                'slug' => 'home',
                'title' => 'Welkom',
                'type' => PageType::System,
                'visibility' => PageVisibility::Public,
                'parent_id' => null,
                'template_id' => $template->id,
            ]);

            $version = PageVersion::create([
                'page_id' => $page->id,
                'version_no' => 1,
                'status' => PageVersionStatus::Published,
            ]);

            $band = Band::create([
                'page_version_id' => $version->id,
                'zone' => 'hoofd',
                'layout' => BandLayout::OneColumn,
                'sort_order' => 0,
            ]);

            Block::create([
                'band_id' => $band->id,
                'column_index' => 0,
                'sort_order' => 0,
                'type' => BlockType::Heading,
                'content' => ['level' => 1, 'text' => 'Welkom bij RZVG'],
                'visibility' => PageVisibility::Public,
            ]);

            Block::create([
                'band_id' => $band->id,
                'column_index' => 0,
                'sort_order' => 1,
                'type' => BlockType::Text,
                'content' => [
                    'html' => '<p>De Roei- en Zeilvereniging Gouda is sinds 1911 actief op de Reeuwijkse Plassen en de Gouwe. '
                        .'Wij bieden ruimte om te roeien en te zeilen in een verzorgde vereniging met vaste momenten en losse tochten.</p>'
                        .'<p>Kom eens langs of neem contact op om te ervaren wat de vereniging voor jou kan betekenen.</p>',
                ],
                'visibility' => PageVisibility::Public,
            ]);

            Block::create([
                'band_id' => $band->id,
                'column_index' => 0,
                'sort_order' => 2,
                'type' => BlockType::Button,
                'content' => [
                    'label' => 'Lid worden',
                    'href' => '/pagina/lid-worden',
                    'style' => 'primary',
                ],
                'visibility' => PageVisibility::Public,
            ]);

            $page->update(['published_version_id' => $version->id]);
        });
    }
};
