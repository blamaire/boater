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
use App\Models\User;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

function publishedPage(Template $template, string $slug, string $title, ?int $parentId = null, PageVisibility $visibility = PageVisibility::Public, PageType $type = PageType::Content): Page
{
    $page = Page::create([
        'slug' => $slug,
        'title' => $title,
        'type' => $type,
        'visibility' => $visibility,
        'parent_id' => $parentId,
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
        'content' => ['level' => 1, 'text' => $title],
    ]);

    $page->update(['published_version_id' => $version->id]);

    return $page;
}

it('renders a published public root page on /{slug}', function () {
    publishedPage($this->template, 'over-ons', 'Over ons');

    $this->get('/pagina/over-ons')
        ->assertOk()
        ->assertSee('Over ons');
});

it('renders a hierarchical page on /{parent-slug}/{child-slug}', function () {
    $parent = publishedPage($this->template, 'vereniging', 'Vereniging');
    publishedPage($this->template, 'historie', 'Historie', parentId: $parent->id);

    $this->get('/pagina/vereniging/historie')
        ->assertOk()
        ->assertSee('Historie');
});

it('returns 404 for a missing slug', function () {
    $this->get('/pagina/bestaat-niet')->assertNotFound();
});

it('returns 404 when no published version exists', function () {
    $page = Page::create([
        'slug' => 'concept-only',
        'title' => 'Concept',
        'template_id' => $this->template->id,
    ]);
    PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Draft,
    ]);

    $this->get('/pagina/concept-only')->assertNotFound();
});

it('requires login for members-only pages', function () {
    publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Members);

    $this->get('/pagina/voor-leden')->assertForbidden();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->get('/pagina/voor-leden')->assertOk();
});

it('renders welcome fallback when no home page exists', function () {
    $this->get('/')->assertOk();
});

it('renders the system home page on / when a system page with slug "home" is published', function () {
    publishedPage($this->template, 'home', 'Welkom bij RZVG', type: PageType::System);

    $this->get('/')
        ->assertOk()
        ->assertSee('Welkom bij RZVG');
});

it('does NOT render a content home on / even if such a page exists', function () {
    // Alleen een systeempagina claimt /; een content-pagina met slug "home" is
    // bereikbaar op /pagina/home, niet op /.
    publishedPage($this->template, 'home', 'Content Home', type: PageType::Content);

    $this->get('/')->assertOk()->assertDontSee('Content Home');
    $this->get('/pagina/home')->assertOk()->assertSee('Content Home');
});

it('serves system and content pages with slug "home" side by side', function () {
    publishedPage($this->template, 'home', 'Systeem Welkom', type: PageType::System);
    publishedPage($this->template, 'home', 'Content Welkom', type: PageType::Content);

    $this->get('/')->assertOk()->assertSee('Systeem Welkom')->assertDontSee('Content Welkom');
    $this->get('/pagina/home')->assertOk()->assertSee('Content Welkom')->assertDontSee('Systeem Welkom');
});

it('serves system routes untouched even when a CMS-page with the same slug exists', function () {
    publishedPage($this->template, 'login', 'Verwarrende pagina');

    // /login blijft de auth-route
    $this->get('/login')->assertOk()->assertDontSee('Verwarrende pagina');

    // /pagina/login serveert de CMS-pagina
    $this->get('/pagina/login')->assertOk()->assertSee('Verwarrende pagina');
});

it('exposes Page::publicUrl() with prefix, except for the system home page', function () {
    $systemHome = publishedPage($this->template, 'home', 'Home', type: PageType::System);
    $contentHome = publishedPage($this->template, 'home', 'Content Home', type: PageType::Content);
    $overOns = publishedPage($this->template, 'over-ons', 'Over ons');
    $vereniging = publishedPage($this->template, 'vereniging', 'Vereniging');
    $historie = publishedPage($this->template, 'historie', 'Historie', parentId: $vereniging->id);

    expect($systemHome->publicUrl())->toBe('/')
        ->and($contentHome->publicUrl())->toBe('/pagina/home')
        ->and($overOns->publicUrl())->toBe('/pagina/over-ons')
        ->and($historie->publicUrl())->toBe('/pagina/vereniging/historie');
});

it('lists public root pages in the menu via the view composer', function () {
    publishedPage($this->template, 'over-ons', 'Over ons');

    $this->get('/pagina/over-ons')
        ->assertOk()
        ->assertSeeText('Over ons');
});

it('renders responsive grid classes for a multi-column band', function () {
    $page = Page::create([
        'slug' => 'kolommen',
        'title' => 'Kolommen',
        'template_id' => $this->template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
    ]);
    Band::create([
        'page_version_id' => $version->id,
        'zone' => 'hoofd',
        'layout' => BandLayout::TwoColumns,
        'sort_order' => 0,
    ]);
    $page->update(['published_version_id' => $version->id]);

    $response = $this->get('/pagina/kolommen')->assertOk();

    $response->assertSee('md:grid-cols-2', false);
    $response->assertDontSee('@class(', false);
});
