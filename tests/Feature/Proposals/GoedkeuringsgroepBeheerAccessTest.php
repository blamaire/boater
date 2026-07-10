<?php

use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ApproverGroupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ApproverGroupSeeder::class);
});

it('weigert een gast op /beheer/goedkeuringsgroepen', function () {
    $this->get('/beheer/goedkeuringsgroepen')->assertRedirect('/login');
});

it('weigert een ingelogde gebruiker zonder approver_groups.manage', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'X', 'last_name' => 'Y', 'account_id' => $user->id]);

    $this->actingAs($user)->get('/beheer/goedkeuringsgroepen')->assertForbidden();
});

it('rendert /beheer/goedkeuringsgroepen voor een beheerder', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $user->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->actingAs($user)
        ->get('/beheer/goedkeuringsgroepen')
        ->assertOk()
        ->assertSee('Redactie')
        ->assertSee('Ledenadministratie')
        ->assertSee('Materialen');
});
