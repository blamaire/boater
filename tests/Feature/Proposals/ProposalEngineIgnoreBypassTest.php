<?php

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonPermission;
use App\Models\ReviewPolicy;
use App\Services\Proposals\ProposalEngine;
use App\Services\Proposals\ProposalHandlerRegistry;
use Tests\Support\FakeProposalHandler;

beforeEach(function () {
    $this->engine = app(ProposalEngine::class);
    $this->handler = new FakeProposalHandler;
    app(ProposalHandlerRegistry::class)->register('test.subject', $this->handler);

    $this->proposer = Person::create(['first_name' => 'P', 'last_name' => 'Roposer'.uniqid()]);
    $permission = Permission::firstOrCreate(
        ['key' => 'test.subject.bypass'],
        ['module' => 'test', 'action' => 'bypass'],
    );
    PersonPermission::create([
        'person_id' => $this->proposer->id,
        'permission_id' => $permission->id,
    ]);
});

it('bypasst standaard nog steeds als de indiener de bypass-permissie heeft', function () {
    $policy = ReviewPolicy::create([
        'name' => 'Met bypass',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => 999]],
        'bypass_permission' => 'test.subject.bypass',
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $this->proposer, policy: $policy);

    expect($proposal->status)->toBe(ProposalStatus::Applied);
});

it('negeert de bypass-permissie als ignoreBypass is gezet, en maakt reviewstappen aan', function () {
    $policy = ReviewPolicy::create([
        'name' => 'Met bypass, genegeerd',
        'subject_type' => 'test.subject',
        'auto_apply' => false,
        'steps' => [['assignee_type' => 'role', 'assignee_id' => 999]],
        'bypass_permission' => 'test.subject.bypass',
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Update, ['x' => 1], $this->proposer, policy: $policy, ignoreBypass: true);

    expect($proposal->status)->toBe(ProposalStatus::InReview)
        ->and($proposal->steps)->toHaveCount(1)
        ->and($this->handler->applied)->toBeEmpty();
});

it('past auto_apply nog steeds toe ook al is ignoreBypass gezet, want dat is een ander mechanisme', function () {
    $policy = ReviewPolicy::create([
        'name' => 'Auto-apply, ignoreBypass genegeerd voor deze vlag',
        'subject_type' => 'test.subject',
        'auto_apply' => true,
        'steps' => [],
        'resubmit_behavior' => 'restart',
    ]);

    $proposal = $this->engine->submit('test.subject', ChangeType::Create, ['x' => 1], $this->proposer, policy: $policy, ignoreBypass: true);

    expect($proposal->status)->toBe(ProposalStatus::Applied);
});
