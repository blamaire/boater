<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Enums\PageVersionStatus;
use App\Livewire\Public\ActiviteitInschrijven;
use App\Livewire\Public\AgendaBlock;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityPage;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Models\PersonRelation;
use App\Models\Template;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = ActivityCategory::create(['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10]);
    $this->cat2 = ActivityCategory::create(['name' => 'Zeilen', 'slug' => 'zeilen', 'sort_order' => 20]);
    $this->template = Template::create(['name' => 'Standaard', 'zones' => [['key' => 'hoofd', 'label' => 'Hoofd']]]);
});

it('toont een link naar de infopagina van de gekoppelde activiteitenpagina', function () {
    $page = Page::create(['slug' => 'zomerkamp', 'title' => 'Zomerkamp 2027', 'type' => 'content', 'template_id' => $this->template->id]);
    $version = PageVersion::create(['page_id' => $page->id, 'version_no' => 1, 'status' => PageVersionStatus::Published]);
    $page->update(['published_version_id' => $version->id]);
    $event = ActivityPage::create(['page_id' => $page->id]);

    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'activity_page_id' => $event->id,
        'title' => 'Kampdag 1',
        'starts_at' => now()->addDays(3),
        'visibility' => ActivityVisibility::Public,
        'status' => ActivityStatus::Published,
    ]);

    $this->get(route('activiteit.show', $activity))
        ->assertOk()
        ->assertSee('Zomerkamp 2027')
        ->assertSee($page->publicUrl(), false);
});

it('weigert publieke bezoeker op een members-only activiteit', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Alleen leden',
        'starts_at' => now()->addDays(3),
        'visibility' => ActivityVisibility::Members,
        'status' => ActivityStatus::Published,
    ]);

    $this->get(route('activiteit.show', $activity))->assertForbidden();
});

it('toont een publieke activiteit voor een niet-ingelogde bezoeker', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Open dag',
        'starts_at' => now()->addDays(3),
        'visibility' => ActivityVisibility::Public,
        'status' => ActivityStatus::Published,
    ]);

    $this->get(route('activiteit.show', $activity))->assertOk()->assertSee('Open dag');
});

it('toont in de agenda alleen publieke activiteiten voor een gast', function () {
    Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Open dag',
        'starts_at' => now()->addDays(3),
        'visibility' => ActivityVisibility::Public,
        'status' => ActivityStatus::Published,
    ]);
    Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Alleen leden',
        'starts_at' => now()->addDays(4),
        'visibility' => ActivityVisibility::Members,
        'status' => ActivityStatus::Published,
    ]);

    Livewire::test(AgendaBlock::class, ['blockContent' => []])
        ->assertSee('Open dag')
        ->assertDontSee('Alleen leden');
});

it('past voorfilter categorieën toe zodat andere categorieën verborgen blijven', function () {
    Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Roeitoer',
        'starts_at' => now()->addDays(3),
        'visibility' => ActivityVisibility::Public,
        'status' => ActivityStatus::Published,
    ]);
    Activity::create([
        'activity_category_id' => $this->cat2->id,
        'title' => 'Zeildag',
        'starts_at' => now()->addDays(4),
        'visibility' => ActivityVisibility::Public,
        'status' => ActivityStatus::Published,
    ]);

    Livewire::test(AgendaBlock::class, ['blockContent' => ['category_ids' => [$this->category->id]]])
        ->assertSee('Roeitoer')
        ->assertDontSee('Zeildag');
});

it('verbergt historie standaard', function () {
    Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Oud',
        'starts_at' => now()->subDays(10),
        'visibility' => ActivityVisibility::Public,
        'status' => ActivityStatus::Published,
    ]);
    Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toekomst',
        'starts_at' => now()->addDays(10),
        'visibility' => ActivityVisibility::Public,
        'status' => ActivityStatus::Published,
    ]);

    Livewire::test(AgendaBlock::class, ['blockContent' => ['hide_history' => true]])
        ->assertSee('Toekomst')
        ->assertDontSee('Oud');
});

it('laat een ouder inschrijven voor het gekoppelde jeugdlid via person_relations', function () {
    $ouder = User::factory()->create(['email_verified_at' => now()]);
    $ouderPerson = Person::create(['first_name' => 'O', 'last_name' => 'uder', 'account_id' => $ouder->id]);
    $kind = Person::create(['first_name' => 'K', 'last_name' => 'ind']);
    PersonRelation::create([
        'person_id' => $ouderPerson->id,
        'related_person_id' => $kind->id,
        'type' => 'ouder_van',
    ]);

    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Jeugdtoer',
        'starts_at' => now()->addDays(3),
        'capacity' => 5,
        'visibility' => ActivityVisibility::Members,
        'status' => ActivityStatus::Published,
    ]);

    $this->actingAs($ouder);

    Livewire::test(ActiviteitInschrijven::class, ['activityId' => $activity->id])
        ->set('selectedPersonId', $kind->id)
        ->call('enroll');

    expect($activity->enrollments()->where('person_id', $kind->id)->count())->toBe(1);
});

it('toont en verwerkt extra inschrijfvelden op het publieke inschrijfformulier', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'L', 'last_name' => 'id', 'account_id' => $user->id]);

    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Kamp', 'starts_at' => now()->addDays(3), 'capacity' => 5,
        'visibility' => ActivityVisibility::Members, 'status' => ActivityStatus::Published,
    ]);
    $field = $activity->registrationFields()->create([
        'type' => 'count', 'label' => 'Introducees', 'price_per_unit' => 5, 'max_count' => 3,
    ]);

    $this->actingAs($user);

    Livewire::test(ActiviteitInschrijven::class, ['activityId' => $activity->id])
        ->assertSee('Introducees')
        ->set("fieldAnswers.{$field->id}", 2)
        ->assertSee('€10,00')
        ->call('enroll')
        ->assertHasNoErrors();

    $enrollment = $activity->enrollments()->where('person_id', $person->id)->firstOrFail();
    expect($enrollment->fieldValues()->first()->count_value)->toBe(2)
        ->and($enrollment->indicativeFieldsTotal())->toBe(10.0);
});
