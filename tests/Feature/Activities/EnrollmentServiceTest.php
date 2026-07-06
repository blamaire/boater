<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Enums\EnrollmentStatus;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Enrollment;
use App\Models\Person;
use App\Services\Activities\EnrollmentService;

beforeEach(function () {
    $this->category = ActivityCategory::create(['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10]);
});

function newActivity(int $categoryId, ?int $capacity = null, ActivityStatus $status = ActivityStatus::Published): Activity
{
    return Activity::create([
        'activity_category_id' => $categoryId,
        'title' => 'Test',
        'starts_at' => now()->addDays(3),
        'capacity' => $capacity,
        'visibility' => ActivityVisibility::Members,
        'status' => $status,
    ]);
}

it('schrijft een persoon in als aangemeld als er plek is', function () {
    $activity = newActivity($this->category->id, capacity: 2);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $enrollment = app(EnrollmentService::class)->enroll($activity, $person);

    expect($enrollment->status)->toBe(EnrollmentStatus::Enrolled);
});

it('zet nieuwe inschrijvingen op wachtlijst als de capaciteit vol is', function () {
    $activity = newActivity($this->category->id, capacity: 1);

    $a = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    $b = Person::create(['first_name' => 'B', 'last_name' => 'C']);

    app(EnrollmentService::class)->enroll($activity, $a);
    $second = app(EnrollmentService::class)->enroll($activity, $b);

    expect($second->status)->toBe(EnrollmentStatus::Waitlist);
});

it('promoveert de eerste wachtende bij een afmelding', function () {
    $activity = newActivity($this->category->id, capacity: 1);

    $a = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    $b = Person::create(['first_name' => 'B', 'last_name' => 'C']);

    $eA = app(EnrollmentService::class)->enroll($activity, $a);
    $eB = app(EnrollmentService::class)->enroll($activity, $b);

    expect($eB->status)->toBe(EnrollmentStatus::Waitlist);

    app(EnrollmentService::class)->cancel($eA);

    $eB->refresh();
    expect($eB->status)->toBe(EnrollmentStatus::Enrolled);
});

it('promoveert niemand bij onbeperkte capaciteit', function () {
    $activity = newActivity($this->category->id, capacity: null);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    $enrollment = app(EnrollmentService::class)->enroll($activity, $person);

    expect($enrollment->status)->toBe(EnrollmentStatus::Enrolled);
});

it('weigert dubbele actieve inschrijving voor dezelfde persoon', function () {
    $activity = newActivity($this->category->id, capacity: 5);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    app(EnrollmentService::class)->enroll($activity, $person);

    expect(fn () => app(EnrollmentService::class)->enroll($activity, $person))
        ->toThrow(RuntimeException::class);
});

it('staat opnieuw inschrijven toe na een eerdere afmelding', function () {
    $activity = newActivity($this->category->id, capacity: 5);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $enrollment = app(EnrollmentService::class)->enroll($activity, $person);
    app(EnrollmentService::class)->cancel($enrollment);

    $enrollment2 = app(EnrollmentService::class)->enroll($activity, $person);
    expect($enrollment2->id)->toBe($enrollment->id)
        ->and($enrollment2->status)->toBe(EnrollmentStatus::Enrolled)
        ->and(Enrollment::query()->count())->toBe(1);
});

it('weigert inschrijving op een niet-gepubliceerde activiteit', function () {
    $activity = newActivity($this->category->id, status: ActivityStatus::Cancelled);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    expect(fn () => app(EnrollmentService::class)->enroll($activity, $person))
        ->toThrow(RuntimeException::class);
});
