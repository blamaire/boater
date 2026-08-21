<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Models\Band;
use App\Models\Block;
use App\Models\NavItem;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Services\Cms\OrphanPageFinder;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

function makeContentPage(string $slug): Page
{
    return Page::create([
        'slug' => $slug,
        'title' => ucfirst(str_replace('-', ' ', $slug)),
        'template_id' => Template::first()->id,
    ]);
}

function makeHomePage(): Page
{
    return Page::create([
        'slug' => 'home',
        'title' => 'Home',
        'type' => PageType::System,
        'template_id' => Template::first()->id,
    ]);
}

function publishBlocks(Page $page, array $blocks): void
{
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

    foreach ($blocks as $i => [$type, $content]) {
        Block::create([
            'band_id' => $band->id,
            'column_index' => 0,
            'sort_order' => $i,
            'type' => $type,
            'content' => $content,
            'visibility' => 'publiek',
        ]);
    }

    $page->update(['published_version_id' => $version->id]);
}

function publishTextLink(Page $page, string $href): void
{
    publishBlocks($page, [[BlockType::Text, ['html' => '<p>Zie <a href="'.$href.'">link</a>.</p>']]]);
}

it('beschouwt een pagina met een zichtbaar menu-item niet als weespagina', function () {
    $page = makeContentPage('via-menu');
    NavItem::create(['menu' => 'main', 'page_id' => $page->id, 'visible' => true, 'sort_order' => 0]);

    expect(app(OrphanPageFinder::class)->find()->pluck('id'))->not->toContain($page->id);
});

it('markeert een pagina zonder menu-item of inkomende link als weespagina', function () {
    $page = makeContentPage('vergeten');

    expect(app(OrphanPageFinder::class)->find()->pluck('id'))->toContain($page->id);
});

it('beschouwt een pagina bereikbaar via een link vanuit een andere bereikbare pagina niet als weespagina', function () {
    $bron = makeContentPage('bron');
    NavItem::create(['menu' => 'main', 'page_id' => $bron->id, 'visible' => true, 'sort_order' => 0]);

    $doel = makeContentPage('doel');
    publishTextLink($bron, $doel->publicUrl());

    expect(app(OrphanPageFinder::class)->find()->pluck('id'))->not->toContain($doel->id);
});

it('telt een link in een conceptversie niet mee, alleen gepubliceerde content', function () {
    $bron = makeContentPage('bron-concept');
    NavItem::create(['menu' => 'main', 'page_id' => $bron->id, 'visible' => true, 'sort_order' => 0]);

    $doel = makeContentPage('doel-concept');

    $version = PageVersion::create([
        'page_id' => $bron->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
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
        'type' => BlockType::Text,
        'content' => ['html' => '<a href="'.$doel->publicUrl().'">link</a>'],
        'visibility' => 'publiek',
    ]);
    // Bewust geen $bron->update(['published_version_id' => ...]) — concept blijft ongepubliceerd.

    expect(app(OrphanPageFinder::class)->find()->pluck('id'))->toContain($doel->id);
});

it('herkent links vanuit knop-, kaart-, hero- en feature-sectieblokken', function () {
    $bron = makeContentPage('bron-blokken');
    NavItem::create(['menu' => 'main', 'page_id' => $bron->id, 'visible' => true, 'sort_order' => 0]);

    $knopDoel = makeContentPage('knop-doel');
    $kaartDoel = makeContentPage('kaart-doel');
    $heroDoel = makeContentPage('hero-doel');
    $featureDoel = makeContentPage('feature-doel');

    publishBlocks($bron, [
        [BlockType::Button, ['label' => 'Ga', 'href' => $knopDoel->publicUrl(), 'style' => 'primary']],
        [BlockType::Card, ['title' => 'x', 'body' => 'y', 'image_url' => null, 'href' => $kaartDoel->publicUrl()]],
        [BlockType::Hero, ['media_asset_id' => null, 'title' => 'x', 'subtitle' => 'y', 'cta_label' => 'a', 'cta_href' => $heroDoel->publicUrl(), 'cta2_label' => '', 'cta2_href' => '']],
        [BlockType::FeatureSection, ['media_asset_id' => null, 'title' => 'x', 'body' => 'y', 'cta_label' => 'a', 'cta_href' => $featureDoel->publicUrl(), 'image_side' => 'left']],
    ]);

    $orphanIds = app(OrphanPageFinder::class)->find()->pluck('id');

    expect($orphanIds)->not->toContain($knopDoel->id)
        ->not->toContain($kaartDoel->id)
        ->not->toContain($heroDoel->id)
        ->not->toContain($featureDoel->id);
});

it('maakt een doelpagina niet bereikbaar via een link vanuit een zelf onbereikbare pagina', function () {
    $onbereikbareBron = makeContentPage('onbereikbare-bron');

    $doel = makeContentPage('doel-via-onbereikbaar');
    publishTextLink($onbereikbareBron, $doel->publicUrl());

    $orphanIds = app(OrphanPageFinder::class)->find()->pluck('id');

    expect($orphanIds)->toContain($onbereikbareBron->id)
        ->toContain($doel->id);
});

it('telt een onzichtbaar menu-item niet mee als bereikbaarheid', function () {
    $page = makeContentPage('onzichtbaar-menu');
    NavItem::create(['menu' => 'main', 'page_id' => $page->id, 'visible' => false, 'sort_order' => 0]);

    expect(app(OrphanPageFinder::class)->find()->pluck('id'))->toContain($page->id);
});

it('matcht een link naar een extern domein niet aan een interne pagina met hetzelfde pad', function () {
    $bron = makeContentPage('bron-extern');
    NavItem::create(['menu' => 'main', 'page_id' => $bron->id, 'visible' => true, 'sort_order' => 0]);

    $doel = makeContentPage('doel-extern');
    publishTextLink($bron, 'https://oud.rzvg.nl'.$doel->publicUrl());

    expect(app(OrphanPageFinder::class)->find()->pluck('id'))->toContain($doel->id);
});

it('gebruikt de systeem-homepage altijd als startpunt, ook zonder eigen menu-item', function () {
    $home = makeHomePage();
    $doel = makeContentPage('via-home');
    publishTextLink($home, $doel->publicUrl());

    expect(app(OrphanPageFinder::class)->find()->pluck('id'))->not->toContain($doel->id);
});
