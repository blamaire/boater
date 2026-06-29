<?php

use App\Models\AuditEntry;

it('allows creating an audit entry', function () {
    $entry = AuditEntry::create([
        'action' => 'test.action',
        'occurred_at' => now(),
    ]);

    expect($entry->exists)->toBeTrue();
});

it('prevents updating an audit entry', function () {
    $entry = AuditEntry::create([
        'action' => 'test.action',
        'occurred_at' => now(),
    ]);

    $entry->action = 'changed';

    expect(fn () => $entry->save())->toThrow(LogicException::class);
});

it('prevents deleting an audit entry', function () {
    $entry = AuditEntry::create([
        'action' => 'test.action',
        'occurred_at' => now(),
    ]);

    expect(fn () => $entry->delete())->toThrow(LogicException::class);
});
