<?php

use App\Livewire\Public\SerieInschrijven;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivitySeries;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = ActivityCategory::create(['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10]);
});

it('toont de reeks-overzichtpagina met voorkomens en een serie-brede inschrijfknop', function () {
    $series = ActivitySeries::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Beginnerscursus',
        'visibility' => 'public',
        'status' => 'gepubliceerd',
        'enrollment_level' => 'reeks',
    ]);
    Activity::create([
        'activity_category_id' => $this->category->id, 'series_id' => $series->id,
        'title' => 'Beginnerscursus', 'starts_at' => now()->addDays(3),
        'visibility' => 'public', 'status' => 'gepubliceerd',
    ]);

    $this->get(route('activiteitenreeks.show', $series))
        ->assertOk()
        ->assertSee('Beginnerscursus')
        ->assertSee('Inschrijven voor de hele reeks');
});

it('schrijft in op alle voorkomens van de reeks in één keer', function () {
    $series = ActivitySeries::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Zwemcursus',
        'enrollment_level' => 'reeks',
    ]);
    $a1 = Activity::create([
        'activity_category_id' => $this->category->id, 'series_id' => $series->id,
        'title' => 'Zwemcursus', 'starts_at' => now()->addDays(3),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);
    $a2 = Activity::create([
        'activity_category_id' => $this->category->id, 'series_id' => $series->id,
        'title' => 'Zwemcursus', 'starts_at' => now()->addDays(10),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'L', 'last_name' => 'id', 'account_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(SerieInschrijven::class, ['seriesId' => $series->id])->call('enroll');

    expect(Enrollment::query()->where('activity_id', $a1->id)->where('person_id', $person->id)->exists())->toBeTrue()
        ->and(Enrollment::query()->where('activity_id', $a2->id)->where('person_id', $person->id)->exists())->toBeTrue()
        ->and(Enrollment::query()->where('series_id', $series->id)->count())->toBe(2);
});

it('weigert serie-inschrijving als de reeks alleen per voorkomen toestaat', function () {
    $series = ActivitySeries::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Los per keer',
        'enrollment_level' => 'bundel',
    ]);
    Activity::create([
        'activity_category_id' => $this->category->id, 'series_id' => $series->id,
        'title' => 'Los per keer', 'starts_at' => now()->addDays(3),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'L', 'last_name' => 'id', 'account_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(SerieInschrijven::class, ['seriesId' => $series->id])
        ->call('enroll')
        ->assertSet('statusMessage', 'Voor deze reeks kun je alleen per voorkomen inschrijven.');

    expect(Enrollment::query()->count())->toBe(0);
});

it('toont op het voorkomen zelf een link naar de reeks en verbergt losse inschrijving bij enrollment_level serie', function () {
    $series = ActivitySeries::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Kamp',
        'visibility' => 'public',
        'enrollment_level' => 'reeks',
    ]);
    $activity = Activity::create([
        'activity_category_id' => $this->category->id, 'series_id' => $series->id,
        'title' => 'Kampdag 1', 'starts_at' => now()->addDays(3),
        'visibility' => 'public', 'status' => 'gepubliceerd',
    ]);
    Activity::create([
        'activity_category_id' => $this->category->id, 'series_id' => $series->id,
        'title' => 'Kampdag 2', 'starts_at' => now()->addDays(4),
        'visibility' => 'public', 'status' => 'gepubliceerd',
    ]);

    $this->get(route('activiteit.show', $activity))
        ->assertOk()
        ->assertSee('Onderdeel van reeks')
        ->assertSee('kun je niet los inschrijven')
        ->assertDontSee('wire:click="enroll"', false);
});

it('verbergt "onderdeel van reeks" voor een losse activiteit (reeks met maar één voorkomen)', function () {
    $series = ActivitySeries::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Zomerkamp weekend',
        'visibility' => 'public',
        'enrollment_level' => 'bundel',
    ]);
    $activity = Activity::create([
        'activity_category_id' => $this->category->id, 'series_id' => $series->id,
        'title' => 'Zomerkamp weekend', 'starts_at' => now()->addDays(3),
        'visibility' => 'public', 'status' => 'gepubliceerd',
    ]);

    $this->get(route('activiteit.show', $activity))
        ->assertOk()
        ->assertDontSee('Onderdeel van reeks');
});
