<?php

namespace Database\Seeders;

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Band;
use App\Models\Block;
use App\Models\NavItem;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Zaait voor lokale ontwikkeling het publieke hoofdmenu uit het homepage-
 * ontwerp (Roeien · Zeilen · Suppen · Jeugd · Over de vereniging), elk met
 * een gepubliceerde inhoudspagina (Roeien, Zeilen en Suppen met wervende
 * inhoud uit {@see LocalSportPagesSeeder}, de rest eenvoudig), zodat de links op de homepage
 * en in header/footer ergens heen gaan. Alleen bij `APP_ENV=local`: op test
 * en productie richt een redacteur het menu zelf in.
 */
class LocalPublicMenuSeeder extends Seeder
{
    /** @var list<array{slug: string, title: string, intro: string}> */
    private const PAGES = [
        ['slug' => 'roeien', 'title' => 'Roeien', 'intro' => 'Alles over roeien bij RZVG: recreatief, wedstrijd, roeivalidatie en de Basis Roei Opleiding.'],
        ['slug' => 'zeilen', 'title' => 'Zeilen', 'intro' => 'Alles over zeilen bij RZVG: zwaardboten, kielboten en de zeilopleidingen.'],
        ['slug' => 'suppen', 'title' => 'Suppen', 'intro' => 'Alles over suppen bij RZVG.'],
        ['slug' => 'jeugd', 'title' => 'Jeugd', 'intro' => 'Jeugdroeien en jeugdzeilen: informatie voor kinderen, jongeren en hun ouders.'],
        ['slug' => 'over-de-vereniging', 'title' => 'Over de vereniging', 'intro' => 'Bestuur, commissies, sociëteit, statuten en reglementen.'],
    ];

    public function run(): void
    {
        if (app()->environment() !== 'local') {
            return;
        }

        // Eerst de wervende inhoud van Roeien, Zeilen en Suppen; die pagina's
        // hebben daarna een gepubliceerde versie en krijgen hieronder dus geen
        // eenvoudige kop-plus-introversie meer.
        $this->call(LocalSportPagesSeeder::class);

        if (NavItem::query()->where('menu', 'main')->exists()) {
            return;
        }

        $template = Template::query()->orderBy('id')->first();
        if ($template === null) {
            return;
        }

        DB::transaction(function () use ($template): void {
            foreach (self::PAGES as $sortOrder => $data) {
                $page = Page::query()->firstOrCreate(
                    ['slug' => $data['slug'], 'parent_id' => null, 'type' => PageType::Content->value],
                    ['title' => $data['title'], 'visibility' => PageVisibility::Public, 'template_id' => $template->id],
                );

                if ($page->published_version_id === null) {
                    $this->publishSimpleVersion($page, $data['title'], $data['intro']);
                }

                NavItem::query()->create([
                    'menu' => 'main',
                    'page_id' => $page->id,
                    'parent_id' => null,
                    'label' => null,
                    'href' => null,
                    'sort_order' => $sortOrder,
                    'visible' => true,
                ]);
            }
        });
    }

    private function publishSimpleVersion(Page $page, string $title, string $intro): void
    {
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
            'content' => ['level' => 1, 'text' => $title],
            'visibility' => PageVisibility::Public,
        ]);

        Block::query()->create([
            'band_id' => $band->id,
            'column_index' => 0,
            'sort_order' => 1,
            'type' => BlockType::Text,
            'content' => ['html' => '<p>'.e($intro).'</p>'],
            'visibility' => PageVisibility::Public,
        ]);

        $page->update(['published_version_id' => $version->id]);
    }
}
