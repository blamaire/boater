<?php

use App\Enums\MembershipStatus;
use App\Enums\ProposalStatus;
use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationConstraintType;
use App\Enums\ReservationStatus;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\PersonRelation;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Models\ReservationRule;
use App\Services\Proposals\Handlers\ReservationProposalHandler;
use App\Services\Reservations\ReservationSubmissionService;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewPolicySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MembershipTypeSeeder::class);
    $this->seed(ReviewPolicySeeder::class);

    $this->boten = ObjectCategory::create([
        'name' => 'Boten',
        'slug' => 'boten',
        'sort_order' => 10,
    ]);
    $this->c1 = ObjectCategory::create([
        'name' => 'C1',
        'slug' => 'c1',
        'parent_id' => $this->boten->id,
        'requires_boat_right' => false,
        'sort_order' => 20,
    ]);
    $this->zaal = ObjectCategory::create([
        'name' => 'Zaal',
        'slug' => 'zaal',
        'sort_order' => 40,
    ]);
});

function makeSubObject(int $categoryId, ReservableObjectStatus $status = ReservableObjectStatus::Available, int $sort = 50, string $name = ''): ReservableObject
{
    return ReservableObject::create([
        'object_category_id' => $categoryId,
        'name' => $name !== '' ? $name : 'Object #'.uniqid(),
        'sort_order' => $sort,
        'status' => $status,
    ]);
}

function makeMember(): Person
{
    $p = Person::create(['first_name' => 'Lid', 'last_name' => 'Lidsson']);
    $type = MembershipType::query()->where('key', 'a')->firstOrFail();
    Membership::create([
        'person_id' => $p->id,
        'membership_type_id' => $type->id,
        'status' => MembershipStatus::Active,
        'start_date' => now()->subMonths(2)->toDateString(),
        'billing_person_id' => $p->id,
    ]);

    return $p;
}

it('reserveert direct als er geen regels zijn en het voor jezelf is', function () {
    $obj = makeSubObject($this->zaal->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $result = app(ReservationSubmissionService::class)->submit(
        $obj, null, $p,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 10:00'),
        $p,
    );

    expect($result->reservation)->not->toBeNull()
        ->and($result->reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($result->proposal)->toBeNull();
});

it('routeert via de motor als een regel wordt overschreden', function () {
    ReservationRule::create([
        'name' => 'Max 60m',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::Duration,
        'limit_value' => 60,
        'per_person' => true,
    ]);
    $obj = makeSubObject($this->c1->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $result = app(ReservationSubmissionService::class)->submit(
        $obj, null, $p,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 11:00'),
        $p,
    );

    expect($result->reservation)->toBeNull()
        ->and($result->proposal)->not->toBeNull()
        ->and($result->proposal->subject_type)->toBe(ReservationProposalHandler::SUBJECT_TYPE)
        ->and($result->proposal->status)->toBe(ProposalStatus::InReview)
        ->and($result->violations)->toHaveCount(1);
});

it('routeert via de motor als een aanvraag voor een ander is zonder machtiging', function () {
    $obj = makeSubObject($this->zaal->id);
    $ik = Person::create(['first_name' => 'I', 'last_name' => 'K']);
    $ander = Person::create(['first_name' => 'X', 'last_name' => 'Y']);

    $result = app(ReservationSubmissionService::class)->submit(
        $obj, null, $ander,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 10:00'),
        $ik,
    );

    expect($result->reservation)->toBeNull()
        ->and($result->proposal)->not->toBeNull()
        ->and($result->needsReviewForOther)->toBeTrue();
});

it('reserveert direct als de aanvrager machtiging heeft voor de begunstigde', function () {
    $obj = makeSubObject($this->zaal->id);
    $ouder = Person::create(['first_name' => 'O', 'last_name' => 'Uder']);
    $kind = Person::create(['first_name' => 'K', 'last_name' => 'Ind']);
    PersonRelation::create([
        'person_id' => $ouder->id,
        'related_person_id' => $kind->id,
        'type' => 'ouder_van',
    ]);

    $result = app(ReservationSubmissionService::class)->submit(
        $obj, null, $kind,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 10:00'),
        $ouder,
    );

    expect($result->reservation)->not->toBeNull();
});

it('weigert een aanvraag op een boot-categorie zonder bootrecht meteen (harde invariant)', function () {
    $boot = ObjectCategory::create([
        'name' => 'B',
        'slug' => 'b',
        'parent_id' => $this->boten->id,
        'requires_boat_right' => true,
        'sort_order' => 25,
    ]);
    $obj = makeSubObject($boot->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    expect(fn () => app(ReservationSubmissionService::class)->submit(
        $obj, null, $p,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 10:00'),
        $p,
    ))->toThrow(RuntimeException::class);
});

it('resolveert "beschikbaar van categorie" naar het eerste vrije object in sort_order', function () {
    $obj1 = makeSubObject($this->c1->id, ReservableObjectStatus::Available, 10, 'A');
    $obj2 = makeSubObject($this->c1->id, ReservableObjectStatus::Available, 20, 'B');
    $obj3 = makeSubObject($this->c1->id, ReservableObjectStatus::Available, 30, 'C');
    $p = makeMember();
    // Obj1 en Obj2 zijn bezet in het tijdvak.
    Reservation::create([
        'reservable_object_id' => $obj1->id,
        'person_id' => $p->id,
        'requested_by_person_id' => $p->id,
        'starts_at' => '2027-01-01 09:00',
        'ends_at' => '2027-01-01 10:00',
        'status' => ReservationStatus::Confirmed,
    ]);
    Reservation::create([
        'reservable_object_id' => $obj2->id,
        'person_id' => $p->id,
        'requested_by_person_id' => $p->id,
        'starts_at' => '2027-01-01 09:00',
        'ends_at' => '2027-01-01 10:00',
        'status' => ReservationStatus::Confirmed,
    ]);

    $result = app(ReservationSubmissionService::class)->submit(
        null, $this->c1, $p,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 10:00'),
        $p,
    );

    expect($result->reservation)->not->toBeNull()
        ->and($result->reservation->reservable_object_id)->toBe($obj3->id);
});

it('gooit een fout als er geen enkel object beschikbaar is in de gekozen categorie/tijdvak', function () {
    $p = makeMember();
    $obj = makeSubObject($this->c1->id, ReservableObjectStatus::OutOfService);

    expect(fn () => app(ReservationSubmissionService::class)->submit(
        null, $this->c1, $p,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 10:00'),
        $p,
    ))->toThrow(RuntimeException::class);
});
