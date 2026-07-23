<?php

use App\Enums\AssigneeType;
use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Enums\ReviewStepStatus;
use App\Models\ApproverGroup;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReviewStep;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Services\Proposals\ReviewerResolver;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->resolver = app(ReviewerResolver::class);
});

function makeProposalWithGroupStep(string $groupName, int $currentStep = 1): array
{
    $proposer = Person::create(['first_name' => 'P', 'last_name' => 'Roposer']);
    $group = ApproverGroup::create(['name' => $groupName]);

    $proposal = Proposal::create([
        'subject_type' => 'test.decidable',
        'change_type' => ChangeType::Update,
        'payload' => ['x' => 1],
        'proposed_by_person_id' => $proposer->id,
        'status' => ProposalStatus::InReview,
        'current_step' => $currentStep,
    ]);

    $step = ReviewStep::create([
        'proposal_id' => $proposal->id,
        'sequence' => $currentStep,
        'assignee_type' => AssigneeType::Group,
        'assignee_id' => $group->id,
        'status' => ReviewStepStatus::Pending,
    ]);

    return [$proposal, $step, $group, $proposer];
}

it('geeft een groepslid de stap terug via decidableStepsQuery', function () {
    [, $step, $group] = makeProposalWithGroupStep('Redactie');

    $member = Person::create(['first_name' => 'M', 'last_name' => 'Ember']);
    $group->members()->attach($member->id);

    $steps = $this->resolver->decidableStepsQuery($member)->get();

    expect($steps->pluck('id'))->toContain($step->id);
});

it('geeft een Beheerder de stap terug via decidableStepsQuery, ook zonder groepslidmaatschap', function () {
    [, $step, $group] = makeProposalWithGroupStep('Redactie');

    $beheerder = Person::create(['first_name' => 'B', 'last_name' => 'Eheerder']);
    $role = Role::create(['name' => 'Beheerder']);
    RoleAssignment::create([
        'person_id' => $beheerder->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    expect($group->members()->where('person_id', $beheerder->id)->exists())->toBeFalse();

    $steps = $this->resolver->decidableStepsQuery($beheerder)->get();

    expect($steps->pluck('id'))->toContain($step->id);
});

it('sluit een niet-huidige (toekomstige) stap uit van decidableStepsQuery', function () {
    $proposer = Person::create(['first_name' => 'P', 'last_name' => 'Roposer']);
    $role = Role::create(['name' => 'Reviewers']);

    $proposal = Proposal::create([
        'subject_type' => 'test.decidable',
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
    $futureStep = ReviewStep::create([
        'proposal_id' => $proposal->id,
        'sequence' => 2,
        'assignee_type' => AssigneeType::Role,
        'assignee_id' => $role->id,
        'status' => ReviewStepStatus::Pending,
    ]);

    $reviewer = Person::create(['first_name' => 'R', 'last_name' => 'Eviewer']);
    RoleAssignment::create([
        'person_id' => $reviewer->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    $steps = $this->resolver->decidableStepsQuery($reviewer)->get();

    expect($steps->pluck('id'))->not->toContain($futureStep->id);
});
