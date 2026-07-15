<?php

use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationConstraintType;
use App\Enums\ReservationStatus;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Models\ReservationRule;
use App\Services\Reservations\ReservationRuleEvaluator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->boten = ObjectCategory::create([
        'name' => 'Boten',
        'slug' => 'boten',
        'sort_order' => 10,
    ]);
    $this->c1 = ObjectCategory::create([
        'name' => 'C1',
        'slug' => 'c1',
        'parent_id' => $this->boten->id,
        'sort_order' => 20,
    ]);
    $this->c2 = ObjectCategory::create([
        'name' => 'C2',
        'slug' => 'c2',
        'parent_id' => $this->boten->id,
        'sort_order' => 30,
    ]);
});

function makeObj(int $categoryId): ReservableObject
{
    return ReservableObject::create([
        'object_category_id' => $categoryId,
        'name' => 'Object #'.uniqid(),
        'status' => ReservableObjectStatus::Available,
    ]);
}

function makeConfirmed(ReservableObject $obj, Person $for, Carbon $start, Carbon $end): Reservation
{
    return Reservation::create([
        'reservable_object_id' => $obj->id,
        'person_id' => $for->id,
        'requested_by_person_id' => $for->id,
        'starts_at' => $start,
        'ends_at' => $end,
        'status' => ReservationStatus::Confirmed,
    ]);
}

it('geeft geen violations bij een lege regelset', function () {
    $obj = makeObj($this->c1->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $result = app(ReservationRuleEvaluator::class)->evaluate(
        $obj, $p,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 10:00'),
    );

    expect($result)->toHaveCount(0);
});

it('overtreedt duur-regel als de aanvraag langer is dan limit_value', function () {
    ReservationRule::create([
        'name' => 'Max 2 uur per rit',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::Duration,
        'limit_value' => 120,
        'per_person' => true,
    ]);
    $obj = makeObj($this->c1->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $result = app(ReservationRuleEvaluator::class)->evaluate(
        $obj, $p,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 12:00'),
    );

    expect($result)->toHaveCount(1);
    expect($result[0]->rule->limit_value)->toBe(120);
});

it('accepteert duur exact op de grens', function () {
    ReservationRule::create([
        'name' => 'Max 60m',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::Duration,
        'limit_value' => 60,
        'per_person' => true,
    ]);
    $obj = makeObj($this->c1->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $result = app(ReservationRuleEvaluator::class)->evaluate(
        $obj, $p,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 10:00'),
    );

    expect($result)->toHaveCount(0);
});

it('overtreedt gelijktijdig-regel per persoon als lid al een lopende reservering heeft', function () {
    ReservationRule::create([
        'name' => 'Max 1 boot gelijktijdig per lid',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::Simultaneous,
        'limit_value' => 1,
        'per_person' => true,
    ]);
    $obj1 = makeObj($this->c1->id);
    $obj2 = makeObj($this->c2->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    makeConfirmed($obj1, $p, Carbon::parse('2027-01-01 09:00'), Carbon::parse('2027-01-01 11:00'));

    $result = app(ReservationRuleEvaluator::class)->evaluate(
        $obj2, $p,
        Carbon::parse('2027-01-01 10:00'),
        Carbon::parse('2027-01-01 12:00'),
    );

    expect($result)->toHaveCount(1);
});

it('gelijktijdig-regel per persoon negeert reserveringen van anderen', function () {
    ReservationRule::create([
        'name' => 'Max 1 gelijktijdig per lid',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::Simultaneous,
        'limit_value' => 1,
        'per_person' => true,
    ]);
    $obj = makeObj($this->c1->id);
    $ander = Person::create(['first_name' => 'X', 'last_name' => 'Y']);
    $ik = Person::create(['first_name' => 'I', 'last_name' => 'K']);

    makeConfirmed($obj, $ander, Carbon::parse('2027-01-01 09:00'), Carbon::parse('2027-01-01 11:00'));
    // Andere boot in subcategorie, overlappend maar door iemand anders.
    $obj2 = makeObj($this->c2->id);
    makeConfirmed($obj2, $ander, Carbon::parse('2027-01-01 09:00'), Carbon::parse('2027-01-01 11:00'));

    $result = app(ReservationRuleEvaluator::class)->evaluate(
        makeObj($this->c1->id), $ik,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 11:00'),
    );

    expect($result)->toHaveCount(0);
});

it('gelijktijdig-regel per_person=false telt reserveringen van iedereen op', function () {
    ReservationRule::create([
        'name' => 'Max 2 boten gelijktijdig totaal',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::Simultaneous,
        'limit_value' => 2,
        'per_person' => false,
    ]);
    $ander1 = Person::create(['first_name' => 'A', 'last_name' => 'A']);
    $ander2 = Person::create(['first_name' => 'B', 'last_name' => 'B']);
    $ik = Person::create(['first_name' => 'I', 'last_name' => 'K']);

    makeConfirmed(makeObj($this->c1->id), $ander1, Carbon::parse('2027-01-01 09:00'), Carbon::parse('2027-01-01 11:00'));
    makeConfirmed(makeObj($this->c1->id), $ander2, Carbon::parse('2027-01-01 09:00'), Carbon::parse('2027-01-01 11:00'));

    $result = app(ReservationRuleEvaluator::class)->evaluate(
        makeObj($this->c2->id), $ik,
        Carbon::parse('2027-01-01 10:00'),
        Carbon::parse('2027-01-01 12:00'),
    );

    expect($result)->toHaveCount(1);
});

it('per_dag-regel telt reserveringen op dezelfde kalenderdag', function () {
    ReservationRule::create([
        'name' => 'Max 1 boottrip per dag',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::PerDay,
        'limit_value' => 1,
        'per_person' => true,
    ]);
    $obj1 = makeObj($this->c1->id);
    $obj2 = makeObj($this->c2->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    makeConfirmed($obj1, $p, Carbon::parse('2027-01-01 07:00'), Carbon::parse('2027-01-01 08:00'));

    $result = app(ReservationRuleEvaluator::class)->evaluate(
        $obj2, $p,
        Carbon::parse('2027-01-01 20:00'),
        Carbon::parse('2027-01-01 21:00'),
    );

    expect($result)->toHaveCount(1);
});

it('per_dag-regel geldt niet voor de volgende dag', function () {
    ReservationRule::create([
        'name' => 'Max 1 boottrip per dag',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::PerDay,
        'limit_value' => 1,
        'per_person' => true,
    ]);
    $obj = makeObj($this->c1->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    makeConfirmed($obj, $p, Carbon::parse('2027-01-01 07:00'), Carbon::parse('2027-01-01 08:00'));

    $result = app(ReservationRuleEvaluator::class)->evaluate(
        $obj, $p,
        Carbon::parse('2027-01-02 07:00'),
        Carbon::parse('2027-01-02 08:00'),
    );

    expect($result)->toHaveCount(0);
});

it('erft een regel op een parent-categorie ook op reserveringen in een subcategorie (§18.4)', function () {
    ReservationRule::create([
        'name' => 'Max 60m per rit',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::Duration,
        'limit_value' => 60,
        'per_person' => true,
    ]);
    // Object zit in subcategorie C1 (Boten → C1).
    $obj = makeObj($this->c1->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $result = app(ReservationRuleEvaluator::class)->evaluate(
        $obj, $p,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 11:00'),
    );

    expect($result)->toHaveCount(1);
});

it('rapporteert meerdere overtredingen tegelijk (regels zijn additief)', function () {
    ReservationRule::create([
        'name' => 'Max 60m',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::Duration,
        'limit_value' => 60,
        'per_person' => true,
    ]);
    ReservationRule::create([
        'name' => 'Max 1 per dag',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::PerDay,
        'limit_value' => 1,
        'per_person' => true,
    ]);
    $obj = makeObj($this->c1->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    makeConfirmed($obj, $p, Carbon::parse('2027-01-01 07:00'), Carbon::parse('2027-01-01 08:00'));

    $result = app(ReservationRuleEvaluator::class)->evaluate(
        $obj, $p,
        Carbon::parse('2027-01-01 10:00'),
        Carbon::parse('2027-01-01 12:00'),
    );

    expect($result)->toHaveCount(2);
});

it('sluit de gegeven exclude_reservation_id uit bij hertelling (voor edits)', function () {
    ReservationRule::create([
        'name' => 'Max 1 per dag',
        'object_category_id' => $this->boten->id,
        'constraint_type' => ReservationConstraintType::PerDay,
        'limit_value' => 1,
        'per_person' => true,
    ]);
    $obj = makeObj($this->c1->id);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    $existing = makeConfirmed($obj, $p, Carbon::parse('2027-01-01 07:00'), Carbon::parse('2027-01-01 08:00'));

    // Zonder exclude: overtreding.
    $violating = app(ReservationRuleEvaluator::class)->evaluate(
        $obj, $p,
        Carbon::parse('2027-01-01 10:00'),
        Carbon::parse('2027-01-01 11:00'),
    );
    expect($violating)->toHaveCount(1);

    // Met exclude van de eigen: geen overtreding meer.
    $ok = app(ReservationRuleEvaluator::class)->evaluate(
        $obj, $p,
        Carbon::parse('2027-01-01 10:00'),
        Carbon::parse('2027-01-01 11:00'),
        excludeReservationId: $existing->id,
    );
    expect($ok)->toHaveCount(0);
});
