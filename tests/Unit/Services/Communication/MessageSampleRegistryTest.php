<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Enums\ContactRequestStatus;
use App\Enums\DamageReportStatus;
use App\Enums\DamageSeverity;
use App\Enums\EnrollmentLevel;
use App\Enums\EnrollmentStatus;
use App\Enums\ReservableObjectStatus;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ContactRequest;
use App\Models\ContactTopic;
use App\Models\DamageReport;
use App\Models\Enrollment;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Services\Communication\MessageSampleRegistry;
use Illuminate\Support\Carbon;

it('ondersteunt alleen sleutels met een reëel voorbeeld-model', function () {
    $registry = app(MessageSampleRegistry::class);

    expect($registry->supports('damage_report_submitted'))->toBeTrue()
        ->and($registry->supports('contact_request_submitted'))->toBeTrue()
        ->and($registry->supports('activity_changed'))->toBeTrue()
        ->and($registry->supports('activity_enrollment_changed'))->toBeTrue()
        ->and($registry->supports('enrollment_confirmed'))->toBeTrue()
        ->and($registry->supports('enrollment_waitlisted'))->toBeTrue()
        ->and($registry->supports('password_reset'))->toBeFalse()
        ->and($registry->supports('email_verification'))->toBeFalse()
        ->and($registry->supports('account_invitation'))->toBeFalse()
        ->and($registry->supports('membership_application_received'))->toBeFalse();
});

it('geeft null als een ondersteunde sleutel nog geen enkel record heeft', function () {
    $registry = app(MessageSampleRegistry::class);

    expect($registry->sampleVariables('damage_report_submitted'))->toBeNull()
        ->and($registry->sampleVariables('contact_request_submitted'))->toBeNull()
        ->and($registry->sampleVariables('activity_changed'))->toBeNull()
        ->and($registry->sampleVariables('activity_enrollment_changed'))->toBeNull()
        ->and($registry->sampleVariables('enrollment_confirmed'))->toBeNull()
        ->and($registry->sampleVariables('enrollment_waitlisted'))->toBeNull();
});

it('geeft null voor een niet-ondersteunde sleutel, ook als er wel data bestaat', function () {
    $registry = app(MessageSampleRegistry::class);

    expect($registry->sampleVariables('password_reset'))->toBeNull();
});

it('bouwt voorbeeld-variabelen op uit een bestaande schademelding', function () {
    $category = ObjectCategory::create(['name' => 'Boten', 'slug' => 'boten', 'requires_boat_right' => false, 'sort_order' => 10]);
    $object = ReservableObject::create(['object_category_id' => $category->id, 'name' => 'Skiff #1', 'status' => ReservableObjectStatus::Available]);
    $reporter = Person::create(['first_name' => 'Mel', 'last_name' => 'Der']);
    DamageReport::create([
        'reservable_object_id' => $object->id,
        'reported_by_person_id' => $reporter->id,
        'description' => 'Gat in de romp.',
        'severity' => DamageSeverity::High,
        'reporter_marked_unusable' => true,
        'status' => DamageReportStatus::Reported,
        'reported_at' => Carbon::now(),
    ]);

    $variables = app(MessageSampleRegistry::class)->sampleVariables('damage_report_submitted');

    expect($variables)->not->toBeNull()
        ->and($variables['{{object}}'])->toBe('Skiff #1')
        ->and($variables['{{melder}}'])->toBe('Mel Der')
        ->and($variables['{{ernst}}'])->toBe(DamageSeverity::High->label())
        ->and($variables['{{niet_bruikbaar_notice}}'])->toContain('niet bruikbaar');
});

it('bouwt voorbeeld-variabelen op uit een bestaand contactverzoek', function () {
    $responsible = Person::create(['first_name' => 'Ans', 'last_name' => 'Woord', 'email' => 'ans@example.test']);
    $topic = ContactTopic::create(['name' => 'Lidmaatschap', 'responsible_person_id' => $responsible->id, 'sort_order' => 10]);
    ContactRequest::create([
        'contact_topic_id' => $topic->id,
        'name' => 'Jan Bezoeker',
        'phone' => '0612345678',
        'contact_by_phone' => true,
        'contact_by_email' => false,
        'message' => 'Ik heb een vraag.',
        'status' => ContactRequestStatus::Nieuw,
    ]);

    $variables = app(MessageSampleRegistry::class)->sampleVariables('contact_request_submitted');

    expect($variables)->not->toBeNull()
        ->and($variables['{{onderwerp}}'])->toBe('Lidmaatschap')
        ->and($variables['{{naam}}'])->toBe('Jan Bezoeker')
        ->and($variables['{{bericht}}'])->toBe('Ik heb een vraag.');
});

it('bouwt voorbeeld-variabelen op uit een bestaande activiteit', function () {
    $category = ActivityCategory::create(['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10]);
    Activity::create([
        'activity_category_id' => $category->id,
        'title' => 'Zaterdagochtendroei',
        'starts_at' => Carbon::parse('2026-09-05 09:00'),
        'visibility' => ActivityVisibility::Public,
        'status' => ActivityStatus::Published,
    ]);

    $variables = app(MessageSampleRegistry::class)->sampleVariables('activity_changed');

    expect($variables)->not->toBeNull()
        ->and($variables['{{titel}}'])->toBe('Zaterdagochtendroei');
});

it('bouwt voorbeeld-variabelen op uit een bestaande inschrijving voor activity_enrollment_changed', function () {
    $category = ActivityCategory::create(['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10]);
    $activity = Activity::create([
        'activity_category_id' => $category->id,
        'title' => 'Zaterdagochtendroei',
        'starts_at' => Carbon::parse('2026-09-05 09:00'),
        'visibility' => ActivityVisibility::Public,
        'status' => ActivityStatus::Published,
    ]);
    $person = Person::create(['first_name' => 'Kim', 'last_name' => 'Roeier']);
    Enrollment::create([
        'activity_id' => $activity->id,
        'person_id' => $person->id,
        'level' => EnrollmentLevel::Bundel,
        'status' => EnrollmentStatus::Enrolled,
        'enrolled_at' => Carbon::now(),
    ]);

    $variables = app(MessageSampleRegistry::class)->sampleVariables('activity_enrollment_changed');

    expect($variables)->not->toBeNull()
        ->and($variables['{{persoon}}'])->toBe('Kim Roeier')
        ->and($variables['{{onderwerp_actie}}'])->toBe('Nieuwe inschrijving');
});

it('bouwt voorbeeld-variabelen op uit een bevestigde resp. wachtlijst-inschrijving', function () {
    $category = ActivityCategory::create(['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10]);
    $activity = Activity::create([
        'activity_category_id' => $category->id,
        'title' => 'Zaterdagochtendroei',
        'starts_at' => Carbon::parse('2026-09-05 09:00'),
        'visibility' => ActivityVisibility::Public,
        'status' => ActivityStatus::Published,
    ]);
    $enrolled = Person::create(['first_name' => 'Kim', 'last_name' => 'Roeier']);
    $waitlisted = Person::create(['first_name' => 'Wim', 'last_name' => 'Wachter']);

    Enrollment::create([
        'activity_id' => $activity->id, 'person_id' => $enrolled->id,
        'level' => EnrollmentLevel::Bundel, 'status' => EnrollmentStatus::Enrolled, 'enrolled_at' => Carbon::now(),
    ]);
    Enrollment::create([
        'activity_id' => $activity->id, 'person_id' => $waitlisted->id,
        'level' => EnrollmentLevel::Bundel, 'status' => EnrollmentStatus::Waitlist, 'enrolled_at' => Carbon::now(),
    ]);

    $registry = app(MessageSampleRegistry::class);

    expect($registry->sampleVariables('enrollment_confirmed')['{{voornaam}}'])->toBe('Kim')
        ->and($registry->sampleVariables('enrollment_waitlisted')['{{voornaam}}'])->toBe('Wim');
});
