<?php

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\MembershipStatus;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Models\Band;
use App\Models\Block;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\NavItem;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
    $this->template = Template::create([
        'name' => 'Standaard',
        'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']],
    ]);
});

function portalNavPage(Template $template, string $slug, string $title, ?int $parentId = null, PageVisibility $visibility = PageVisibility::Public): Page
{
    $page = Page::create([
        'slug' => $slug,
        'title' => $title,
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

function portalActiveMember(): User
{
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

    return $user;
}

function portalInactiveMember(): User
{
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

    return $user;
}

it('toont een beperkt-zichtbare root-pagina in de portal-navigatiebalk voor een ingelogd lid met actief lidmaatschap', function () {
    portalNavPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);

    $this->actingAs(portalActiveMember())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Voor leden');
});

it('toont die pagina niet voor een ingelogd oud-lid zonder actief lidmaatschap', function () {
    portalNavPage($this->template, 'voor-leden', 'Voor leden', visibility: PageVisibility::Restricted);

    $this->actingAs(portalInactiveMember())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Voor leden');
});

it('toont hetzelfde handmatig geconfigureerde NavItem-menu in de portal als op de publieke site', function () {
    $ouder = portalNavPage($this->template, 'vereniging', 'Vereniging');
    $kind = portalNavPage($this->template, 'historie', 'Historie', parentId: $ouder->id);
    $ouderItem = NavItem::create(['menu' => 'main', 'page_id' => $ouder->id, 'sort_order' => 10, 'visible' => true]);
    NavItem::create(['menu' => 'main', 'parent_id' => $ouderItem->id, 'page_id' => $kind->id, 'sort_order' => 10, 'visible' => true]);

    $user = portalActiveMember();

    $this->actingAs($user)
        ->get('/pagina/vereniging')
        ->assertOk()
        ->assertSeeText('Vereniging');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Vereniging');
});
