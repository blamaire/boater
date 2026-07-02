<?php

use App\Enums\ChangeType;
use App\Enums\MembershipStatus;
use App\Enums\ProposalStatus;
use App\Livewire\Public\LidWorden;
use App\Models\Guardianship;
use App\Models\Household;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\User;
use App\Services\Proposals\Handlers\MembershipApplicationHandler;
use App\Services\Proposals\ProposalEngine;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
    $this->seed(ReviewPolicySeeder::class);
});

it('rendert het lid-worden formulier op /lid-worden', function () {
    $this->get('/lid-worden')
        ->assertOk()
        ->assertSee('Lid worden')
        ->assertSee('A-lid');
});

it('kan een aanvraag indienen als volwassene die een A-lid kiest', function () {
    Livewire::test(LidWorden::class)
        ->set('first_name', 'John')
        ->set('last_name', 'Doe')
        ->set('date_of_birth', now()->subYears(30)->toDateString())
        ->set('email', 'john@example.com')
        ->set('postal_code', '1234AB')
        ->set('house_number', '1')
        ->set('street', 'Hoofdstraat')
        ->set('city', 'Gouda')
        ->set('membership_type_key', 'a')
        ->set('agree_statutes', true)
        ->set('agree_house_rules', true)
        ->set('agree_privacy', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $proposal = Proposal::query()->latest()->first();
    expect($proposal)->not->toBeNull()
        ->and($proposal->subject_type)->toBe(MembershipApplicationHandler::SUBJECT_TYPE)
        ->and($proposal->status)->toBe(ProposalStatus::InReview)
        ->and($proposal->proposed_by_person_id)->toBeNull()
        ->and($proposal->payload['person']['first_name'])->toBe('John')
        ->and($proposal->payload['is_minor'])->toBeFalse();
});

it('eist een uitleg bij het kiezen van een niet-passende vorm', function () {
    Livewire::test(LidWorden::class)
        ->set('first_name', 'Piet')
        ->set('last_name', 'Puk')
        ->set('date_of_birth', now()->subYears(15)->toDateString())
        ->set('email', 'piet@example.com')
        ->set('postal_code', '1234AB')
        ->set('house_number', '2')
        ->set('street', 'Kerkstraat')
        ->set('city', 'Gouda')
        ->set('guardian_first_name', 'Klaas')
        ->set('guardian_last_name', 'Puk')
        ->set('guardian_email', 'klaas@example.com')
        ->set('membership_type_key', 'a') // Niet passend: A-lid vereist 21+
        ->set('agree_statutes', true)
        ->set('agree_house_rules', true)
        ->set('agree_privacy', true)
        ->call('submit')
        ->assertHasErrors('override_reason');
});

it('past een goedgekeurde aanvraag toe: PERSON, HOUSEHOLD, MEMBERSHIP, USER + reset-mail', function () {
    Password::shouldReceive('broker')->andReturnSelf();
    Password::shouldReceive('sendResetLink')->once()->with(['email' => 'john@example.com']);

    $payload = [
        'person' => [
            'first_name' => 'John', 'last_name_prefix' => null, 'last_name' => 'Doe',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'email' => 'john@example.com', 'phone' => '0612345678',
        ],
        'address' => [
            'postal_code' => '1234AB', 'house_number' => '1', 'house_number_addition' => null,
            'street' => 'Hoofdstraat', 'city' => 'Gouda',
        ],
        'membership_type_key' => 'a',
        'membership_type_override_reason' => null,
        'is_minor' => false,
        'guardian' => null,
        'agreements' => ['statutes' => true, 'house_rules' => true, 'privacy' => true],
    ];

    $proposal = Proposal::create([
        'subject_type' => MembershipApplicationHandler::SUBJECT_TYPE,
        'change_type' => ChangeType::Create,
        'payload' => $payload,
        'status' => ProposalStatus::Approved,
    ]);

    app(MembershipApplicationHandler::class)->apply($proposal);

    $person = Person::query()->where('email', 'john@example.com')->first();
    expect($person)->not->toBeNull()
        ->and($person->first_name)->toBe('John')
        ->and($person->household->postal_code)->toBe('1234AB')
        ->and($person->account_id)->not->toBeNull();

    $membership = Membership::query()->where('person_id', $person->id)->first();
    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->type->key)->toBe('a')
        ->and($membership->billing_person_id)->toBe($person->id);

    expect(User::query()->where('email', 'john@example.com')->exists())->toBeTrue();
});

it('past een minderjarige aanvraag toe met nieuwe guardian: GUARDIANSHIP + billing naar guardian', function () {
    Password::shouldReceive('broker')->andReturnSelf();
    Password::shouldReceive('sendResetLink')->once()->with(['email' => 'klaas@example.com']);

    $payload = [
        'person' => [
            'first_name' => 'Piet', 'last_name_prefix' => null, 'last_name' => 'Puk',
            'date_of_birth' => now()->subYears(15)->toDateString(),
            'email' => 'piet@example.com', 'phone' => null,
        ],
        'address' => [
            'postal_code' => '1234AB', 'house_number' => '2', 'house_number_addition' => null,
            'street' => 'Kerkstraat', 'city' => 'Gouda',
        ],
        'membership_type_key' => 'jeugd',
        'membership_type_override_reason' => null,
        'is_minor' => true,
        'guardian' => [
            'first_name' => 'Klaas', 'last_name_prefix' => null, 'last_name' => 'Puk',
            'email' => 'klaas@example.com', 'phone' => null,
        ],
        'agreements' => ['statutes' => true, 'house_rules' => true, 'privacy' => true],
    ];

    $proposal = Proposal::create([
        'subject_type' => MembershipApplicationHandler::SUBJECT_TYPE,
        'change_type' => ChangeType::Create,
        'payload' => $payload,
        'status' => ProposalStatus::Approved,
    ]);

    app(MembershipApplicationHandler::class)->apply($proposal);

    $kind = Person::where('email', 'piet@example.com')->first();
    $guardian = Person::where('email', 'klaas@example.com')->first();
    expect($kind)->not->toBeNull()
        ->and($guardian)->not->toBeNull()
        ->and($guardian->account_id)->not->toBeNull()
        ->and($kind->household_id)->toBe($guardian->household_id);

    $link = Guardianship::where('minor_person_id', $kind->id)->first();
    expect($link)->not->toBeNull()
        ->and($link->guardian_person_id)->toBe($guardian->id)
        ->and($link->is_payer)->toBeTrue()
        ->and($link->may_act_on_behalf)->toBeTrue()
        ->and($link->consent_at)->not->toBeNull();

    $membership = Membership::where('person_id', $kind->id)->first();
    expect($membership->billing_person_id)->toBe($guardian->id);
});

it('past een minderjarige aanvraag toe met een bestaande guardian (ingelogd)', function () {
    $guardianHousehold = Household::create([
        'name' => 'Guardian', 'street' => 'Kerkstraat', 'house_number' => '2',
        'postal_code' => '1234AB', 'city' => 'Gouda',
    ]);
    $existingGuardian = Person::create([
        'first_name' => 'Anna', 'last_name' => 'Test',
        'email' => 'anna@example.com',
        'household_id' => $guardianHousehold->id,
    ]);

    $payload = [
        'person' => [
            'first_name' => 'Piet', 'last_name_prefix' => null, 'last_name' => 'Puk',
            'date_of_birth' => now()->subYears(15)->toDateString(),
            'email' => 'piet@example.com', 'phone' => null,
        ],
        'address' => [
            'postal_code' => '1234AB', 'house_number' => '2', 'house_number_addition' => null,
            'street' => 'Kerkstraat', 'city' => 'Gouda',
        ],
        'membership_type_key' => 'jeugd',
        'membership_type_override_reason' => null,
        'is_minor' => true,
        'guardian' => ['existing_person_id' => $existingGuardian->id],
        'agreements' => ['statutes' => true, 'house_rules' => true, 'privacy' => true],
    ];

    $proposal = Proposal::create([
        'subject_type' => MembershipApplicationHandler::SUBJECT_TYPE,
        'change_type' => ChangeType::Create,
        'payload' => $payload,
        'status' => ProposalStatus::Approved,
    ]);

    app(MembershipApplicationHandler::class)->apply($proposal);

    expect(Person::where('email', 'anna@example.com')->count())->toBe(1); // geen dubbele guardian

    $kind = Person::where('email', 'piet@example.com')->first();
    $link = Guardianship::where('minor_person_id', $kind->id)->first();
    expect($link->guardian_person_id)->toBe($existingGuardian->id);
});

it('kan als reviewer-Beheerder de aanvraag goedkeuren via de ProposalEngine', function () {
    $engine = app(ProposalEngine::class);

    $proposal = $engine->submit(
        subjectType: MembershipApplicationHandler::SUBJECT_TYPE,
        changeType: ChangeType::Create,
        payload: [
            'person' => [
                'first_name' => 'John', 'last_name_prefix' => null, 'last_name' => 'Doe',
                'date_of_birth' => now()->subYears(30)->toDateString(),
                'email' => 'john@example.com', 'phone' => null,
            ],
            'address' => [
                'postal_code' => '1234AB', 'house_number' => '1', 'house_number_addition' => null,
                'street' => 'Hoofdstraat', 'city' => 'Gouda',
            ],
            'membership_type_key' => 'a',
            'membership_type_override_reason' => null,
            'is_minor' => false,
            'guardian' => null,
            'agreements' => ['statutes' => true, 'house_rules' => true, 'privacy' => true],
        ],
    );

    expect($proposal->status)->toBe(ProposalStatus::InReview)
        ->and($proposal->proposed_by_person_id)->toBeNull();

    // Beheerder-persoon maken en de step goedkeuren
    $beheerder = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'email' => 'beheerder@example.com']);
    $beheerder->roles()->attach(Role::where('name', 'Beheerder')->value('id'));

    Password::shouldReceive('broker')->andReturnSelf();
    Password::shouldReceive('sendResetLink')->once();

    $step = $proposal->steps()->firstOrFail();
    $engine->approveStep($step, $beheerder);

    $proposal->refresh();
    expect($proposal->status)->toBe(ProposalStatus::Applied)
        ->and(Membership::where('membership_type_id', MembershipType::where('key', 'a')->value('id'))->count())->toBe(1);
});
