<?php

use App\Enums\ProposalStatus;
use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationConstraintType;
use App\Enums\ReservationStatus;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Models\ReservationRule;
use App\Models\Role;
use App\Services\Proposals\Handlers\ReservationProposalHandler;
use App\Services\Proposals\ProposalEngine;
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

    $this->cat = ObjectCategory::create([
        'name' => 'Zaal',
        'slug' => 'zaal',
        'sort_order' => 10,
    ]);

    ReservationRule::create([
        'name' => 'Max 60m',
        'object_category_id' => $this->cat->id,
        'constraint_type' => ReservationConstraintType::Duration,
        'limit_value' => 60,
        'per_person' => true,
    ]);
});

function makeProposalScenario(): array
{
    $cat = ObjectCategory::query()->firstOrFail();
    $obj = ReservableObject::create([
        'object_category_id' => $cat->id,
        'name' => 'Zaal 1',
        'status' => ReservableObjectStatus::Available,
    ]);
    $p = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    // Aanvraag van 2 uur → overschrijdt de 60m-regel → gaat via motor.
    $outcome = app(ReservationSubmissionService::class)->submit(
        $obj, null, $p,
        Carbon::parse('2027-01-01 09:00'),
        Carbon::parse('2027-01-01 11:00'),
        $p,
    );

    return [$outcome->proposal, $obj, $p];
}

it('creëert een InReview-voorstel bij drempeloverschrijding', function () {
    [$proposal] = makeProposalScenario();
    expect($proposal->status)->toBe(ProposalStatus::InReview)
        ->and($proposal->subject_type)->toBe(ReservationProposalHandler::SUBJECT_TYPE);
});

it('past het voorstel toe bij goedkeuring → er ontstaat een bevestigde reservering', function () {
    [$proposal, $obj, $p] = makeProposalScenario();
    $decider = Person::create(['first_name' => 'D', 'last_name' => 'Ecider']);
    // Beheerder-rol geeft alle permissies inclusief reservations.approve (bypass).
    $decider->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $step = $proposal->steps()->firstOrFail();
    app(ProposalEngine::class)->approveStep($step, $decider);

    $fresh = $proposal->fresh();
    expect($fresh->status)->toBe(ProposalStatus::Applied);
    expect(Reservation::query()->where('reservable_object_id', $obj->id)->where('status', ReservationStatus::Confirmed->value)->count())->toBe(1);
});

it('gaat naar Conflicted als het object intussen niet meer beschikbaar is', function () {
    [$proposal, $obj] = makeProposalScenario();
    // Object wordt intussen uit gebruik gehaald.
    $obj->update(['status' => ReservableObjectStatus::OutOfService]);

    $decider = Person::create(['first_name' => 'D', 'last_name' => 'Ecider']);
    $decider->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $step = $proposal->steps()->firstOrFail();
    app(ProposalEngine::class)->approveStep($step, $decider);

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Conflicted);
    expect(Reservation::query()->count())->toBe(0);
});

it('gaat naar Conflicted als er intussen een overlappende bevestigde reservering is', function () {
    [$proposal, $obj] = makeProposalScenario();
    // Iemand anders reserveert in de tussentijd hetzelfde tijdvak direct.
    $ander = Person::create(['first_name' => 'X', 'last_name' => 'Y']);
    Reservation::create([
        'reservable_object_id' => $obj->id,
        'person_id' => $ander->id,
        'requested_by_person_id' => $ander->id,
        'starts_at' => '2027-01-01 10:00',
        'ends_at' => '2027-01-01 10:30',
        'status' => ReservationStatus::Confirmed,
    ]);

    $decider = Person::create(['first_name' => 'D', 'last_name' => 'Ecider']);
    $decider->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $step = $proposal->steps()->firstOrFail();
    app(ProposalEngine::class)->approveStep($step, $decider);

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Conflicted);
});
