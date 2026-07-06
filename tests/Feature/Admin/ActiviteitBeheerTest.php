<?php

use App\Enums\ActivityStatus;
use App\Livewire\Admin\ActiviteitBeheer;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ActivityCategorySeeder::class);

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

it('maakt een nieuwe activiteit aan', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->set('categoryId', $this->category->id)
        ->set('title', 'Ochtendtoer')
        ->set('startsAt', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->set('location', 'Steiger')
        ->set('capacity', 8)
        ->set('visibility', 'members')
        ->set('status', 'gepubliceerd')
        ->call('save')
        ->assertHasNoErrors();

    $activity = Activity::query()->where('title', 'Ochtendtoer')->firstOrFail();
    expect($activity->activity_category_id)->toBe($this->category->id)
        ->and($activity->capacity)->toBe(8);
});

it('valideert dat de einddatum niet vóór de startdatum ligt', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->set('categoryId', $this->category->id)
        ->set('title', 'Foute activiteit')
        ->set('startsAt', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->set('endsAt', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->call('save')
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

    Livewire::test(ActiviteitBeheer::class)->call('cancel', $activity->id);

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
