<?php

use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('weigert een gast op /schade-melden', function () {
    $this->get('/schade-melden')->assertRedirect('/login');
});

it('weigert een ingelogde gebruiker zonder damage_reports.create', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'X', 'last_name' => 'Y', 'account_id' => $user->id]);

    $this->actingAs($user)->get('/schade-melden')->assertForbidden();
});

it('rendert /schade-melden voor een beheerder', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $user->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->actingAs($user)->get('/schade-melden')->assertOk()->assertSee('Nieuwe melding')
        ->assertSee('kies bestanden')->assertSee("Sleep foto's hierheen", false);
});

it('vereist damage_reports.view voor /beheer/schademeldingen', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'X', 'last_name' => 'Y', 'account_id' => $user->id]);
    $this->actingAs($user)->get('/beheer/schademeldingen')->assertForbidden();
});

it('rendert /beheer/schademeldingen voor een beheerder', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $user->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->actingAs($user)->get('/beheer/schademeldingen')->assertOk();
});
