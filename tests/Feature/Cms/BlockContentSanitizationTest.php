<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Models\Band;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;

/**
 * Maakt een band aan waaronder een blok kan hangen — de sanitizer draait op
 * Block::saving(), dus een geldige band_id is nodig om een blok te kunnen
 * opslaan.
 */
function makeSanitizationBand(): Band
{
    $template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'sanitization-test-'.uniqid(),
        'title' => 'Sanitisatietest',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
    ]);

    return Band::create(['page_version_id' => $version->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
}

it('verwijdert een script-tag uit een tekst-blok maar behoudt legitieme opmaak', function () {
    $band = makeSanitizationBand();

    $block = Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<p>Hallo</p><script>alert(1)</script>'],
        'visibility' => 'publiek',
    ]);

    $fresh = $block->fresh();

    expect($fresh->content['html'])
        ->not->toContain('<script>')
        ->toContain('<p>Hallo</p>');
});

it('verwijdert een onerror-attribuut uit een img in een tekst-blok maar behoudt de img-tag', function () {
    $band = makeSanitizationBand();

    $block = Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<img src="x" onerror="alert(1)">'],
        'visibility' => 'publiek',
    ]);

    $fresh = $block->fresh();

    expect($fresh->content['html'])
        ->not->toContain('onerror')
        ->toContain('<img');
});

it('vertaalt WordPress\' alignleft-class op een img naar onze eigen wp-align-left-class', function () {
    $band = makeSanitizationBand();

    $block = Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<img class="alignleft wp-image-30588" src="https://oud.rzvg.nl/foto.jpg">'],
        'visibility' => 'publiek',
    ]);

    $fresh = $block->fresh();

    expect($fresh->content['html'])
        ->toContain('class="wp-align-left"')
        ->not->toContain('wp-image-30588')
        ->not->toContain('alignleft wp-image');
});

it('verwijdert een class op img die geen WordPress-align-token bevat', function () {
    $band = makeSanitizationBand();

    $block = Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<img class="rounded-lg shadow-xl" src="https://oud.rzvg.nl/foto.jpg">'],
        'visibility' => 'publiek',
    ]);

    $fresh = $block->fresh();

    expect($fresh->content['html'])->not->toContain('class=');
});

it('staat class niet breed toe op andere elementen dan img', function () {
    $band = makeSanitizationBand();

    $block = Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<div class="alignleft">Tekst</div>'],
        'visibility' => 'publiek',
    ]);

    $fresh = $block->fresh();

    expect($fresh->content['html'])->not->toContain('class=');
});

it('verwijdert een javascript:-link uit een tekst-blok', function () {
    $band = makeSanitizationBand();

    $block = Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<a href="javascript:alert(1)">klik</a>'],
        'visibility' => 'publiek',
    ]);

    $fresh = $block->fresh();

    expect($fresh->content['html'])->not->toContain('javascript:');
});

it('saniteert het body-veld van een feature-sectie-blok', function () {
    $band = makeSanitizationBand();

    $block = Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::FeatureSection,
        'content' => ['title' => 'Titel', 'body' => '<p>Hallo</p><script>alert(1)</script>'],
        'visibility' => 'publiek',
    ]);

    $fresh = $block->fresh();

    expect($fresh->content['body'])
        ->not->toContain('<script>')
        ->toContain('<p>Hallo</p>');
});

it('laat de content van een kop-blok ongewijzigd bij het opslaan', function () {
    $band = makeSanitizationBand();

    $block = Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Heading,
        'content' => ['level' => 2, 'text' => '<script>alert(1)</script>Titel'],
        'visibility' => 'publiek',
    ]);

    $fresh = $block->fresh();

    expect($fresh->content['text'])->toBe('<script>alert(1)</script>Titel');
});

it('saniteert ook bij het renderen, als vangnet mocht een opslagroute de saniteer-observer ooit omzeilen', function () {
    $band = makeSanitizationBand();

    // Simuleer content die de opslag-sanitisatie omzeild heeft (bv. een
    // toekomstige nieuwe opslagroute die niet via het model saved) door de
    // observer bewust over te slaan — de preview-partial moet dit zelf ook
    // nog saniteren (defense-in-depth), niet enkel op saving() vertrouwen.
    $block = Block::withoutEvents(fn () => Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<p>Hallo</p><script>alert(1)</script>'],
        'visibility' => 'publiek',
    ]));

    $html = view('cms.blocks.preview', ['block' => $block])->render();

    expect($html)->not->toContain('<script>')->toContain('Hallo');
});

it('behoudt legitieme opmaak in een tekst-blok functioneel intact', function () {
    $band = makeSanitizationBand();

    $block = Block::create([
        'band_id' => $band->id,
        'column_index' => 0,
        'sort_order' => 0,
        'type' => BlockType::Text,
        'content' => ['html' => '<p>Welkom bij <strong>RZVG</strong>. Zie <a href="https://rzvg.nl">onze site</a>.</p>'],
        'visibility' => 'publiek',
    ]);

    $fresh = $block->fresh();

    expect($fresh->content['html'])
        ->toContain('<p>')
        ->toContain('<strong>RZVG</strong>')
        ->toContain('href="https://rzvg.nl"')
        ->toContain('onze site');
});
