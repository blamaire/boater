<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVersionStatus;
use App\Models\Band;
use App\Models\Block;
use App\Models\NavItem;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\HomeSystemPageSeeder;
use Database\Seeders\LocalPublicMenuSeeder;
use Database\Seeders\TemplateSeeder;

/**
 * Wervende homepage: nieuwe bloktypes/-varianten, de publieke header/footer
 * en de seeder-inhoud.
 */
function pageWithBlocks(array $blocks): Page
{
    $template = Template::firstOrCreate(['name' => 'Standaard'], ['zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $page = Page::create([
        'slug' => 'blokken-'.uniqid(),
        'title' => 'Blokken',
        'type' => 'content',
        'visibility' => 'publiek',
        'template_id' => $template->id,
    ]);
    $version = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Published]);
    $band = Band::create(['page_version_id' => $version->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    foreach ($blocks as $i => [$type, $content]) {
        Block::create([
            'band_id' => $band->id,
            'column_index' => 0,
            'sort_order' => $i,
            'type' => $type,
            'content' => array_merge($type->defaultContent(), $content),
            'visibility' => 'publiek',
        ]);
    }
    $page->update(['published_version_id' => $version->id]);

    return $page;
}

it('rendert een stap- en een prijsblok', function () {
    $page = pageWithBlocks([
        [BlockType::Step, ['number' => '2', 'title' => 'Word lid', 'body' => 'Meld je online aan.']],
        [BlockType::Price, ['label' => 'Jeugd', 'amount' => '€165', 'body' => 'Van 12 tot 21 jaar.']],
    ]);

    $this->get($page->publicUrl())->assertOk()
        ->assertSee('Stap 2:', false)
        ->assertSee('Word lid')
        ->assertSee('€165')
        ->assertSee('Van 12 tot 21 jaar.');
});

it('maakt van een kaart met link één klikbaar geheel met eigen linktekst', function () {
    $page = pageWithBlocks([
        [BlockType::Card, ['title' => 'Roeien', 'body' => 'Toeren of wedstrijd.', 'href' => '/pagina/roeien', 'link_label' => 'Zo begin je met roeien']],
        [BlockType::Card, ['title' => 'Jeugd', 'body' => 'Voor ouders.', 'href' => '/pagina/jeugd', 'variant' => 'compact', 'icon' => 'users']],
    ]);

    $html = $this->get($page->publicUrl())->assertOk()->getContent();

    expect($html)->toContain('href="/pagina/roeien"')
        ->toContain('Zo begin je met roeien')
        ->toContain('bg-sand-100 p-6')
        // Zonder eigen linktekst valt een kaart terug op "Lees meer".
        ->toContain('Lees meer');
});

it('rendert een knopblok met een tweede knop ernaast', function () {
    $page = pageWithBlocks([
        [BlockType::Button, ['label' => 'Proefles aanvragen', 'href' => '/contact', 'style' => 'wit', 'label2' => 'Bel ons', 'href2' => 'tel:+31600000000', 'style2' => 'wit_omlijnd']],
    ]);

    $this->get($page->publicUrl())->assertOk()
        ->assertSee('Proefles aanvragen')
        ->assertSee('href="tel:+31600000000"', false)
        ->assertSee('Bel ons');
});

it('rendert een citaat als ledenverhaal met naam en rol', function () {
    $page = pageWithBlocks([
        [BlockType::Quote, ['style' => 'verhaal', 'text' => 'Mijn eerste haal was geweldig.', 'source' => 'Anna', 'role' => 'roeit sinds 2020']],
    ]);

    $this->get($page->publicUrl())->assertOk()
        ->assertSee('Mijn eerste haal was geweldig.')
        ->assertSee('Anna, roeit sinds 2020');
});

it('toont gasten een Lid worden-knop en een ledenstrook in de header, en ingelogden niet', function () {
    $page = pageWithBlocks([[BlockType::Heading, ['text' => 'Hallo']]]);

    $this->get($page->publicUrl())->assertOk()
        ->assertSee('Naar Mijn RZVG')
        ->assertSee(route('lid-worden'), false);

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->get($page->publicUrl())->assertOk()
        ->assertDontSee('Naar Mijn RZVG')
        ->assertSee('Ledenpagina');
});

it('zet de ledenstrook direct onder een openings-hero in plaats van boven de header', function () {
    $page = pageWithBlocks([[BlockType::Hero, ['title' => 'Welkom op het water']]]);

    $html = $this->get($page->publicUrl())->assertOk()->getContent();

    expect(substr_count($html, 'Naar Mijn RZVG'))->toBe(1)
        ->and(strpos($html, 'Naar Mijn RZVG'))->toBeGreaterThan(strpos($html, 'Welkom op het water'));
});

it('rendert knopstijl contact als de vaste Bel/mail mij terug-knop', function () {
    $page = pageWithBlocks([
        [BlockType::Button, ['label' => 'Proefles aanvragen', 'href' => '/contact', 'label2' => 'genegeerd', 'href2' => '/elders', 'style2' => 'contact']],
    ]);

    $html = $this->get($page->publicUrl())->assertOk()->getContent();

    // Eén keer zwevend rechtsonder, één keer in het knopblok.
    expect(substr_count($html, 'Bel/mail mij terug'))->toBe(2)
        ->and($html)->not->toContain('genegeerd');
});

it('toont het hoofdmenu in header en footer', function () {
    $page = pageWithBlocks([[BlockType::Heading, ['text' => 'Hallo']]]);
    NavItem::create(['menu' => 'main', 'label' => 'Over de vereniging', 'href' => '/pagina/over-de-vereniging', 'sort_order' => 0, 'visible' => true]);

    $html = $this->get($page->publicUrl())->assertOk()->getContent();

    // Desktopmenu, mobiel menu en de footerkolom "Ontdek".
    expect(substr_count($html, 'href="/pagina/over-de-vereniging"'))->toBe(3)
        ->and($html)->toContain('Ontdek');
});

it('zaait een wervende homepage op /', function () {
    $this->seed(TemplateSeeder::class);
    $this->seed(HomeSystemPageSeeder::class);

    $this->get('/')->assertOk()
        ->assertSee('Roeien, zeilen en suppen op de Reeuwijkse plassen')
        ->assertSee('Wat wil jij doen?')
        ->assertSee('Of zoek je iets anders?')
        ->assertSee('Zo begin je')
        ->assertSee('€390')
        ->assertSee('Leden aan het woord')
        ->assertSee('Zin om het te proberen?');
});

it('zaait het lokale hoofdmenu alleen bij APP_ENV=local', function () {
    $this->seed(TemplateSeeder::class);

    $this->seed(LocalPublicMenuSeeder::class);
    expect(NavItem::count())->toBe(0);

    app()->detectEnvironment(fn () => 'local');
    $this->seed(LocalPublicMenuSeeder::class);

    expect(NavItem::query()->orderBy('sort_order')->get()->map->displayLabel()->all())
        ->toBe(['Roeien', 'Zeilen', 'Suppen', 'Jeugd', 'Over de vereniging']);
});

it('toont gasten de vaste Proefles- en Lid worden-knoppen op home en CMS-pagina, ingelogden niet', function () {
    $page = pageWithBlocks([[BlockType::Heading, ['text' => 'Hallo']]]);

    $this->seed(HomeSystemPageSeeder::class);
    foreach (['/', $page->publicUrl()] as $url) {
        $this->get($url)->assertOk()
            ->assertSee(route('contact', ['onderwerp' => 'proefles']), false)
            ->assertSee('fixed inset-x-0 bottom-0', false)
            ->assertSee('Proefles aanvragen');
    }

    $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->get($page->publicUrl())->assertOk()
        ->assertDontSee(route('contact', ['onderwerp' => 'proefles']), false)
        ->assertDontSee('fixed inset-x-0 bottom-0', false);
});

it('toont de publieke header als twee balken met naam en niet-wrappende knoppen', function () {
    $page = pageWithBlocks([[BlockType::Heading, ['text' => 'Hallo']]]);

    $this->get($page->publicUrl())->assertOk()
        ->assertSee('Roei- en Zeilvereniging &quot;Gouda&quot;', false)
        ->assertSee('flex flex-nowrap items-center', false)
        ->assertSee('whitespace-nowrap', false)
        ->assertSee('role="search"', false);
});
