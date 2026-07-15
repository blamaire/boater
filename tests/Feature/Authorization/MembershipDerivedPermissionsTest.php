<?php

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Services\Authorization\EffectivePermissions;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
});

it('kent pages.propose automatisch toe aan een persoon met een actief lidmaatschap', function () {
    $p = Person::create(['first_name' => 'Lid', 'last_name' => 'Sson']);
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $p->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subMonth()->toDateString(),
        'billing_person_id' => $p->id,
    ]);

    expect(app(EffectivePermissions::class)->has($p, 'pages.propose'))->toBeTrue();
});

it('kent pages.propose NIET toe aan een persoon zonder lidmaatschap', function () {
    $p = Person::create(['first_name' => 'Niet', 'last_name' => 'Lid']);

    expect(app(EffectivePermissions::class)->has($p, 'pages.propose'))->toBeFalse();
});

it('kent pages.propose NIET toe aan een verlopen lidmaatschap', function () {
    $p = Person::create(['first_name' => 'Ver', 'last_name' => 'Lopen']);
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $p->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subYears(2)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'billing_person_id' => $p->id,
    ]);

    expect(app(EffectivePermissions::class)->has($p, 'pages.propose'))->toBeFalse();
});
