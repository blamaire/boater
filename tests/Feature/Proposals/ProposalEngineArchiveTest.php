<?php

use App\Enums\ChangeType;
use App\Enums\ProposalStatus;
use App\Models\Person;
use App\Models\Proposal;
use App\Services\Proposals\Exceptions\ProposalStateException;
use App\Services\Proposals\ProposalEngine;

beforeEach(function () {
    $this->engine = app(ProposalEngine::class);
});

it('archiveert een afgewezen voorstel voor de indiener', function () {
    $proposer = Person::create(['first_name' => 'P', 'last_name' => 'Roposer']);

    $proposal = Proposal::create([
        'subject_type' => 'test.subject',
        'change_type' => ChangeType::Update,
        'payload' => ['x' => 1],
        'proposed_by_person_id' => $proposer->id,
        'status' => ProposalStatus::Rejected,
        'decision_reason' => 'past niet',
        'current_step' => 0,
    ]);

    $archived = $this->engine->archive($proposal, $proposer);

    expect($archived->archived_at)->not->toBeNull()
        ->and($archived->status)->toBe(ProposalStatus::Rejected);
});

it('weigert archiveren van een nog open voorstel', function () {
    $proposer = Person::create(['first_name' => 'P', 'last_name' => 'Roposer']);

    $proposal = Proposal::create([
        'subject_type' => 'test.subject',
        'change_type' => ChangeType::Update,
        'payload' => ['x' => 1],
        'proposed_by_person_id' => $proposer->id,
        'status' => ProposalStatus::InReview,
        'current_step' => 1,
    ]);

    expect(fn () => $this->engine->archive($proposal, $proposer))
        ->toThrow(ProposalStateException::class, 'afgehandelde');
});

it('weigert archiveren door iemand anders dan de indiener', function () {
    $proposer = Person::create(['first_name' => 'P', 'last_name' => 'Roposer']);
    $outsider = Person::create(['first_name' => 'O', 'last_name' => 'Utsider']);

    $proposal = Proposal::create([
        'subject_type' => 'test.subject',
        'change_type' => ChangeType::Update,
        'payload' => ['x' => 1],
        'proposed_by_person_id' => $proposer->id,
        'status' => ProposalStatus::Rejected,
        'current_step' => 0,
    ]);

    expect(fn () => $this->engine->archive($proposal, $outsider))
        ->toThrow(ProposalStateException::class, 'indiener');
});
