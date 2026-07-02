<?php

use App\Enums\MembershipStatus;
use App\Livewire\Admin\LedenBeheer;
use App\Livewire\Admin\LedenOverzicht;
use App\Models\AuditEntry;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

/**
 * @param  array<int, string>  $permissionKeys
 */
function ledenAdminUser(array $permissionKeys): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'Test',
        'last_name' => 'Beheerder',
        'account_id' => $user->id,
    ]);
    foreach ($permissionKeys as $key) {
        $permissionId = Permission::query()->where('key', $key)->value('id');
        PersonPermission::create([
            'person_id' => $person->id,
            'permission_id' => $permissionId,
            'status' => 'active',
        ]);
    }

    return $user;
}

function maakLidmaatschapsvorm(): MembershipType
{
    return MembershipType::create([
        'key' => 'a',
        'name' => 'A-lid',
        'min_age' => 21,
        'allows_boat_use' => true,
        'allows_instruction' => true,
        'intro_per_year' => 3,
        'allows_competition' => true,
        'seasonal_only' => false,
        'auto_expiry' => false,
        'requires_proof' => false,
        'is_partner_type' => false,
        'sort_order' => 10,
    ]);
}

it('weigert gast op ledenadministratie-routes', function () {
    $this->get('/beheer/leden')->assertRedirect('/login');
});

it('weigert ingelogde gebruiker zonder persons.view', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create([
        'first_name' => 'Jan',
        'last_name' => 'Lid',
        'account_id' => $user->id,
    ]);

    $this->actingAs($user)->get('/beheer/leden')->assertForbidden();
});

it('toont overzicht voor gebruiker met persons.view', function () {
    $user = ledenAdminUser(['persons.view']);
    Person::create(['first_name' => 'Anna', 'last_name' => 'Zoeker']);

    $this->actingAs($user)
        ->get('/beheer/leden')
        ->assertOk()
        ->assertSee('Anna')
        ->assertSee('Zoeker');
});

it('filtert het overzicht op zoekterm', function () {
    $user = ledenAdminUser(['persons.view']);
    Person::create(['first_name' => 'Piet', 'last_name' => 'Bezoeker']);
    Person::create(['first_name' => 'Anna', 'last_name' => 'Zoeker']);

    Livewire::actingAs($user)
        ->test(LedenOverzicht::class)
        ->set('zoekterm', 'Bezoek')
        ->assertSee('Piet')
        ->assertDontSee('Anna');
});

it('filtert het overzicht op lidmaatschapsstatus', function () {
    $user = ledenAdminUser(['persons.view']);
    $type = maakLidmaatschapsvorm();
    $actief = Person::create(['first_name' => 'Anna', 'last_name' => 'Actief']);
    $opgezegd = Person::create(['first_name' => 'Otto', 'last_name' => 'Opgezegd']);

    Membership::create([
        'person_id' => $actief->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now(),
    ]);
    Membership::create([
        'person_id' => $opgezegd->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Cancelled,
        'start_date' => now()->subYear(),
        'end_date' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(LedenOverzicht::class)
        ->set('statusFilter', MembershipStatus::Active->value)
        ->assertSee('Actief')
        ->assertDontSee('Otto');
});

it('toont detail voor gebruiker met persons.view', function () {
    $user = ledenAdminUser(['persons.view']);
    $person = Person::create(['first_name' => 'Anna', 'last_name' => 'Detail']);

    $this->actingAs($user)
        ->get('/beheer/leden/'.$person->id)
        ->assertOk()
        ->assertSee('Anna')
        ->assertSee('Detail');
});

it('slaat persoonsgegevens op en schrijft een audit-entry', function () {
    $user = ledenAdminUser(['persons.view', 'persons.update']);
    $person = Person::create(['first_name' => 'Anna', 'last_name' => 'Origineel']);

    Livewire::actingAs($user)
        ->test(LedenBeheer::class, ['personId' => $person->id])
        ->set('last_name', 'Nieuw')
        ->call('savePerson')
        ->assertHasNoErrors();

    expect(Person::query()->find($person->id)?->last_name)->toBe('Nieuw');

    $audit = AuditEntry::query()
        ->where('subject_type', Person::class)
        ->where('subject_id', $person->id)
        ->where('action', 'person.updated')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->before['last_name'] ?? null)->toBe('Origineel')
        ->and($audit->after['last_name'] ?? null)->toBe('Nieuw');
});

it('kent een lidmaatschap toe en logt de actie', function () {
    $user = ledenAdminUser(['persons.view', 'persons.update']);
    $person = Person::create(['first_name' => 'Anna', 'last_name' => 'Lidmaat']);
    $type = maakLidmaatschapsvorm();

    Livewire::actingAs($user)
        ->test(LedenBeheer::class, ['personId' => $person->id])
        ->set('newMembershipTypeId', $type->id)
        ->call('grantMembership')
        ->assertHasNoErrors();

    $membership = Membership::query()->where('person_id', $person->id)->first();

    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->membership_type_id)->toBe($type->id);

    $audit = AuditEntry::query()
        ->where('subject_type', Membership::class)
        ->where('subject_id', $membership->id)
        ->where('action', 'membership.granted')
        ->first();

    expect($audit)->not->toBeNull();
});

it('beëindigt een lidmaatschap en zet status op opgezegd', function () {
    $user = ledenAdminUser(['persons.view', 'persons.update']);
    $person = Person::create(['first_name' => 'Anna', 'last_name' => 'BeEindig']);
    $type = maakLidmaatschapsvorm();
    $membership = Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subYear(),
    ]);

    Livewire::actingAs($user)
        ->test(LedenBeheer::class, ['personId' => $person->id])
        ->set('endingMembershipId', $membership->id)
        ->set('endingMembershipEndDate', now()->format('Y-m-d'))
        ->call('endMembership')
        ->assertHasNoErrors();

    $membership->refresh();
    expect($membership->status)->toBe(MembershipStatus::Cancelled)
        ->and($membership->end_date)->not->toBeNull();

    $audit = AuditEntry::query()
        ->where('subject_type', Membership::class)
        ->where('subject_id', $membership->id)
        ->where('action', 'membership.ended')
        ->first();

    expect($audit)->not->toBeNull();
});
