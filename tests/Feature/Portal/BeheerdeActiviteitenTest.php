<?php

use App\Livewire\Portal\BeheerdeActiviteiten;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Person;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->category = ActivityCategory::create(['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10]);
});

it('toont alleen activiteiten waarvoor de ingelogde persoon beheerder is', function () {
    $managed = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Mijn activiteit', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);
    Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Andermans activiteit', 'starts_at' => now()->addDays(3),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'eheerder', 'account_id' => $user->id]);
    $managed->managers()->attach($person->id, ['notify' => true]);

    $this->actingAs($user);

    Livewire::test(BeheerdeActiviteiten::class)
        ->assertSee('Mijn activiteit')
        ->assertDontSee('Andermans activiteit');
});

it('laat een gedelegeerd beheerder de activiteit wijzigen zonder activities.update', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'eheerder', 'account_id' => $user->id]);
    $activity->managers()->attach($person->id, ['notify' => true]);

    $this->actingAs($user);
    expect($user->can('activities.update'))->toBeFalse();

    Livewire::test(BeheerdeActiviteiten::class)
        ->call('editActivity', $activity->id)
        ->set('location', 'Nieuwe plek')
        ->call('save')
        ->assertHasNoErrors();

    expect($activity->refresh()->location)->toBe('Nieuwe plek');
});

it('saniteert de omschrijving bij het opslaan door een gedelegeerd beheerder', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'eheerder', 'account_id' => $user->id]);
    $activity->managers()->attach($person->id, ['notify' => true]);

    $this->actingAs($user);

    Livewire::test(BeheerdeActiviteiten::class)
        ->call('editActivity', $activity->id)
        ->set('description', '<p>Neem <strong>zwemvest</strong> mee.</p><script>alert(1)</script>')
        ->call('save')
        ->assertHasNoErrors();

    expect($activity->refresh()->description)
        ->toBe('<p>Neem <strong>zwemvest</strong> mee.</p>');
});

it('weigert wijzigen van een activiteit waar de persoon geen beheerder van is', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'location' => 'Origineel', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'uitenstaander', 'account_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(BeheerdeActiviteiten::class)
        ->call('editActivity', $activity->id)
        ->set('location', 'Geknoei')
        ->call('save');

    expect($activity->refresh()->location)->toBe('Origineel');
});
