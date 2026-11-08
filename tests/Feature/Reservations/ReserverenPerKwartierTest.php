<?php

use App\Enums\ReservationStatus;
use App\Livewire\Portal\Reserveren;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Models\User;
use App\Rules\OpKwartier;
use Illuminate\Support\Carbon;
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

/** @return array{0: User, 1: Person, 2: ReservableObject, 3: ReservableObject} */
function kwartierScenario(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'Roos', 'last_name' => 'Roeier', 'account_id' => $user->id]);
    $category = ObjectCategory::create(['name' => 'Skiffs', 'slug' => 'skiffs', 'sort_order' => 0]);

    return [
        $user,
        $person,
        ReservableObject::create(['object_category_id' => $category->id, 'name' => 'Skiff 1', 'status' => 'beschikbaar']),
        ReservableObject::create(['object_category_id' => $category->id, 'name' => 'Skiff 2', 'status' => 'beschikbaar']),
    ];
}

it('kiest in het rooster eerst het begin en dan het einde per kwartier', function () {
    [$user, , $boat] = kwartierScenario();

    Livewire::actingAs($user)
        ->test(Reserveren::class)
        ->set('viewDate', '2027-03-01')
        ->assertSeeHtml('aria-label="Skiff 1 09:45"')
        ->call('pickSlot', $boat->id, '2027-03-01T09:45:00')
        ->assertSet('startsAt', '2027-03-01T09:45')
        ->assertSet('awaitingEnd', true)
        // Reserveringsregel staat er al vanaf het begin, Reserveren nog uitgeschakeld.
        ->assertSee('Opmerking (optioneel)', false)
        ->assertSeeHtml('disabled')
        // Voorvertoning onder de muis (Alpine) zolang het einde nog gekozen moet worden.
        ->assertSeeHtml('@mouseenter="hover = ')
        // Geen hulpteksten bij het grafisch selecteren.
        ->assertDontSeeHtml('Klik om te')
        ->call('pickSlot', $boat->id, '2027-03-01T11:15:00')
        ->assertSet('endsAt', '2027-03-01T11:30')
        ->assertSet('awaitingEnd', false)
        ->assertSet('selectionComplete', true)
        ->assertSeeHtml('selectie-arcering')
        ->assertDontSeeHtml('@mouseenter="hover = ');
});

it('negeert tijdens een selectie kliks in een andere rij of vóór het begin', function () {
    [$user, , $boat1, $boat2] = kwartierScenario();

    Livewire::actingAs($user)
        ->test(Reserveren::class)
        ->call('pickSlot', $boat1->id, '2027-03-01T10:00:00')
        ->call('pickSlot', $boat2->id, '2027-03-01T11:00:00')
        ->call('pickSlot', $boat1->id, '2027-03-01T09:00:00')
        ->assertSet('selectedObjectId', $boat1->id)
        ->assertSet('startsAt', '2027-03-01T10:00')
        ->assertSet('endsAt', '2027-03-01T10:15')
        ->assertSet('awaitingEnd', true);
});

it('laat het einde niet voorbij de eerstvolgende reservering vallen', function () {
    [$user, $person, $boat] = kwartierScenario();
    Reservation::create([
        'reservable_object_id' => $boat->id,
        'person_id' => $person->id,
        'requested_by_person_id' => $person->id,
        'starts_at' => Carbon::parse('2027-03-01 11:00'),
        'ends_at' => Carbon::parse('2027-03-01 12:00'),
        'status' => ReservationStatus::Confirmed,
    ]);

    Livewire::actingAs($user)
        ->test(Reserveren::class)
        ->call('pickSlot', $boat->id, '2027-03-01T09:00:00')
        ->call('pickSlot', $boat->id, '2027-03-01T11:15:00')
        ->assertSet('awaitingEnd', true)
        ->call('pickSlot', $boat->id, '2027-03-01T10:45:00')
        ->assertSet('endsAt', '2027-03-01T11:00')
        ->assertSet('selectionComplete', true);
});

it('toont de opmerking in mijn reserveringen en in de tooltip van het rooster', function () {
    [$user, $person, $boat] = kwartierScenario();
    $start = Carbon::now()->addDay()->setTime(9, 0);
    Reservation::create([
        'reservable_object_id' => $boat->id,
        'person_id' => $person->id,
        'requested_by_person_id' => $person->id,
        'starts_at' => $start,
        'ends_at' => $start->copy()->addHour(),
        'status' => ReservationStatus::Confirmed,
        'note' => 'Training voor de Head',
    ]);

    Livewire::actingAs($user)
        ->test(Reserveren::class)
        ->set('viewDate', $start->toDateString())
        ->assertSeeHtml('<div class="mt-0.5 text-xs text-gray-600 italic">Training voor de Head</div>')
        ->assertSeeHtml('09:00–10:00 · Training voor de Head');
});

it('maakt de selectie ongedaan met het kruisje', function () {
    [$user, , $boat] = kwartierScenario();

    Livewire::actingAs($user)
        ->test(Reserveren::class)
        ->call('pickSlot', $boat->id, '2027-03-01T09:00:00')
        ->call('pickSlot', $boat->id, '2027-03-01T09:45:00')
        ->call('cancelGridSelection')
        ->assertSet('awaitingEnd', false)
        ->assertSet('selectionComplete', false)
        ->assertSet('selectedObjectId', null);
});

it('slaat de reservering met opmerking op via het vinkje', function () {
    [$user, $person, $boat] = kwartierScenario();
    $start = Carbon::now()->addDay()->setTime(9, 0);

    Livewire::actingAs($user)
        ->test(Reserveren::class)
        ->call('pickSlot', $boat->id, $start->toIso8601String())
        ->call('pickSlot', $boat->id, $start->copy()->addMinutes(30)->toIso8601String())
        ->set('note', 'Training voor de Head')
        ->call('confirmGridReservation')
        ->assertHasNoErrors()
        ->assertSet('selectionComplete', false);

    $reservation = Reservation::query()->firstOrFail();
    expect($reservation->reservable_object_id)->toBe($boat->id)
        ->and($reservation->person_id)->toBe($person->id)
        ->and($reservation->starts_at->format('H:i'))->toBe('09:00')
        ->and($reservation->ends_at->format('H:i'))->toBe('09:45')
        ->and($reservation->note)->toBe('Training voor de Head');
});

it('weigert een reservering die niet op een kwartier begint', function () {
    [$user] = kwartierScenario();

    Livewire::actingAs($user)
        ->test(Reserveren::class)
        ->set('startsAt', '2027-03-01T09:10')
        ->set('endsAt', '2027-03-01T10:00')
        ->call('reserve')
        ->assertHasErrors(['startsAt']);
});
