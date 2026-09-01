<?php

use App\Livewire\Portal\NotificationBell;
use App\Models\InAppNotification;
use App\Models\Person;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->person = Person::create(['first_name' => 'Kim', 'last_name' => 'Roeier', 'account_id' => $this->user->id]);
});

it('telt alleen ongelezen meldingen van de ingelogde persoon', function () {
    InAppNotification::create(['person_id' => $this->person->id, 'type' => 'x', 'subject' => 'A']);
    $gelezen = InAppNotification::create(['person_id' => $this->person->id, 'type' => 'x', 'subject' => 'B']);
    $gelezen->markAsRead();

    $this->actingAs($this->user);

    Livewire::test(NotificationBell::class)->assertSeeText('1');
});

it('toont 0 en geen dropdown-inhoud voor een user zonder gekoppelde persoon', function () {
    $userZonderPerson = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($userZonderPerson);

    /** @var NotificationBell $instance */
    $instance = Livewire::test(NotificationBell::class)->instance();

    expect($instance->unreadCount())->toBe(0)
        ->and($instance->recent())->toHaveCount(0);
});

it('markeert een melding als gelezen via de bel', function () {
    $notification = InAppNotification::create(['person_id' => $this->person->id, 'type' => 'x', 'subject' => 'X']);

    $this->actingAs($this->user);

    Livewire::test(NotificationBell::class)->call('markAsRead', $notification->id);

    expect($notification->fresh()->read_at)->not->toBeNull();
});
