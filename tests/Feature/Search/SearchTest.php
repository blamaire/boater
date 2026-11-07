<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\MembershipStatus;
use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Band;
use App\Models\Block;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Permission;
use App\Models\Person;
use App\Models\Template;
use App\Models\User;
use App\Services\Search\SearchService;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
    $this->template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
    $this->category = ActivityCategory::create(['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10]);
});

function searchPage(Template $template, string $title, PageVisibility $visibility = PageVisibility::Public, ?string $bodyText = null, bool $published = true, string $slug = ''): Page
{
    $page = Page::create([
        'slug' => $slug !== '' ? $slug : str($title)->slug()->toString(),
        'title' => $title,
        'type' => 'content',
        'visibility' => $visibility,
        'template_id' => $template->id,
    ]);
    $version = PageVersion::create([
        'page_id' => $page->id,
        'version_no' => 1,
        'status' => $published ? PageVersionStatus::Published : PageVersionStatus::Draft,
    ]);
    $band = Band::create(['page_version_id' => $version->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    if ($bodyText !== null) {
        Block::create([
            'band_id' => $band->id,
            'column_index' => 0,
            'sort_order' => 0,
            'type' => BlockType::Heading,
            'content' => ['level' => 2, 'text' => $bodyText],
        ]);
    }
    if ($published) {
        $page->update(['published_version_id' => $version->id]);
    }

    return $page;
}

function searchActivity(string $title, ActivityVisibility $visibility = ActivityVisibility::Public, ActivityStatus $status = ActivityStatus::Published, array $extra = []): Activity
{
    return Activity::create(array_merge([
        'activity_category_id' => ActivityCategory::query()->firstOrFail()->id,
        'title' => $title,
        'starts_at' => now()->addDays(5),
        'visibility' => $visibility,
        'status' => $status,
    ], $extra));
}

function searchMember(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Lid', 'last_name' => 'Gewoon', 'account_id' => $user->id]);
    Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => MembershipType::query()->where('key', 'a')->firstOrFail()->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subMonth()->toDateString(),
        'billing_person_id' => $person->id,
    ]);

    return $user;
}

function searchUserWith(string ...$permissionKeys): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Beheer', 'last_name' => 'Der', 'account_id' => $user->id]);
    foreach ($permissionKeys as $key) {
        $person->personPermissions()->create([
            'permission_id' => Permission::query()->where('key', $key)->firstOrFail()->id,
            'status' => 'active',
        ]);
    }

    return $user;
}

/** @return list<string> */
function searchTitles(array $groups, string $key): array
{
    foreach ($groups as $group) {
        if ($group['key'] === $key) {
            return array_map(fn ($r) => $r->title, $group['results']);
        }
    }

    return [];
}

it('toont een besloten pagina niet aan gasten maar wel aan leden', function () {
    searchPage($this->template, 'Ledeninfo zeilen', PageVisibility::Restricted);
    searchPage($this->template, 'Openbaar zeilen');

    $service = app(SearchService::class);

    expect(searchTitles($service->search('zeilen', null), 'paginas'))->toBe(['Openbaar zeilen'])
        ->and(searchTitles($service->search('zeilen', searchMember()), 'paginas'))->toBe(['Ledeninfo zeilen', 'Openbaar zeilen']);
});

it('toont members-activiteiten alleen aan ingelogde gebruikers', function () {
    searchActivity('Ledenborrel', ActivityVisibility::Members);
    searchActivity('Open borrel', ActivityVisibility::Public);

    $service = app(SearchService::class);

    expect(searchTitles($service->search('borrel', null), 'activiteiten'))->toBe(['Open borrel'])
        ->and(searchTitles($service->search('borrel', searchMember()), 'activiteiten'))->toContain('Ledenborrel', 'Open borrel');
});

it('toont staff-activiteiten alleen met activities.view', function () {
    searchActivity('Bestuursborrel', ActivityVisibility::Staff);

    $service = app(SearchService::class);

    expect(searchTitles($service->search('borrel', searchMember()), 'activiteiten'))->toBe([])
        ->and(searchTitles($service->search('borrel', searchUserWith('activities.view')), 'activiteiten'))->toBe(['Bestuursborrel']);
});

it('toont concept-activiteiten nooit', function () {
    searchActivity('Conceptborrel', ActivityVisibility::Public, ActivityStatus::Draft);

    expect(searchTitles(app(SearchService::class)->search('borrel', searchUserWith('activities.view')), 'activiteiten'))->toBe([]);
});

it('zoekt in de beschrijving en locatie van een activiteit en toont komende voor verleden', function () {
    searchActivity('Oude tocht', extra: ['starts_at' => now()->subDays(10), 'location' => 'Plassen']);
    searchActivity('Nieuwe tocht', extra: ['starts_at' => now()->addDays(10), 'description' => '<p>Een rit over de <b>Plassen</b>.</p>']);

    $groups = app(SearchService::class)->search('plassen', null);

    expect(searchTitles($groups, 'activiteiten'))->toBe(['Nieuwe tocht', 'Oude tocht'])
        ->and($groups[0]['results'][0]->snippet)->toBe('Een rit over de Plassen.');
});

it('vindt tekst in de blokken van de gepubliceerde versie, niet in een conceptversie', function () {
    $page = searchPage($this->template, 'Clubhuis', bodyText: 'Welkom bij de regatta');

    $concept = PageVersion::create(['page_id' => $page->id, 'version_no' => 2, 'status' => PageVersionStatus::Draft]);
    $band = Band::create(['page_version_id' => $concept->id, 'zone' => 'hoofd', 'layout' => BandLayout::OneColumn, 'sort_order' => 0]);
    Block::create(['band_id' => $band->id, 'column_index' => 0, 'sort_order' => 0, 'type' => BlockType::Heading, 'content' => ['level' => 2, 'text' => 'Alleen in concept: sloepen']]);

    $service = app(SearchService::class);

    expect(searchTitles($service->search('regatta', null), 'paginas'))->toBe(['Clubhuis'])
        ->and($service->search('regatta', null)[0]['results'][0]->snippet)->toContain('regatta')
        ->and($service->search('sloepen', null))->toBe([]);
});

it('neemt link-adressen en stijlnamen van blokken niet mee in treffers of fragmenten', function () {
    $page = searchPage($this->template, 'Proeflessen', bodyText: 'Kom een keer meevaren');
    Block::create([
        'band_id' => $page->publishedVersion->bands()->first()->id,
        'column_index' => 0,
        'sort_order' => 1,
        'type' => BlockType::Button,
        'content' => ['label' => 'Aanmelden', 'href' => '/contact', 'style' => 'primary'],
    ]);

    $service = app(SearchService::class);

    expect($service->search('contact', null))->toBe([])
        ->and($service->search('meevaren', null)[0]['results'][0]->snippet)->not->toContain('/contact');
});

it('vindt tekst met accenten in blokinhoud', function () {
    searchPage($this->template, 'Café', bodyText: 'Gezellig in het clubcafé');

    expect(searchTitles(app(SearchService::class)->search('clubcafé', null), 'paginas'))->toBe(['Café']);
});

it('toont niet-gepubliceerde pagina\'s niet', function () {
    searchPage($this->template, 'Geheim concept', published: false);

    expect(app(SearchService::class)->search('geheim', null))->toBe([]);
});

it('linkt de home-systeempagina naar /', function () {
    $home = searchPage($this->template, 'Welkom thuis', slug: 'home');
    $home->update(['type' => PageType::System]);

    $results = app(SearchService::class)->search('welkom', null)[0]['results'];

    expect($results[0]->url)->toBe('/');
});

it('toont de personen-groep alleen met users.manage', function () {
    Person::create(['first_name' => 'Pieter', 'last_name' => 'Zeiler', 'email' => 'pieter@example.test']);

    $service = app(SearchService::class);

    expect($service->search('zeiler', null))->toBe([])
        ->and($service->search('zeiler', searchMember()))->toBe([])
        ->and(searchTitles($service->search('zeiler', searchUserWith('users.manage')), 'personen'))->toBe(['Pieter Zeiler']);
});

it('zoekt LIKE-wildcards letterlijk', function () {
    searchPage($this->template, 'Korting 50% voor leden');
    searchPage($this->template, 'Korting 500 voor leden');
    searchPage($this->template, 'Sloep_een');
    searchPage($this->template, 'Sloepxeen');

    $service = app(SearchService::class);

    expect(searchTitles($service->search('50%', null), 'paginas'))->toBe(['Korting 50% voor leden'])
        ->and(searchTitles($service->search('p_e', null), 'paginas'))->toBe(['Sloep_een']);
});

it('zoekt zonder resultaten bij een te korte zoekterm', function () {
    searchPage($this->template, 'Aanmelden');

    expect(app(SearchService::class)->search(' a ', null))->toBe([])
        ->and(app(SearchService::class)->suggestTerms('a', null))->toBe([]);
});

it('maakt van HTML in een snippet platte tekst op de resultatenpagina en escapet scripts', function () {
    searchActivity('Open dag', extra: ['description' => '<p>Kom langs voor <b>zeilen</b> &amp; meer</p>']);
    searchPage($this->template, 'Zeilpagina', bodyText: '&lt;script&gt;alert(1)&lt;/script&gt; zeilen');

    $response = $this->get('/zoeken?q=zeilen')->assertOk();

    $response->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('<mark class="bg-rzvg-100 text-gray-900">zeilen</mark>', false)
        ->assertSee('Kom langs voor <mark class="bg-rzvg-100 text-gray-900">zeilen</mark> &amp; meer', false);
});

it('escapet de zoekterm zelf op de resultatenpagina', function () {
    $this->get('/zoeken?q='.urlencode('<script>x</script>'))
        ->assertOk()
        ->assertDontSee('<script>x</script>', false)
        ->assertSee('Geen resultaten');
});

it('geeft suggesties van woordaanvullingen op frequentie en alfabet', function () {
    searchPage($this->template, 'Zeilopleiding');
    searchPage($this->template, 'Zeilen op de Plas');
    searchActivity('Zeilen voor beginners');
    searchPage($this->template, 'Ledenzeilen', PageVisibility::Restricted);
    searchActivity('Zeilwedstrijd', ActivityVisibility::Staff);

    $this->getJson('/zoeken/suggesties?q=zeil')
        ->assertOk()
        ->assertExactJson(['suggestions' => ['zeilen', 'zeilopleiding']]);

    expect(app(SearchService::class)->suggestTerms('zeil', searchUserWith('activities.view')))
        ->toBe(['zeilen', 'zeilopleiding', 'zeilwedstrijd']);
});

it('werkt voor gast en lid op de resultatenpagina', function () {
    searchPage($this->template, 'Roeien voor beginners');

    $this->get('/zoeken')->assertOk()->assertSee('Zoeken');
    $this->get('/zoeken?q=roeien')->assertOk()->assertSee('1 resultaat voor');
    $this->actingAs(searchMember())->get('/zoeken?q=roeien')->assertOk()->assertSee('Roeien voor beginners');
});
