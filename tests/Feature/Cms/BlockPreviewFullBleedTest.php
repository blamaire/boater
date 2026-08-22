<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Livewire\Admin\PageEditor;
use App\Models\Band;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Models\User;
use Livewire\Livewire;

/**
 * Full-bleed hero/video/feature-secties vullen op de publieke pagina bewust
 * edge-to-edge de breedte van <main> (h-screen i.p.v. de kleinere
 * niet-full-bleed hoogte) — dat kan alleen in een 1-koloms-band zonder de
 * gecentreerde max-w-6xl-leesbreedte eromheen (zie public/page.blade.php).
 * Diezelfde partial (cms.blocks.preview) wordt ook gebruikt in de
 * bewerker/diff/conflict-resolver, waar full-bleed de layout juist kapot zou
 * maken (een grote hero-foto werd breder dan de bewerker zelf). De partial
 * moet dus alleen full-bleed renderen als $fullBleed niet expliciet op false
 * staat.
 */
function makeHeroPage(BandLayout $layout = BandLayout::OneColumn, array $extraBlocks = []): array
{
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'hero-test',
        'title' => 'Hero test',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
    ]);
    $band = Band::create(['page_version_id' => $version->id, 'zone' => 'hoofd', 'layout' => $layout, 'sort_order' => 0]);
    Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Hero,
        'content' => ['title' => 'Welkom'],
        'visibility' => 'publiek',
    ]);
    foreach ($extraBlocks as $i => $type) {
        Block::create([
            'band_id' => $band->id,
            'column_index' => 0,
            'sort_order' => $i + 1,
            'type' => $type,
            'content' => $type === BlockType::Text ? ['html' => '<p>Bijschrift</p>'] : ['level' => 2, 'text' => 'Kop'],
            'visibility' => 'publiek',
        ]);
    }
    $page->update(['published_version_id' => $version->id]);

    return [$page, $version];
}

/**
 * De pagina-nav en -footer gebruiken zelf ook max-w-6xl (voor hún eigen
 * centrering) — assertions over of een band wel/niet begrensd is moeten dus
 * binnen <article>...</article> kijken, niet op de hele pagina.
 */
function articleHtml(string $html): string
{
    $start = strpos($html, '<article>');
    $end = strpos($html, '</article>');
    expect($start)->not->toBeFalse()->and($end)->not->toBeFalse();

    return substr($html, $start, $end - $start);
}

it('rendert een hero-blok full-bleed (h-screen, geen max-w-wrapper om de band) op de publieke pagina', function () {
    [$page] = makeHeroPage();

    $article = articleHtml($this->get($page->publicUrl())->assertOk()->getContent());

    expect($article)->toContain('h-screen')
        ->not->toContain('max-w-6xl');
});

it('rendert een hero-blok niet full-bleed in de paginabewerker', function () {
    [$page, $version] = makeHeroPage();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $html = Livewire::actingAs($user)
        ->test(PageEditor::class, ['versionId' => $version->id])
        ->html();

    expect($html)->not->toContain('h-screen')
        ->toContain('h-64');
});

it('behandelt een hero-blok in een 2-koloms-band ook als full-bleed', function () {
    [$page] = makeHeroPage(BandLayout::TwoColumns);

    $article = articleHtml($this->get($page->publicUrl())->assertOk()->getContent());

    expect($article)->toContain('h-screen')
        ->not->toContain('max-w-6xl');
});

it('laat drie feature-secties naast elkaar de volledige paginabreedte innemen (geen max-w-wrapper om de band)', function () {
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'drie-kolommen-test',
        'title' => 'Drie kolommen',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    $version = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Published]);
    $band = Band::create(['page_version_id' => $version->id, 'zone' => 'hoofd', 'layout' => BandLayout::ThreeColumns, 'sort_order' => 0]);
    foreach (range(0, 2) as $col) {
        Block::create([
            'band_id' => $band->id,
            'column_index' => $col,
            'sort_order' => 0,
            'type' => BlockType::FeatureSection,
            'content' => ['title' => "Kolom {$col}", 'body' => '', 'cta_label' => '', 'cta_href' => '', 'image_side' => 'left'],
            'visibility' => 'publiek',
        ]);
    }
    $page->update(['published_version_id' => $version->id]);

    $article = articleHtml($this->get($page->publicUrl())->assertOk()->getContent());

    expect($article)->toContain('md:grid-cols-3')
        ->not->toContain('max-w-6xl')
        ->toContain('Kolom 0')
        ->toContain('Kolom 1')
        ->toContain('Kolom 2');
});

it('geeft een tekstblok in dezelfde full-bleed-band zijn eigen leesbreedte-wrapper', function () {
    [$page] = makeHeroPage(extraBlocks: [BlockType::Text]);

    $article = articleHtml($this->get($page->publicUrl())->assertOk()->getContent());

    // De band zelf is onbegrensd (het hero-blok), maar het tekstblok erin
    // krijgt alsnog een eigen max-w-6xl-wrapper voor de leesbaarheid.
    expect($article)->toContain('h-screen')
        ->toContain('max-w-6xl')
        ->toContain('Bijschrift');
});
