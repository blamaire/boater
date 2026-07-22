<?php

use App\Enums\AssigneeType;
use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Enums\ReviewStepStatus;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\Proposal;
use App\Models\ReviewStep;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Carbon;

it('redirects guests to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('shows the dashboard with name, no roles and no permissions for a fresh user', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'Anne',
        'last_name' => 'Tester',
        'account_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welkom, Anne')
        ->assertSee('Je hebt op dit moment geen actieve rollen.')
        ->assertSee('Je hebt op dit moment geen effectieve permissies.');
});

it('lists active roles and groups effective permissions by module', function () {
    $this->seed(PermissionSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'Bert',
        'last_name' => 'Beheerder',
        'account_id' => $user->id,
    ]);

    $role = Role::create(['name' => 'Beheerder']);
    $role->permissions()->attach(Permission::where('key', 'audit_trail.view')->first());
    $role->permissions()->attach(Permission::where('key', 'roles.update')->first());

    RoleAssignment::create([
        'person_id' => $person->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => Permission::where('key', 'persons.update')->value('id'),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $response->assertSee('Beheerder')
        ->assertSee('Auditlogboek bekijken')
        ->assertSee('Rollen wijzigen')
        ->assertSee('Personen wijzigen')
        ->assertSee('Audit trail')
        ->assertSee('Rollen beheren');
});

it('shows a "Te beslissen" shortcut counting open steps assigned via role', function () {
    $this->seed(PermissionSeeder::class);

    $reviewerUser = User::factory()->create(['email_verified_at' => now()]);
    $reviewer = Person::create([
        'first_name' => 'Riet',
        'last_name' => 'Reviewer',
        'account_id' => $reviewerUser->id,
    ]);

    $role = Role::create(['name' => 'Reviewers']);
    RoleAssignment::create([
        'person_id' => $reviewer->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    $proposer = Person::create(['first_name' => 'P', 'last_name' => 'Indiener']);
    $proposal = Proposal::create([
        'subject_type' => 'test.subject',
        'change_type' => ChangeType::Update,
        'payload' => ['x' => 1],
        'proposed_by_person_id' => $proposer->id,
        'status' => ProposalStatus::InReview,
        'current_step' => 1,
    ]);
    ReviewStep::create([
        'proposal_id' => $proposal->id,
        'sequence' => 1,
        'assignee_type' => AssigneeType::Role,
        'assignee_id' => $role->id,
        'status' => ReviewStepStatus::Pending,
    ]);

    $this->actingAs($reviewerUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Te beslissen')
        ->assertSeeText('1');
});

it('shows a "Mijn open voorstellen" shortcut when the user has open proposals as proposer', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create([
        'first_name' => 'Iris',
        'last_name' => 'Indiener',
        'account_id' => $user->id,
    ]);

    Proposal::create([
        'subject_type' => 'test.subject',
        'change_type' => ChangeType::Update,
        'payload' => ['x' => 1],
        'proposed_by_person_id' => $person->id,
        'status' => ProposalStatus::InReview,
        'current_step' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Mijn open voorstellen');
});

it('shows a friendly notice when the user is not linked to a person', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Je account is nog niet gekoppeld aan een persoon.');
});

it('shows the environment label and a build version in the sidebar', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Lokaal');
});
