<?php

use App\Livewire\Admin\ActivityPageBeheer;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityPage;
use App\Models\Page;
use App\Models\Person;
use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ActivityCategorySeeder::class);
    $this->template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);

    $this->category = ActivityCategory::query()->where('slug', 'roeien')->firstOrFail();

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist activities.update permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/activiteiten/activiteitenpaginas')->assertForbidden();
});

it('rendert de beheer-pagina voor een beheerder', function () {
    $this->actingAs($this->beheerder)
        ->get('/beheer/activiteiten/activiteitenpaginas')
        ->assertOk()
        ->assertSee("Activiteitenpagina's");
});

it('maakt een activiteitenpagina aan', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ActivityPageBeheer::class)
        ->set('title', 'Zomerkamp 2027')
        ->call('save')
        ->assertHasNoErrors();

    $event = ActivityPage::query()->with('page')->firstOrFail();
    expect($event->page->title)->toBe('Zomerkamp 2027')
        ->and($event->page->slug)->toBe('zomerkamp-2027')
        ->and($event->page->versions()->count())->toBe(1);
});

it('kan de titel van een event wijzigen zonder de slug te veranderen', function () {
    $page = Page::create(['slug' => 'jeugdweek', 'title' => 'Jeugdweek', 'type' => 'content', 'template_id' => $this->template->id]);
    $event = ActivityPage::create(['page_id' => $page->id]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActivityPageBeheer::class)
        ->call('edit', $event->id)
        ->set('title', 'Jeugdweek 2027')
        ->call('save')
        ->assertHasNoErrors();

    expect($page->refresh()->title)->toBe('Jeugdweek 2027')
        ->and($page->slug)->toBe('jeugdweek');
});

it('weigert een event te verwijderen met gekoppelde activiteiten', function () {
    $page = Page::create(['slug' => 'wedstrijdweekend', 'title' => 'Wedstrijdweekend', 'type' => 'content', 'template_id' => $this->template->id]);
    $event = ActivityPage::create(['page_id' => $page->id]);
    Activity::create([
        'activity_category_id' => $this->category->id,
        'activity_page_id' => $event->id,
        'title' => 'Zaterdagwedstrijd',
        'starts_at' => now()->addDays(5),
        'visibility' => 'members',
        'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActivityPageBeheer::class)->call('delete', $event->id);

    expect(ActivityPage::query()->find($event->id))->not->toBeNull();
});

it('verwijdert een event zonder gekoppelde activiteiten maar behoudt de pagina', function () {
    $page = Page::create(['slug' => 'oud-event', 'title' => 'Oud event', 'type' => 'content', 'template_id' => $this->template->id]);
    $event = ActivityPage::create(['page_id' => $page->id]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActivityPageBeheer::class)->call('delete', $event->id);

    expect(ActivityPage::query()->find($event->id))->toBeNull()
        ->and(Page::query()->find($page->id))->not->toBeNull();
});
