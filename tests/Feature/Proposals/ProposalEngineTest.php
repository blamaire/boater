<?php

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Enums\ResubmitBehavior;
use App\Enums\ReviewStepStatus;
use App\Models\ApproverGroup;
use App\Models\AuditEntry;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\ReviewPolicy;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Services\Proposals\Exceptions\ProposalStateException;
use App\Services\Proposals\ProposalEngine;
use App\Services\Proposals\ProposalHandlerRegistry;
use Illuminate\Support\Carbon;
use Tests\Support\FakeProposalHandler;

beforeEach(function () {
    $this->engine = app(ProposalEngine::class);
    $this->handler = new FakeProposalHandler;
    app(ProposalHandlerRegistry::class)->register('test.subject', $this->handler);
});

it('routes a proposal through reviewsteps when policy requires it', function () {
    [$proposer, $reviewer] = persons(2);
    $role = roleFor($reviewer, 'reviewers');

    $policy = ReviewPolicy::create([
        'name' => 'Tweede view',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [
            ['assignee_type' => 'role', 'assignee_id' => $role->id],
        ],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit(
        subjectType: 'test.subject',
        changeType: ChangeType::Update,
        payload: ['veld' => 'waarde'],
        proposer: $proposer,
        subjectId: 42,
        policy: $policy,
    );

    expect($proposal->status)->toBe(ProposalStatus::InReview);
    expect($proposal->current_step)->toBe(1);
    expect($proposal->steps)->toHaveCount(1);
    expect($proposal->steps->first()->status)->toBe(ReviewStepStatus::Pending);
    expect($this->handler->applied)->toBeEmpty();
});

it('auto-applies a proposal when policy.auto_apply is true', function () {
    $proposer = persons(1)[0];

    $policy = ReviewPolicy::create([
        'name' => 'Binnen beleid',
        'subject_type' => 'test.subject',
        'auto_apply' => true,
        'steps' => [],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit(
        subjectType: 'test.subject',
        changeType: ChangeType::Create,
        payload: ['naam' => 'nieuw'],
        proposer: $proposer,
        policy: $policy,
    );

    expect($proposal->status)->toBe(ProposalStatus::Applied);
    expect($this->handler->applied[$proposal->id] ?? 0)->toBe(1);
});

it('bypasses review when proposer has the configured bypass permission', function () {
    $proposer = persons(1)[0];
    grantPermission($proposer, 'test.subject.bypass');

    $policy = ReviewPolicy::create([
        'name' => 'Met bypass',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => 999]],
        'bypass_permission' => 'test.subject.bypass',
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit(
        subjectType: 'test.subject',
        changeType: ChangeType::Update,
        payload: ['veld' => 'x'],
        proposer: $proposer,
        policy: $policy,
    );

    expect($proposal->status)->toBe(ProposalStatus::Applied);
    expect($this->handler->applied[$proposal->id] ?? 0)->toBe(1);
    expect(AuditEntry::where('action', 'proposal.bypassed')->count())->toBe(1);
});

it('moves to the next step on approval and applies after the last step', function () {
    [$proposer, $reviewerA, $reviewerB] = persons(3);
    $roleA = roleFor($reviewerA, 'review-1');
    $roleB = roleFor($reviewerB, 'review-2');

    $policy = ReviewPolicy::create([
        'name' => 'Derde view',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [
            ['assignee_type' => 'role', 'assignee_id' => $roleA->id],
            ['assignee_type' => 'role', 'assignee_id' => $roleB->id],
        ],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);

    $proposal = $this->engine->approveStep($proposal->steps->first(), $reviewerA);

    expect($proposal->status)->toBe(ProposalStatus::InReview);
    expect($proposal->current_step)->toBe(2);
    expect($this->handler->applied)->toBeEmpty();

    $proposal = $this->engine->approveStep($proposal->steps[1], $reviewerB);

    expect($proposal->status)->toBe(ProposalStatus::Applied);
    expect($this->handler->applied[$proposal->id] ?? 0)->toBe(1);
});

it('refuses approval by the proposer themself (functiescheiding)', function () {
    $proposer = persons(1)[0];
    $role = roleFor($proposer, 'self-approver');

    $policy = ReviewPolicy::create([
        'name' => 'Functiescheiding',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => $role->id]],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);

    expect(fn () => $this->engine->approveStep($proposal->steps->first(), $proposer))
        ->toThrow(ProposalStateException::class, 'Functiescheiding');
});

it('refuses approval by someone not authorized for the step', function () {
    [$proposer, $reviewer, $intruder] = persons(3);
    $role = roleFor($reviewer, 'reviewers');

    $policy = ReviewPolicy::create([
        'name' => 'Beperkte beslissers',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => $role->id]],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);

    expect(fn () => $this->engine->approveStep($proposal->steps->first(), $intruder))
        ->toThrow(ProposalStateException::class, 'niet bevoegd');
});

it('lets any group member approve a group-assigned step', function () {
    [$proposer, $memberA, $memberB] = persons(3);
    $group = ApproverGroup::create(['name' => 'Schippers']);
    $group->members()->attach([$memberA->id, $memberB->id]);

    $policy = ReviewPolicy::create([
        'name' => 'Groepsstap',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'group', 'assignee_id' => $group->id]],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);
    $proposal = $this->engine->approveStep($proposal->steps->first(), $memberB);

    expect($proposal->status)->toBe(ProposalStatus::Applied);
});

it('rejects a proposal and skips remaining steps', function () {
    [$proposer, $reviewerA, $reviewerB] = persons(3);
    $roleA = roleFor($reviewerA, 'r1');
    $roleB = roleFor($reviewerB, 'r2');

    $policy = ReviewPolicy::create([
        'name' => 'Twee stappen',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [
            ['assignee_type' => 'role', 'assignee_id' => $roleA->id],
            ['assignee_type' => 'role', 'assignee_id' => $roleB->id],
        ],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);
    $proposal = $this->engine->reject($proposal->steps->first(), $reviewerA, 'past niet bij beleid');

    expect($proposal->status)->toBe(ProposalStatus::Rejected);
    expect($proposal->decision_reason)->toBe('past niet bij beleid');
    expect($proposal->steps->first()->status)->toBe(ReviewStepStatus::Rejected);
    expect($proposal->steps[1]->status)->toBe(ReviewStepStatus::Skipped);
    expect($this->handler->applied)->toBeEmpty();
});

it('returns a proposal and on resubmit-restart rebuilds all steps from one', function () {
    [$proposer, $reviewerA, $reviewerB] = persons(3);
    $roleA = roleFor($reviewerA, 'r1');
    $roleB = roleFor($reviewerB, 'r2');

    $policy = ReviewPolicy::create([
        'name' => 'Restart',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [
            ['assignee_type' => 'role', 'assignee_id' => $roleA->id],
            ['assignee_type' => 'role', 'assignee_id' => $roleB->id],
        ],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);
    $proposal = $this->engine->approveStep($proposal->steps->first(), $reviewerA);
    $proposal = $this->engine->returnToSubmitter($proposal->steps[1], $reviewerB, 'graag herzien');

    expect($proposal->status)->toBe(ProposalStatus::Returned);

    $proposal = $this->engine->resubmit($proposal, ['x' => 2]);

    expect($proposal->status)->toBe(ProposalStatus::InReview);
    expect($proposal->current_step)->toBe(1);
    expect($proposal->steps)->toHaveCount(2);
    expect($proposal->steps->pluck('status')->all())
        ->toEqual([ReviewStepStatus::Pending, ReviewStepStatus::Pending]);
});

it('returns a proposal and on resubmit-continue resumes at the returned step', function () {
    [$proposer, $reviewerA, $reviewerB] = persons(3);
    $roleA = roleFor($reviewerA, 'r1');
    $roleB = roleFor($reviewerB, 'r2');

    $policy = ReviewPolicy::create([
        'name' => 'Continue',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [
            ['assignee_type' => 'role', 'assignee_id' => $roleA->id],
            ['assignee_type' => 'role', 'assignee_id' => $roleB->id],
        ],
        'resubmit_behavior' => ResubmitBehavior::Resume->value,
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);
    $proposal = $this->engine->approveStep($proposal->steps->first(), $reviewerA);
    $proposal = $this->engine->returnToSubmitter($proposal->steps[1], $reviewerB, 'graag herzien');

    $proposal = $this->engine->resubmit($proposal, ['x' => 2]);

    expect($proposal->status)->toBe(ProposalStatus::InReview);
    expect($proposal->current_step)->toBe(2);
    expect($proposal->steps->first()->status)->toBe(ReviewStepStatus::Approved);
    expect($proposal->steps[1]->status)->toBe(ReviewStepStatus::Pending);
});

it('lets the proposer withdraw an open proposal', function () {
    [$proposer, $reviewer] = persons(2);
    $role = roleFor($reviewer, 'r1');

    $policy = ReviewPolicy::create([
        'name' => 'Voor intrekken',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => $role->id]],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);
    $proposal = $this->engine->withdraw($proposal, $proposer);

    expect($proposal->status)->toBe(ProposalStatus::Withdrawn);
    expect($proposal->steps->first()->status)->toBe(ReviewStepStatus::Skipped);
});

it('does not allow a non-proposer to withdraw a proposal', function () {
    [$proposer, $outsider, $reviewer] = persons(3);
    $role = roleFor($reviewer, 'r1');

    $policy = ReviewPolicy::create([
        'name' => 'Intrekken-bescherming',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => $role->id]],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);

    expect(fn () => $this->engine->withdraw($proposal, $outsider))
        ->toThrow(ProposalStateException::class, 'indiener');
});

it('marks the proposal conflicted when revalidation fails and does not apply', function () {
    [$proposer, $reviewer] = persons(2);
    $role = roleFor($reviewer, 'r1');

    $policy = ReviewPolicy::create([
        'name' => 'Hervalidatie',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => $role->id]],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);

    $this->handler->throwConflictOnRevalidate = true;
    $this->handler->conflictMessage = 'onderliggende data is gewijzigd';

    $proposal = $this->engine->approveStep($proposal->steps->first(), $reviewer);

    expect($proposal->status)->toBe(ProposalStatus::Conflicted);
    expect($proposal->decision_reason)->toBe('onderliggende data is gewijzigd');
    expect($this->handler->applied)->toBeEmpty();
});

it('logs every state transition to the audit trail', function () {
    [$proposer, $reviewer] = persons(2);
    $role = roleFor($reviewer, 'r1');

    $policy = ReviewPolicy::create([
        'name' => 'Audit-controle',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => $role->id]],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $proposer, policy: $policy);
    $this->engine->approveStep($proposal->steps->first(), $reviewer);

    $actions = AuditEntry::where('action', 'like', 'proposal.%')->orderBy('id')->pluck('action')->all();
    expect($actions)->toEqual([
        'proposal.submitted',
        'proposal.step_approved',
        'proposal.applied',
    ]);
});

// --- helpers ---------------------------------------------------------------

function persons(int $count): array
{
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $out[] = Person::create([
            'first_name' => 'P'.uniqid(),
            'last_name' => 'Test',
        ]);
    }

    return $out;
}

function roleFor(Person $person, string $name): Role
{
    $role = Role::create(['name' => $name.'-'.uniqid()]);
    RoleAssignment::create([
        'person_id' => $person->id,
        'role_id' => $role->id,
        'status' => 'active',
        'assigned_at' => Carbon::now(),
    ]);

    return $role;
}

function grantPermission(Person $person, string $key): void
{
    $permission = Permission::firstOrCreate(
        ['key' => $key],
        ['module' => 'test', 'action' => 'bypass'],
    );
    PersonPermission::create([
        'person_id' => $person->id,
        'permission_id' => $permission->id,
    ]);
}
