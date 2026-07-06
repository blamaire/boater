<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Livewire\Public\ActiviteitInschrijven;
use App\Livewire\Public\AgendaBlock;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Person;
use App\Models\PersonRelation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = ActivityCategory::create(['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10]);
    $this->cat2 = ActivityCategory::create(['name' => 'Zeilen', 'slug' => 'zeilen', 'sort_order' => 20]);
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
