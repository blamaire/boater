<?php

use App\Enums\MembershipStatus;
use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationStatus;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Services\Reservations\ReservationService;
use Database\Seeders\MembershipTypeSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(MembershipTypeSeeder::class);
    $this->boatCategory = ObjectCategory::create([
        'name' => 'C1',
        'slug' => 'c1',
        'requires_boat_right' => true,
        'sort_order' => 10,
    ]);
    $this->genericCategory = ObjectCategory::create([
        'name' => 'Zaal',
        'slug' => 'zaal',
        'requires_boat_right' => false,
        'sort_order' => 20,
    ]);
});

function makeObject(int $categoryId, ReservableObjectStatus $status = ReservableObjectStatus::Available): ReservableObject
{
    return ReservableObject::create([
        'object_category_id' => $categoryId,
        'name' => 'Object #'.uniqid(),
        'status' => $status,
    ]);
}

function makeMemberWithBoatRight(): Person
{
    $person = Person::create(['first_name' => 'Boot', 'last_name' => 'Roeier']);
    $type = MembershipType::where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $person->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subMonths(2),
        'billing_person_id' => $person->id,
    ]);

    return $person;
}

it('creëert een bevestigde reservering voor een beschikbaar object', function () {
    $object = makeObject($this->genericCategory->id);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $reservation = app(ReservationService::class)->reserve(
        $object,
        $person,
        Carbon::now()->addHour(),
        Carbon::now()->addHours(2),
    );

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->person_id)->toBe($person->id);
});

it('weigert een reservering met eindtijd voor of gelijk aan de starttijd', function () {
    $object = makeObject($this->genericCategory->id);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $start = Carbon::now()->addHour();
    expect(fn () => app(ReservationService::class)->reserve($object, $person, $start, $start))
        ->toThrow(RuntimeException::class);
});

it('weigert een reservering op een object dat buiten gebruik staat', function () {
    $object = makeObject($this->genericCategory->id, ReservableObjectStatus::OutOfService);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    expect(fn () => app(ReservationService::class)->reserve(
        $object,
        $person,
        Carbon::now()->addHour(),
        Carbon::now()->addHours(2),
    ))->toThrow(RuntimeException::class);
});

it('weigert dubbelboeking op hetzelfde object in overlappend tijdvak (invariant 1)', function () {
    $object = makeObject($this->genericCategory->id);
    $a = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    $b = Person::create(['first_name' => 'B', 'last_name' => 'C']);

    app(ReservationService::class)->reserve(
        $object,
        $a,
        Carbon::now()->addHour(),
        Carbon::now()->addHours(3),
    );

    expect(fn () => app(ReservationService::class)->reserve(
        $object,
        $b,
        Carbon::now()->addHours(2),
        Carbon::now()->addHours(4),
    ))->toThrow(RuntimeException::class);
});

it('staat aansluitende reserveringen zonder overlap gewoon toe', function () {
    $object = makeObject($this->genericCategory->id);
    $a = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    $b = Person::create(['first_name' => 'B', 'last_name' => 'C']);

    app(ReservationService::class)->reserve(
        $object,
        $a,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 10:00'),
    );

    $second = app(ReservationService::class)->reserve(
        $object,
        $b,
        Carbon::parse('2027-01-01 10:00'),
        Carbon::parse('2027-01-01 11:00'),
    );

    expect($second->status)->toBe(ReservationStatus::Confirmed);
});

it('staat een dubbelboeking op een geannuleerde eerdere reservering toe', function () {
    $object = makeObject($this->genericCategory->id);
    $a = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    $b = Person::create(['first_name' => 'B', 'last_name' => 'C']);

    $first = app(ReservationService::class)->reserve(
        $object,
        $a,
        Carbon::now()->addHour(),
        Carbon::now()->addHours(3),
    );
    app(ReservationService::class)->cancel($first);

    $second = app(ReservationService::class)->reserve(
        $object,
        $b,
        Carbon::now()->addHours(2),
        Carbon::now()->addHours(4),
    );

    expect($second->status)->toBe(ReservationStatus::Confirmed);
});

it('weigert reservering van een boot-categorie voor iemand zonder botengebruik-recht (invariant 2)', function () {
    $object = makeObject($this->boatCategory->id);
    $person = Person::create(['first_name' => 'Zonder', 'last_name' => 'Rechten']);

    expect(fn () => app(ReservationService::class)->reserve(
        $object,
        $person,
        Carbon::now()->addHour(),
        Carbon::now()->addHours(2),
    ))->toThrow(RuntimeException::class);
});

it('laat een A-lid met botengebruik-recht een boot reserveren', function () {
    $object = makeObject($this->boatCategory->id);
    $person = makeMemberWithBoatRight();

    $reservation = app(ReservationService::class)->reserve(
        $object,
        $person,
        Carbon::now()->addHour(),
        Carbon::now()->addHours(2),
    );

    expect($reservation->status)->toBe(ReservationStatus::Confirmed);
});
