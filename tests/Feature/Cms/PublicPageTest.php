<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\MembershipStatus;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Band;
use App\Models\Block;
use App\Models\MediaAsset;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\NavItem;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

function publishedPage(Template $template, string $slug, string $title, ?int $parentId = null, PageVisibility $visibility = PageVisibility::Public, PageType $type = PageType::Content, array $meta = []): Page
{
    $page = Page::create([
        'slug' => $slug,
        'title' => $title,
        'type' => $type,
        'visibility' => $visibility,
        'parent_id' => $parentId,
        'template_id' => $template->id,
    ]);
    $version = PageVersion::create(array_merge([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => PageVersionStatus::Published,
    ], $meta));
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

function activeMemberUser(string $firstName = 'Actief', string $lastName = 'Lid'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => $firstName, 'last_name' => $lastName, 'account_id' => $user->id]);
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subMonth()->toDateString(),
        'billing_person_id' => $person->id,
    ]);

    return $user;
}

function inactiveMemberUser(string $firstName = 'Oud', string $lastName = 'Lid'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => $firstName, 'last_name' => $lastName, 'account_id' => $user->id]);
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subYears(2)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'billing_person_id' => $person->id,
    ]);

    return $user;
}

function editorUser(string $firstName = 'Redac', string $lastName = 'Teur'): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => $firstName, 'last_name' => $lastName, 'account_id' => $user->id]);
    $permissionId = Permission::query()->where('key', 'pages.view')->firstOrFail()->id;
    $person->personPermissions()->create(['permission_id' => $permissionId, 'status' => 'active']);

    return $user;
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

it('weigert een beperkt-zichtbare pagina voor uitgelogde bezoekers', function () {
    publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);

    $this->get('/pagina/voor-leden')->assertForbidden();
});

it('weigert een beperkt-zichtbare pagina voor ingelogde oud-leden zonder actief lidmaatschap', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);

    publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Oud', 'last_name' => 'Lid', 'account_id' => $user->id]);
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subYears(2)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'billing_person_id' => $person->id,
    ]);

    $this->actingAs($user)->get('/pagina/voor-leden')->assertForbidden();
});

it('toont een beperkt-zichtbare pagina aan een ingelogd lid met actief lidmaatschap', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);

    publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Actief', 'last_name' => 'Lid', 'account_id' => $user->id]);
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subMonth()->toDateString(),
        'billing_person_id' => $person->id,
    ]);

    $this->actingAs($user)->get('/pagina/voor-leden')->assertOk();
});

it('toont een beperkt-zichtbare pagina aan een redacteur zonder lidmaatschap', function () {
    $this->seed(PermissionSeeder::class);

    publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Redac', 'last_name' => 'Teur', 'account_id' => $user->id]);
    $permissionId = Permission::query()->where('key', 'pages.view')->firstOrFail()->id;
    $person->personPermissions()->create(['permission_id' => $permissionId, 'status' => 'active']);

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
    publishedPage($this->template, 'vereniging', 'Vereniging');

    // Bezoek een ándere pagina en controleer dat het menu (niet de
    // paginatekst zelf) een link naar 'over-ons' bevat.
    $this->get('/pagina/vereniging')
        ->assertOk()
        ->assertSee('/pagina/over-ons', false)
        ->assertSeeText('Over ons');
});

it('toont een beperkt-zichtbare pagina niet in het auto-fallback-menu voor een uitgelogde bezoeker', function () {
    publishedPage($this->template, 'openbaar', 'Openbaar');
    publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);

    $this->get('/pagina/openbaar')
        ->assertOk()
        ->assertDontSee('Voor leden');
});

it('toont een beperkt-zichtbare pagina niet in het auto-fallback-menu voor een ingelogd oud-lid zonder actief lidmaatschap', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);

    publishedPage($this->template, 'openbaar', 'Openbaar');
    publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);

    $this->actingAs(inactiveMemberUser())
        ->get('/pagina/openbaar')
        ->assertOk()
        ->assertDontSee('Voor leden');
});

it('toont een beperkt-zichtbare pagina wel in het auto-fallback-menu voor een ingelogd lid met actief lidmaatschap', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);

    publishedPage($this->template, 'openbaar', 'Openbaar');
    publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);

    $this->actingAs(activeMemberUser())
        ->get('/pagina/openbaar')
        ->assertOk()
        ->assertSee('/pagina/voor-leden', false)
        ->assertSeeText('Voor leden');
});

it('toont een beperkt-zichtbare pagina wel in het auto-fallback-menu voor een redacteur zonder lidmaatschap', function () {
    $this->seed(PermissionSeeder::class);

    publishedPage($this->template, 'openbaar', 'Openbaar');
    publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);

    $this->actingAs(editorUser())
        ->get('/pagina/openbaar')
        ->assertOk()
        ->assertSee('/pagina/voor-leden', false)
        ->assertSeeText('Voor leden');
});

it('toont een beperkt-zichtbare pagina niet in het handmatige menu voor een uitgelogde bezoeker', function () {
    $openbaar = publishedPage($this->template, 'openbaar', 'Openbaar');
    $besloten = publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);
    NavItem::create(['menu' => 'main', 'page_id' => $openbaar->id, 'sort_order' => 10, 'visible' => true]);
    NavItem::create(['menu' => 'main', 'page_id' => $besloten->id, 'sort_order' => 20, 'visible' => true]);

    $this->get('/pagina/openbaar')
        ->assertOk()
        ->assertSeeText('Openbaar')
        ->assertDontSee('Voor leden');
});

it('toont een beperkt-zichtbare pagina wel in het handmatige menu voor een ingelogd lid met actief lidmaatschap', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);

    $openbaar = publishedPage($this->template, 'openbaar', 'Openbaar');
    $besloten = publishedPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);
    NavItem::create(['menu' => 'main', 'page_id' => $openbaar->id, 'sort_order' => 10, 'visible' => true]);
    NavItem::create(['menu' => 'main', 'page_id' => $besloten->id, 'sort_order' => 20, 'visible' => true]);

    $this->actingAs(activeMemberUser())
        ->get('/pagina/openbaar')
        ->assertOk()
        ->assertSeeText('Voor leden');
});

it('toont een NavItem zonder gekoppelde pagina altijd, ongeacht zichtbaarheid van andere items', function () {
    publishedPage($this->template, 'openbaar', 'Openbaar');
    NavItem::create(['menu' => 'main', 'href' => 'https://roeienzeil.nl/extern', 'label' => 'Externe link', 'sort_order' => 10, 'visible' => true]);

    $this->get('/pagina/openbaar')
        ->assertOk()
        ->assertSeeText('Externe link');
});

it('filtert children van een handmatig NavItem op dezelfde zichtbaarheidsregel als het item zelf', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);

    $ouder = publishedPage($this->template, 'vereniging', 'Vereniging');
    $kind = publishedPage($this->template, 'historie', 'Historie', parentId: $ouder->id, visibility: PageVisibility::Restricted);
    $ouderItem = NavItem::create(['menu' => 'main', 'page_id' => $ouder->id, 'sort_order' => 10, 'visible' => true]);
    NavItem::create(['menu' => 'main', 'parent_id' => $ouderItem->id, 'page_id' => $kind->id, 'sort_order' => 10, 'visible' => true]);

    $this->get('/pagina/vereniging')->assertOk()->assertDontSee('Historie');

    $this->actingAs(activeMemberUser())
        ->get('/pagina/vereniging')
        ->assertOk()
        ->assertSeeText('Historie');
});

it('filtert children in het auto-fallback-menu op dezelfde regel als hun ouder', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);

    $ouder = publishedPage($this->template, 'vereniging', 'Vereniging');
    publishedPage($this->template, 'historie', 'Historie', parentId: $ouder->id, visibility: PageVisibility::Restricted);

    $this->get('/pagina/vereniging')->assertOk()->assertDontSee('Historie');

    $this->actingAs(activeMemberUser())
        ->get('/pagina/vereniging')
        ->assertOk()
        ->assertSeeText('Historie');
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

it('toont meta-omschrijving en OG-tags wanneer die gezet zijn', function () {
    publishedPage($this->template, 'met-meta', 'Met meta', meta: [
        'meta_description' => 'Mijn samenvatting',
        'og_title' => 'Mijn OG titel',
        'og_description' => 'Mijn OG omschrijving',
    ]);

    $this->get('/pagina/met-meta')
        ->assertOk()
        ->assertSee('<meta name="description" content="Mijn samenvatting">', false)
        ->assertSee('<meta property="og:title" content="Mijn OG titel">', false)
        ->assertSee('<meta property="og:description" content="Mijn OG omschrijving">', false);
});

it('valt terug op het RZVG-logo voor og:image zonder eigen afbeelding', function () {
    publishedPage($this->template, 'zonder-og-image', 'Zonder OG-afbeelding');

    $this->get('/pagina/zonder-og-image')
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.asset('img/branding/rzvg-logo.jpg').'">', false);
});

it('gebruikt de eigen MediaAsset-URL voor og:image wanneer die gezet is', function () {
    Storage::fake('media');
    Storage::disk('media')->put('assets/2026/og.jpg', 'inhoud');
    $asset = MediaAsset::create([
        'disk' => 'media',
        'path' => 'assets/2026/og.jpg',
        'original_name' => 'og.jpg',
        'mime_type' => 'image/jpeg',
        'type' => 'afbeelding',
        'file_size' => 6,
        'visibility' => PageVisibility::Public,
    ]);

    publishedPage($this->template, 'met-og-image', 'Met OG-afbeelding', meta: [
        'og_image_media_asset_id' => $asset->id,
    ]);

    $this->get('/pagina/met-og-image')
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.$asset->displayUrl().'">', false);
});

it('bevat een canonical-link met de absolute config(app.url)-URL', function () {
    $page = publishedPage($this->template, 'canonieke-pagina', 'Canonieke pagina');

    $expected = rtrim(config('app.url'), '/').$page->publicUrl();

    $this->get('/pagina/canonieke-pagina')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.$expected.'">', false)
        ->assertSee('<meta property="og:url" content="'.$expected.'">', false);
});
