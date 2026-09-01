<?php

use App\Livewire\Portal\Meldingen;
use App\Models\InAppNotification;
use App\Models\Person;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->person = Person::create(['first_name' => 'Kim', 'last_name' => 'Roeier', 'account_id' => $this->user->id]);
});

it('vereist inloggen', function () {
    $this->get('/mijn/meldingen')->assertRedirect('/login');
});

it('toont alleen meldingen van de ingelogde persoon', function () {
    $ander = Person::create(['first_name' => 'Wim', 'last_name' => 'Wachter']);
    InAppNotification::create(['person_id' => $this->person->id, 'type' => 'x', 'subject' => 'Van mij']);
    InAppNotification::create(['person_id' => $ander->id, 'type' => 'x', 'subject' => 'Van een ander']);

    $this->actingAs($this->user)
        ->get('/mijn/meldingen')
        ->assertOk()
        ->assertSee('Van mij')
        ->assertDontSee('Van een ander');
});

it('markeert een melding als gelezen', function () {
    $notification = InAppNotification::create(['person_id' => $this->person->id, 'type' => 'x', 'subject' => 'X']);

    $this->actingAs($this->user);

    Livewire::test(Meldingen::class)->call('markAsRead', $notification->id);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('kan niet de melding van een ander markeren als gelezen', function () {
    $ander = Person::create(['first_name' => 'Wim', 'last_name' => 'Wachter']);
    $notification = InAppNotification::create(['person_id' => $ander->id, 'type' => 'x', 'subject' => 'X']);

    $this->actingAs($this->user);

    Livewire::test(Meldingen::class)->call('markAsRead', $notification->id);

    expect($notification->fresh()->read_at)->toBeNull();
});

it('markeert alle meldingen in één keer als gelezen', function () {
    InAppNotification::create(['person_id' => $this->person->id, 'type' => 'x', 'subject' => 'A']);
    InAppNotification::create(['person_id' => $this->person->id, 'type' => 'x', 'subject' => 'B']);

    $this->actingAs($this->user);

    Livewire::test(Meldingen::class)->call('markAllAsRead');

    expect(InAppNotification::query()->where('person_id', $this->person->id)->whereNull('read_at')->count())->toBe(0);
});
