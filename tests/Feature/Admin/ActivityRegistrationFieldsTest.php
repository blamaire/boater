<?php

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
    $this->category = ActivityCategory::query()->where('slug', 'roeien')->firstOrFail();

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('voegt een inschrijfveld toe aan een bestaand voorkomen en kan het weer verwijderen', function () {
    $activity = Activity::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Toer', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    $component = Livewire::test(ActiviteitBeheer::class)
        ->call('toggleRegistrationFields', $activity->id)
        ->call('selectNewFieldType', 'text')
        ->set('newFieldLabel', 'Opmerking')
        ->call('addRegistrationFieldToActivity', $activity->id)
        ->assertHasNoErrors();

    expect($activity->registrationFields()->count())->toBe(1);
    $field = $activity->registrationFields()->firstOrFail();
    expect($field->label)->toBe('Opmerking')
        ->and($field->type)->toBe('text');

    $component->call('removeRegistrationField', $activity->id, $field->id);
    expect($activity->registrationFields()->count())->toBe(0);
});
