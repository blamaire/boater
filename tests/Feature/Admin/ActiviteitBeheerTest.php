<?php

use App\Enums\ActivityStatus;
use App\Livewire\Admin\ActiviteitBeheer;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityPage;
use App\Models\ActivitySeries;
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

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->category = ActivityCategory::query()->where('slug', 'roeien')->firstOrFail();
});

it('vereist activities.view permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/activiteiten')->assertForbidden();
});

it('rendert de beheer-pagina voor een beheerder', function () {
    $this->actingAs($this->beheerder)->get('/beheer/activiteiten')->assertOk()->assertSee('Activiteiten');
});

it('doet niets als save() wordt aangeroepen zonder een activiteit in bewerking (geen losse aanmaak meer)', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->set('categoryId', $this->category->id)
        ->set('title', 'Ochtendtoer')
        ->set('startsAt', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('saveActivity');

    expect(Activity::query()->where('title', 'Ochtendtoer')->exists())->toBeFalse();
});

it('wijzigt een bestaande activiteit', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Ochtendtoer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('editActivity', $activity->id)
        ->set('location', 'Steiger')
        ->set('capacity', 8)
        ->call('saveActivity')
        ->assertHasNoErrors();

    expect($activity->refresh()->location)->toBe('Steiger')
        ->and($activity->capacity)->toBe(8);
});

it('koppelt een bestaande activiteit aan een activiteitenpagina', function () {
    $page = Page::create(['slug' => 'zomerkamp', 'title' => 'Zomerkamp', 'type' => 'content', 'template_id' => $this->template->id]);
    $event = ActivityPage::create(['page_id' => $page->id]);
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Kampdag 1', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('editActivity', $activity->id)
        ->set('activityPageId', $event->id)
        ->call('saveActivity')
        ->assertHasNoErrors();

    expect($activity->refresh()->activity_page_id)->toBe($event->id);
});

it('overschrijft created_by_person_id niet bij het bewerken', function () {
    $original = Person::create(['first_name' => 'O', 'last_name' => 'rigineel']);
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'created_by_person_id' => $original->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('editActivity', $activity->id)
        ->set('location', 'Nieuwe plek')
        ->call('saveActivity');

    expect($activity->refresh()->created_by_person_id)->toBe($original->id);
});

it('markeert een reeks-voorkomen als uitzondering bij een losse wijziging van een gedeeld veld', function () {
    $series = ActivitySeries::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Cursus',
        'location' => 'Loods',
        'enrollment_level' => 'bundel',
    ]);
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'series_id' => $series->id,
        'title' => 'Cursus', 'location' => 'Loods', 'starts_at' => now()->addDays(3),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('editActivity', $activity->id)
        ->set('location', 'Andere loods')
        ->call('saveActivity')
        ->assertHasNoErrors();

    expect($activity->refresh()->is_exception)->toBeTrue()
        ->and($activity->location)->toBe('Andere loods');
});

it('valideert dat de einddatum niet vóór de startdatum ligt', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Foute activiteit', 'starts_at' => now()->addDays(3),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('editActivity', $activity->id)
        ->set('endsAt', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('saveActivity')
        ->assertHasErrors('endsAt');
});

it('kan een activiteit afgelasten', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer',
        'starts_at' => now()->addDays(2),
        'visibility' => 'members',
        'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)->call('cancelActivity', $activity->id);

    expect($activity->refresh()->status)->toBe(ActivityStatus::Cancelled);
});

it('verbergt historie standaard in de lijst', function () {
    Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Verleden toer',
        'starts_at' => now()->subDays(10),
        'visibility' => 'members',
        'status' => 'gepubliceerd',
    ]);
    Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toekomstige toer',
        'starts_at' => now()->addDays(10),
        'visibility' => 'members',
        'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->assertSee('Toekomstige toer')
        ->assertDontSee('Verleden toer')
        ->set('hideHistory', false)
        ->assertSee('Verleden toer');
});
