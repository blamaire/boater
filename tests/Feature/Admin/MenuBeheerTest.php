<?php

use App\Enums\PageType;
use App\Enums\PageVersionStatus;
use App\Enums\PageVisibility;
use App\Livewire\Admin\MenuBeheer;
use App\Models\NavItem;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist menu.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/menu')->assertForbidden();
});

it('rendert de beheer-pagina voor een beheerder', function () {
    $this->actingAs($this->beheerder)->get('/beheer/menu')->assertOk()->assertSee('Menu-beheer');
});

it('voegt een menu-item toe dat naar een CMS-pagina wijst', function () {
    $page = pubPage($this->template, 'over-ons', 'Over ons');

    $this->actingAs($this->beheerder);

    Livewire::test(MenuBeheer::class)
        ->set('newPageId', $page->id)
        ->call('add')
        ->assertHasNoErrors();

    $item = NavItem::query()->firstOrFail();
    expect($item->page_id)->toBe($page->id)
        ->and($item->menu)->toBe('main')
        ->and($item->displayLabel())->toBe('Over ons');
});

it('voegt een menu-item toe met vrije URL en label', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(MenuBeheer::class)
        ->set('newHref', 'https://roeienzeil.nl')
        ->set('newLabel', 'Over de vereniging')
        ->call('add')
        ->assertHasNoErrors();

    $item = NavItem::query()->firstOrFail();
    expect($item->href)->toBe('https://roeienzeil.nl')
        ->and($item->displayLabel())->toBe('Over de vereniging');
});

it('weigert een item zonder pagina en zonder URL', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(MenuBeheer::class)
        ->call('add')
        ->assertHasErrors('newHref');
});

it('toggelt zichtbaarheid en verwijdert items', function () {
    $item = NavItem::create(['menu' => 'main', 'href' => 'https://x', 'label' => 'Test', 'sort_order' => 10]);

    $this->actingAs($this->beheerder);

    Livewire::test(MenuBeheer::class)->call('toggleVisible', $item->id);
    expect($item->refresh()->visible)->toBeFalse();

    Livewire::test(MenuBeheer::class)->call('delete', $item->id);
    expect(NavItem::query()->count())->toBe(0);
});

it('gebruikt handmatige items in de publieke nav zodra ze bestaan', function () {
    $page = pubPage($this->template, 'over-ons', 'Over ons');
    NavItem::create(['menu' => 'main', 'page_id' => $page->id, 'sort_order' => 10, 'visible' => true]);

    // Extra CMS-pagina die NIET in het menu hoort te staan (fallback zou 'm wel tonen).
    pubPage($this->template, 'blog', 'Blog');

    $response = $this->get('/pagina/over-ons');
    $response->assertOk()
        ->assertSee('Over ons')
        ->assertDontSee('Blog');
});

it('valt terug op auto-nav als er geen handmatige items zijn', function () {
    pubPage($this->template, 'blog', 'Blog');

    $this->get('/pagina/blog')->assertOk()->assertSee('Blog');
});

function pubPage(Template $template, string $slug, string $title): Page // helper
{
    $page = Page::create([
        'slug' => $slug, 'title' => $title,
        'type' => PageType::Content, 'visibility' => PageVisibility::Public,
        'template_id' => $template->id,
    ]);
    $version = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Published]);
    $page->update(['published_version_id' => $version->id]);

    return $page;
}
