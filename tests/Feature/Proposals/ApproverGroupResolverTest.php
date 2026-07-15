<?php

use App\Enums\AssigneeType;
use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Enums\ReviewStepStatus;
use App\Models\ApproverGroup;
use App\Models\Person;
use App\Models\Proposal;
use App\Models\ReviewPolicy;
use App\Models\ReviewStep;
use App\Models\Role;
use App\Services\Proposals\ReviewerResolver;
use Database\Seeders\ApproverGroupSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ApproverGroupSeeder::class);
});

function makeGroupStep(int $groupId): ReviewStep
{
    $policy = ReviewPolicy::create([
        'name' => 'Test',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => AssigneeType::Group->value, 'assignee_id' => $groupId]],
    ]);
    $proposal = Proposal::create([
        'subject_type' => 'test.subject',
        'subject_id' => null,
        'change_type' => ChangeType::Create,
        'payload' => [],
        'status' => ProposalStatus::InReview,
        'policy_id' => $policy->id,
        'current_step' => 1,
    ]);

    return ReviewStep::create([
        'proposal_id' => $proposal->id,
        'sequence' => 1,
        'assignee_type' => AssigneeType::Group,
        'assignee_id' => $groupId,
        'status' => ReviewStepStatus::Pending,
    ]);
}

it('laat een expliciet lid van de groep beslissen', function () {
    $group = ApproverGroup::query()->where('name', 'Redactie')->firstOrFail();
    $lid = Person::create(['first_name' => 'Ex', 'last_name' => 'Plicit']);
    $group->members()->attach($lid->id);
    $step = makeGroupStep($group->id);

    expect(app(ReviewerResolver::class)->canDecide($step, $lid))->toBeTrue();
});

it('weigert iemand die niet lid is en geen Beheerder-rol heeft', function () {
    $group = ApproverGroup::query()->where('name', 'Redactie')->firstOrFail();
    $buitenstaander = Person::create(['first_name' => 'Bui', 'last_name' => 'Ten']);
    $step = makeGroupStep($group->id);

    expect(app(ReviewerResolver::class)->canDecide($step, $buitenstaander))->toBeFalse();
});

it('laat een persoon met de Beheerder-rol beslissen ook als hij geen expliciet groepslid is (§20.4 impliciete groepsleden)', function () {
    $group = ApproverGroup::query()->where('name', 'Redactie')->firstOrFail();
    $beheerder = Person::create(['first_name' => 'Be', 'last_name' => 'Heerder']);
    $beheerder->roleAssignments()->create([
        'role_id' => Role::query()->where('name', 'Beheerder')->value('id'),
        'status' => 'active',
        'assigned_at' => now(),
    ]);
    $step = makeGroupStep($group->id);

    expect(app(ReviewerResolver::class)->canDecide($step, $beheerder))->toBeTrue();
});
