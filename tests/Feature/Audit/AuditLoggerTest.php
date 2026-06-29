<?php

use App\Models\AuditEntry;
use App\Models\Person;
use App\Models\User;
use App\Services\Audit\AuditLogger;

beforeEach(function () {
    $this->logger = app(AuditLogger::class);
});

it('writes an audit entry with the given action', function () {
    $entry = $this->logger->log('something.happened');

    expect($entry->action)->toBe('something.happened')
        ->and(AuditEntry::count())->toBe(1);
});

it('captures the subject when one is given', function () {
    $person = Person::create(['first_name' => 'Test', 'last_name' => 'Persoon']);

    $entry = $this->logger->log('person.updated', $person);

    expect($entry->subject_type)->toBe(Person::class)
        ->and($entry->subject_id)->toBe($person->id);
});

it('stores before, after and context as arrays', function () {
    $entry = $this->logger->log(
        action: 'role.status_changed',
        before: ['status' => 'active'],
        after: ['status' => 'deactivated'],
        context: ['reason' => 'manual revoke'],
    );

    expect($entry->before)->toBe(['status' => 'active'])
        ->and($entry->after)->toBe(['status' => 'deactivated'])
        ->and($entry->context)->toBe(['reason' => 'manual revoke']);
});

it('records the actor when a user is authenticated', function () {
    $user = User::factory()->create();
    $person = Person::create([
        'first_name' => 'Test',
        'last_name' => 'Persoon',
        'account_id' => $user->id,
    ]);
    $this->actingAs($user);

    $entry = $this->logger->log('something.happened');

    expect($entry->actor_person_id)->toBe($person->id);
});

it('leaves actor null when no user is authenticated', function () {
    $entry = $this->logger->log('something.happened');

    expect($entry->actor_person_id)->toBeNull();
});

it('leaves actor null when authenticated user has no person', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $entry = $this->logger->log('something.happened');

    expect($entry->actor_person_id)->toBeNull();
});
