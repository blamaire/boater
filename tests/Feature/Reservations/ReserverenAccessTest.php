<?php

use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
    $this->seed(ActivityCategorySeeder::class);
});

it('weigert een gast op /reserveren', function () {
    $this->get('/reserveren')->assertRedirect('/login');
});

it('weigert een ingelogde gebruiker zonder reservations.create', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'X', 'last_name' => 'Y', 'account_id' => $user->id]);

    $this->actingAs($user)->get('/reserveren')->assertForbidden();
});

it('rendert /reserveren voor een beheerder', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $user->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->actingAs($user)->get('/reserveren')->assertOk()->assertSee('Beschikbare objecten');
});

it('vereist reservable_objects.manage voor /beheer/objecten', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'X', 'last_name' => 'Y', 'account_id' => $user->id]);
    $this->actingAs($user)->get('/beheer/objecten')->assertForbidden();
});

it('rendert /beheer/objecten voor een beheerder', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $user->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->actingAs($user)->get('/beheer/objecten')->assertOk()->assertSee('Reserveerbare objecten');
});
