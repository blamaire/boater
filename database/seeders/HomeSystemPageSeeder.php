<?php

namespace Database\Seeders;

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Http\Controllers\PublicPageController;
use App\Models\Band;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Zaait de home-systeempagina (§CMS: type=system, slug=home, root-level).
 * Deze pagina wordt op `/` gerenderd door {@see PublicPageController::home()}
 * en is bewust niet verwijderbaar via de admin-UI.
 *
 * Was oorspronkelijk een migratie, maar die kon stilletjes overgeslagen worden
 * als hij liep vóór `TemplateSeeder`. In een seeder is de volgorde expliciet
 * (na `TemplateSeeder`) en 100% herhaalbaar.
 */
class HomeSystemPageSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: als hij al bestaat, niets doen.
        $exists = Page::query()
            ->whereNull('parent_id')
            ->where('slug', 'home')
            ->where('type', PageType::System->value)
            ->exists();

        if ($exists) {
            return;
        }

        $template = Template::query()->orderBy('id')->first();
        if ($template === null) {
            return;
        }

        DB::transaction(function () use ($template): void {
            $page = Page::query()->create([
                'slug' => 'home',
                'title' => 'Welkom',
                'type' => PageType::System,
                'visibility' => PageVisibility::Public,
                'parent_id' => null,
                'template_id' => $template->id,
            ]);

            $version = PageVersion::query()->create([
                'page_id' => $page->id,
                'version_no' => 1,
                'status' => PageVersionStatus::Published,
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
                'type' => BlockType::Heading,
                'content' => ['level' => 1, 'text' => 'Welkom bij RZVG'],
                'visibility' => PageVisibility::Public,
            ]);

            Block::query()->create([
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

            Block::query()->create([
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
}
