<?php

use App\Models\InAppNotification;
use App\Models\Person;
use App\Services\Communication\InAppNotifier;

it('maakt een in-app-melding aan', function () {
    $person = Person::create(['first_name' => 'Kim', 'last_name' => 'Roeier']);

    $notification = app(InAppNotifier::class)->notify($person, 'test_type', 'Er is iets gebeurd', '/mijn/lidmaatschap');

    expect($notification->person_id)->toBe($person->id)
        ->and($notification->type)->toBe('test_type')
        ->and($notification->subject)->toBe('Er is iets gebeurd')
        ->and($notification->link)->toBe('/mijn/lidmaatschap')
        ->and($notification->read_at)->toBeNull();

    expect(InAppNotification::query()->where('person_id', $person->id)->count())->toBe(1);
});

it('markeert een melding als gelezen', function () {
    $person = Person::create(['first_name' => 'Kim', 'last_name' => 'Roeier']);
    $notification = app(InAppNotifier::class)->notify($person, 'test_type', 'X');

    expect($notification->read_at)->toBeNull();

    $notification->markAsRead();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('overschrijft read_at niet bij een tweede markAsRead-aanroep', function () {
    $person = Person::create(['first_name' => 'Kim', 'last_name' => 'Roeier']);
    $notification = app(InAppNotifier::class)->notify($person, 'test_type', 'X');

    $notification->markAsRead();
    $firstReadAt = $notification->fresh()->read_at;

    $notification->refresh();
    $notification->markAsRead();

    expect($notification->fresh()->read_at->equalTo($firstReadAt))->toBeTrue();
});
