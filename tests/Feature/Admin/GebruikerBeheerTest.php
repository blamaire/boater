<?php

use App\Enums\MembershipStatus;
use App\Livewire\Admin\GebruikerBeheer;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonRelation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $beheerderPerson = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $beheerderPerson->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('vereist users.manage permissie', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get('/beheer/gebruikers')->assertForbidden();
});

it('rendert de gebruikersbeheer-pagina voor een beheerder', function () {
    $this->actingAs($this->beheerder)->get('/beheer/gebruikers')->assertOk()->assertSee('Gebruikers');
});

it('maakt een nieuwe gebruiker (User + Person + Membership + rol) aan en stuurt uitnodiging', function () {
    Notification::fake();

    $typeA = MembershipType::query()->where('key', 'a')->firstOrFail();
    $rolBeheerder = Role::query()->where('name', 'Beheerder')->firstOrFail();

    $this->actingAs($this->beheerder);

    Livewire::test(GebruikerBeheer::class)
        ->set('firstName', 'Anna')
        ->set('lastName', 'Roeier')
        ->set('email', 'anna@example.com')
        ->set('membershipTypeId', $typeA->id)
        ->set('roleIds', [$rolBeheerder->id])
        ->set('sendInvitationMail', true)
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'anna@example.com')->firstOrFail();
    expect($user->person)->not->toBeNull()
        ->and($user->person->last_name)->toBe('Roeier')
        ->and($user->person->memberships()->first()->type->key)->toBe('a')
        ->and($user->person->roles()->pluck('name'))->toContain('Beheerder');

    Notification::assertSentTo($user, AccountInvitation::class);
});

it('maakt gebruiker aan zonder mail als de checkbox uit staat', function () {
    Notification::fake();
    $typeA = MembershipType::query()->where('key', 'a')->firstOrFail();

    $this->actingAs($this->beheerder);

    Livewire::test(GebruikerBeheer::class)
        ->set('firstName', 'Bram')
        ->set('lastName', 'Zeiler')
        ->set('email', 'bram@example.com')
        ->set('membershipTypeId', $typeA->id)
        ->set('sendInvitationMail', false)
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'bram@example.com')->firstOrFail();
    Notification::assertNothingSentTo($user);
});

it('eist een gekoppeld jeugdlid bij type ouder_verzorger', function () {
    $typeOuder = MembershipType::query()->where('key', 'ouder_verzorger')->firstOrFail();

    $this->actingAs($this->beheerder);

    Livewire::test(GebruikerBeheer::class)
        ->set('firstName', 'Carla')
        ->set('lastName', 'Ouder')
        ->set('email', 'carla@example.com')
        ->set('membershipTypeId', $typeOuder->id)
        ->call('save')
        ->assertHasErrors('relatedPersonId');
});

it('koppelt een ouder aan een jeugdlid via person_relations', function () {
    Notification::fake();

    $typeJeugd = MembershipType::query()->where('key', 'jeugd')->firstOrFail();
    $typeOuder = MembershipType::query()->where('key', 'ouder_verzorger')->firstOrFail();

    $kind = Person::create(['first_name' => 'Kim', 'last_name' => 'Kind']);
    Membership::create([
        'person_id' => $kind->id,
        'membership_type_id' => $typeJeugd->id,
        'status' => MembershipStatus::Active,
        'start_date' => now(),
        'billing_person_id' => $kind->id,
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(GebruikerBeheer::class)
        ->set('firstName', 'Otto')
        ->set('lastName', 'Ouder')
        ->set('email', 'otto@example.com')
        ->set('membershipTypeId', $typeOuder->id)
        ->set('relatedPersonId', $kind->id)
        ->set('relationType', 'ouder_van')
        ->call('save')
        ->assertHasNoErrors();

    $otto = User::query()->where('email', 'otto@example.com')->firstOrFail()->person;
    $relation = PersonRelation::query()->where('person_id', $otto->id)->firstOrFail();
    expect($relation->related_person_id)->toBe($kind->id)
        ->and($relation->type)->toBe('ouder_van');
});

it('weigert een bestaand e-mailadres', function () {
    User::factory()->create(['email' => 'dubbel@example.com']);
    $typeA = MembershipType::query()->where('key', 'a')->firstOrFail();

    $this->actingAs($this->beheerder);

    Livewire::test(GebruikerBeheer::class)
        ->set('firstName', 'Test')
        ->set('lastName', 'Persoon')
        ->set('email', 'dubbel@example.com')
        ->set('membershipTypeId', $typeA->id)
        ->call('save')
        ->assertHasErrors('email');
});

it('deactiveert en heractiveert een account', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'X', 'last_name' => 'Y', 'account_id' => $user->id]);

    $this->actingAs($this->beheerder);

    Livewire::test(GebruikerBeheer::class)->call('toggleActive', $user->id);
    expect($user->refresh()->disabled_at)->not->toBeNull();

    Livewire::test(GebruikerBeheer::class)->call('toggleActive', $user->id);
    expect($user->refresh()->disabled_at)->toBeNull();
});

it('verstuurt een nieuwe uitnodiging bij "opnieuw sturen"', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'opnieuw@example.com']);
    Person::create(['first_name' => 'H', 'last_name' => 'Erha', 'account_id' => $user->id]);

    $this->actingAs($this->beheerder);

    Livewire::test(GebruikerBeheer::class)->call('resendInvitation', $user->id);

    Notification::assertSentTo($user, AccountInvitation::class);
});

it('zorgt dat de Beheerder-rol automatisch de nieuwe users.manage-permissie heeft', function () {
    $perm = Permission::query()->where('key', 'users.manage')->firstOrFail();
    $beheerderRol = Role::query()->where('name', 'Beheerder')->firstOrFail();
    expect($beheerderRol->permissions()->pluck('permissions.id'))->toContain($perm->id);
});
