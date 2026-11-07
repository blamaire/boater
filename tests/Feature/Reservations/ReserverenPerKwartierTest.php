<?php

use App\Livewire\Portal\Reserveren;
use App\Models\ObjectCategory;
use App\Models\ReservableObject;
use App\Models\User;
use App\Rules\OpKwartier;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

it('accepteert alleen tijden op een kwartier', function (string $value, bool $valid) {
    $validator = Validator::make(['t' => $value], ['t' => [new OpKwartier]]);

    expect($validator->passes())->toBe($valid);
})->with([
    'heel uur' => ['2027-03-01T09:00', true],
    'kwart over' => ['2027-03-01T09:15', true],
    'half' => ['2027-03-01T09:30', true],
    'kwart voor' => ['2027-03-01T09:45', true],
    'tien over' => ['2027-03-01T09:10', false],
    'met seconden' => ['2027-03-01 09:15:30', false],
]);

function kwartierBoten(): array
{
    $category = ObjectCategory::create(['name' => 'Skiffs', 'slug' => 'skiffs', 'sort_order' => 0]);

    return [
        ReservableObject::create(['object_category_id' => $category->id, 'name' => 'Skiff 1', 'status' => 'beschikbaar']),
        ReservableObject::create(['object_category_id' => $category->id, 'name' => 'Skiff 2', 'status' => 'beschikbaar']),
    ];
}

it('toont het rooster per kwartier en kiest met twee kliks begin en einde', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    [$boat] = kwartierBoten();

    Livewire::actingAs($user)
        ->test(Reserveren::class)
        ->set('viewDate', '2027-03-01')
        ->assertSeeHtml('Klik om te beginnen om 09:45')
        // Eerste klik: begin, voorlopig één kwartier.
        ->call('pickSlot', $boat->id, '2027-03-01T09:45:00+01:00')
        ->assertSet('startsAt', '2027-03-01T09:45')
        ->assertSet('endsAt', '2027-03-01T10:00')
        ->assertSet('awaitingEnd', true)
        ->assertSee('klik nu op het eindtijdstip')
        // Tweede klik: einde tot en met het aangeklikte kwartier.
        ->call('pickSlot', $boat->id, '2027-03-01T11:15:00+01:00')
        ->assertSet('startsAt', '2027-03-01T09:45')
        ->assertSet('endsAt', '2027-03-01T11:30')
        ->assertSet('awaitingEnd', false);
});

it('begint een nieuwe selectie bij een klik in een andere rij of vóór het begin', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    [$boat1, $boat2] = kwartierBoten();

    Livewire::actingAs($user)
        ->test(Reserveren::class)
        ->call('pickSlot', $boat1->id, '2027-03-01T10:00:00+01:00')
        ->call('pickSlot', $boat2->id, '2027-03-01T11:00:00+01:00')
        ->assertSet('selectedObjectId', $boat2->id)
        ->assertSet('startsAt', '2027-03-01T11:00')
        ->assertSet('awaitingEnd', true)
        ->call('pickSlot', $boat2->id, '2027-03-01T09:00:00+01:00')
        ->assertSet('startsAt', '2027-03-01T09:00')
        ->assertSet('endsAt', '2027-03-01T09:15')
        ->assertSet('awaitingEnd', true);
});

it('weigert een reservering die niet op een kwartier begint', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test(Reserveren::class)
        ->set('startsAt', '2027-03-01T09:10')
        ->set('endsAt', '2027-03-01T10:00')
        ->call('reserve')
        ->assertHasErrors(['startsAt']);
});
