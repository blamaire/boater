<?php

use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ReviewPolicySeeder::class);
});

it('redirects guests to login', function () {
    $this->get('/mijn/lidmaatschap')->assertRedirect(route('login'));
});

it('rendert voor een ingelogd, geverifieerd lid met een gekoppelde persoon', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create([
        'first_name' => 'Iris',
        'last_name' => 'Lid',
        'account_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get('/mijn/lidmaatschap')
        ->assertOk()
        ->assertSee('Mijn lidmaatschap')
        ->assertSee('Iris');
});

it('weigert een ingelogde user zonder gekoppelde person', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/mijn/lidmaatschap')
        ->assertForbidden();
});
