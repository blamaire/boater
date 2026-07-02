<?php

use App\Enums\MembershipStatus;
use App\Models\Household;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Services\Membership\MembershipEligibility;
use Database\Seeders\MembershipTypeSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(MembershipTypeSeeder::class);
});

it('markeert A-lid als beschikbaar voor iemand van 25', function () {
    $result = app(MembershipEligibility::class)
        ->evaluate(Carbon::now()->subYears(25))
        ->keyBy(fn ($e) => $e->type->key);

    expect($result['a']->available)->toBeTrue()
        ->and($result['b']->available)->toBeTrue()
        ->and($result['jeugd']->available)->toBeFalse()
        ->and($result['jeugd']->reason)->toContain('12 t/m 20');
});

it('markeert jeugd als beschikbaar voor iemand van 15 en A-lid als niet', function () {
    $result = app(MembershipEligibility::class)
        ->evaluate(Carbon::now()->subYears(15))
        ->keyBy(fn ($e) => $e->type->key);

    expect($result['jeugd']->available)->toBeTrue()
        ->and($result['a']->available)->toBeFalse()
        ->and($result['a']->reason)->toContain('vanaf 21');
});

it('markeert aspirant voor kind van 8 en niet voor volwassene', function () {
    $kind = app(MembershipEligibility::class)
        ->evaluate(Carbon::now()->subYears(8))
        ->keyBy(fn ($e) => $e->type->key);
    $volwassene = app(MembershipEligibility::class)
        ->evaluate(Carbon::now()->subYears(30))
        ->keyBy(fn ($e) => $e->type->key);

    expect($kind['aspirant']->available)->toBeTrue()
        ->and($volwassene['aspirant']->available)->toBeFalse()
        ->and($volwassene['aspirant']->reason)->toContain('tot en met 11');
});

it('markeert gezins-A als niet-beschikbaar zonder A-lid op adres', function () {
    $household = Household::create([
        'name' => 'Testhuis', 'street' => 'Hoofd', 'house_number' => '1',
        'postal_code' => '1234AB', 'city' => 'Gouda',
    ]);

    $result = app(MembershipEligibility::class)
        ->evaluate(Carbon::now()->subYears(30), '1234AB', '1')
        ->keyBy(fn ($e) => $e->type->key);

    expect($result['gezins_a']->available)->toBeFalse()
        ->and($result['gezins_a']->reason)->toContain('A-lid op dit adres');
});

it('markeert gezins-A als beschikbaar als er een actief A-lid op het adres woont', function () {
    $household = Household::create([
        'name' => 'Testhuis', 'street' => 'Hoofd', 'house_number' => '1',
        'postal_code' => '1234AB', 'city' => 'Gouda',
    ]);
    $partner = Person::create([
        'first_name' => 'Anna', 'last_name' => 'Test',
        'household_id' => $household->id,
    ]);
    Membership::create([
        'person_id' => $partner->id,
        'membership_type_id' => MembershipType::where('key', 'a')->value('id'),
        'status' => MembershipStatus::Active,
    ]);

    $result = app(MembershipEligibility::class)
        ->evaluate(Carbon::now()->subYears(30), '1234AB', '1')
        ->keyBy(fn ($e) => $e->type->key);

    expect($result['gezins_a']->available)->toBeTrue()
        ->and($result['gezins_a']->reason)->toBeNull();
});
