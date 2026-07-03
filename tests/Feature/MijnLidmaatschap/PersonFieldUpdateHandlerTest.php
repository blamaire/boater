<?php

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Models\AuditEntry;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\ReviewPolicy;
use App\Models\RoleAssignment;
use App\Services\Proposals\Handlers\PersonFieldUpdateHandler;
use App\Services\Proposals\ProposalEngine;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ReviewPolicySeeder::class);
    $this->engine = app(ProposalEngine::class);
});

it('past een goedgekeurd first_name-voorstel toe met audit-log', function () {
    $indiener = Person::create(['first_name' => 'Iris', 'last_name' => 'Lid']);
    $reviewer = Person::create(['first_name' => 'Roos', 'last_name' => 'Reviewer']);

    $policy = ReviewPolicy::query()->where('subject_type', PersonFieldUpdateHandler::SUBJECT_TYPE)->firstOrFail();
    $reviewerRoleId = $policy->steps[0]['assignee_id'];

    RoleAssignment::create([
        'person_id' => $reviewer->id,
        'role_id' => $reviewerRoleId,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    $proposal = $this->engine->submit(
        subjectType: PersonFieldUpdateHandler::SUBJECT_TYPE,
        changeType: ChangeType::Update,
        payload: [
            'person_id' => $indiener->id,
            'field' => 'first_name',
            'new_value' => 'Irene',
            'old_value' => 'Iris',
        ],
        proposer: $indiener,
        subjectId: $indiener->id,
    );

    expect($proposal->status)->toBe(ProposalStatus::InReview);

    $proposal = $this->engine->approveStep($proposal->steps->first(), $reviewer);

    expect($proposal->status)->toBe(ProposalStatus::Applied);
    expect($indiener->refresh()->first_name)->toBe('Irene');
    expect(AuditEntry::query()->where('action', 'person.field_updated')->count())->toBe(1);
});

it('gaat naar conflict-status als de onderliggende waarde intussen is gewijzigd', function () {
    $indiener = Person::create(['first_name' => 'Iris', 'last_name' => 'Lid']);
    $reviewer = Person::create(['first_name' => 'Roos', 'last_name' => 'Reviewer']);

    $policy = ReviewPolicy::query()->where('subject_type', PersonFieldUpdateHandler::SUBJECT_TYPE)->firstOrFail();
    $reviewerRoleId = $policy->steps[0]['assignee_id'];

    RoleAssignment::create([
        'person_id' => $reviewer->id,
        'role_id' => $reviewerRoleId,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    $proposal = $this->engine->submit(
        subjectType: PersonFieldUpdateHandler::SUBJECT_TYPE,
        changeType: ChangeType::Update,
        payload: [
            'person_id' => $indiener->id,
            'field' => 'first_name',
            'new_value' => 'Irene',
            'old_value' => 'Iris',
        ],
        proposer: $indiener,
        subjectId: $indiener->id,
    );

    // Iemand anders wijzigt intussen de naam.
    $indiener->first_name = 'Ida';
    $indiener->save();

    $proposal = $this->engine->approveStep($proposal->steps->first(), $reviewer);

    expect($proposal->status)->toBe(ProposalStatus::Conflicted);
    expect($indiener->refresh()->first_name)->toBe('Ida');
});

it('past een voorstel direct toe voor een indiener met persons.update-permissie (bypass)', function () {
    $indiener = Person::create(['first_name' => 'Iris', 'last_name' => 'Lid']);

    $permission = Permission::query()->where('key', 'persons.update')->firstOrFail();
    PersonPermission::create([
        'person_id' => $indiener->id,
        'permission_id' => $permission->id,
    ]);

    $proposal = $this->engine->submit(
        subjectType: PersonFieldUpdateHandler::SUBJECT_TYPE,
        changeType: ChangeType::Update,
        payload: [
            'person_id' => $indiener->id,
            'field' => 'first_name',
            'new_value' => 'Irene',
            'old_value' => 'Iris',
        ],
        proposer: $indiener,
        subjectId: $indiener->id,
    );

    expect($proposal->status)->toBe(ProposalStatus::Applied);
    expect($indiener->refresh()->first_name)->toBe('Irene');
});
