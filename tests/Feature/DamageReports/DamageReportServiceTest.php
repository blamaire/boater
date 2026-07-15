<?php

use App\Enums\DamageReportStatus;
use App\Enums\DamageSeverity;
use App\Enums\ReservableObjectStatus;
use App\Enums\ReservationStatus;
use App\Models\AuditEntry;
use App\Models\CategoryResponsible;
use App\Models\DamageReport;
use App\Models\ObjectCategory;
use App\Models\Person;
use App\Models\ReservableObject;
use App\Models\Reservation;
use App\Notifications\DamageReportSubmitted;
use App\Services\DamageReports\DamageReportService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->parent = ObjectCategory::create([
        'name' => 'Boten',
        'slug' => 'boten',
        'requires_boat_right' => false,
        'sort_order' => 10,
    ]);
    $this->child = ObjectCategory::create([
        'name' => 'C1',
        'slug' => 'c1',
        'parent_id' => $this->parent->id,
        'requires_boat_right' => true,
        'sort_order' => 20,
    ]);
    $this->reporter = Person::create(['first_name' => 'Mel', 'last_name' => 'Der', 'email' => 'melder@example.test']);
});

function makeReservableObject(int $categoryId, ReservableObjectStatus $status = ReservableObjectStatus::Available): ReservableObject
{
    return ReservableObject::create([
        'object_category_id' => $categoryId,
        'name' => 'Object #'.uniqid(),
        'status' => $status,
    ]);
}

it('maakt een melding aan met status gemeld en logt de tijdstempel', function () {
    Notification::fake();
    $object = makeReservableObject($this->child->id);

    $report = app(DamageReportService::class)->submit(
        object: $object,
        reporter: $this->reporter,
        description: 'Er zit een gat in de romp.',
        severity: DamageSeverity::High,
        reporterMarkedUnusable: false,
        photos: collect(),
    );

    expect($report->status)->toBe(DamageReportStatus::Reported)
        ->and($report->reported_at)->not->toBeNull()
        ->and($report->reservable_object_id)->toBe($object->id);
});

it('zet het object direct op buiten gebruik wanneer melder niet-bruikbaar aankruist (§22.4)', function () {
    Notification::fake();
    $object = makeReservableObject($this->child->id);

    app(DamageReportService::class)->submit(
        object: $object,
        reporter: $this->reporter,
        description: 'Roeispaan gebroken.',
        severity: DamageSeverity::Medium,
        reporterMarkedUnusable: true,
        photos: collect(),
    );

    expect($object->refresh()->status)->toBe(ReservableObjectStatus::OutOfService);
});

it('laat het object beschikbaar wanneer melder niet-bruikbaar niet aankruist', function () {
    Notification::fake();
    $object = makeReservableObject($this->child->id);

    app(DamageReportService::class)->submit(
        object: $object,
        reporter: $this->reporter,
        description: 'Kleine kras.',
        severity: DamageSeverity::Low,
        reporterMarkedUnusable: false,
        photos: collect(),
    );

    expect($object->refresh()->status)->toBe(ReservableObjectStatus::Available);
});

it('kan een buiten-gebruik-object weer op beschikbaar zetten via restoreObject', function () {
    Notification::fake();
    $object = makeReservableObject($this->child->id);

    $report = app(DamageReportService::class)->submit(
        object: $object,
        reporter: $this->reporter,
        description: 'Onbruikbaar.',
        severity: DamageSeverity::High,
        reporterMarkedUnusable: true,
        photos: collect(),
    );

    app(DamageReportService::class)->restoreObject($object->fresh(), $this->reporter, $report);

    expect($object->refresh()->status)->toBe(ReservableObjectStatus::Available);
});

it('doorloopt de status-workflow gemeld → in behandeling → opgelost', function () {
    Notification::fake();
    $object = makeReservableObject($this->child->id);
    $service = app(DamageReportService::class);

    $report = $service->submit(
        object: $object,
        reporter: $this->reporter,
        description: 'X',
        severity: DamageSeverity::Low,
        reporterMarkedUnusable: false,
        photos: collect(),
    );

    $behandelaar = Person::create(['first_name' => 'Beh', 'last_name' => 'Andelaar']);

    $service->assign($report, $behandelaar, $this->reporter);
    expect($report->refresh()->status)->toBe(DamageReportStatus::InProgress)
        ->and($report->assigned_to_person_id)->toBe($behandelaar->id);

    $service->changeStatus($report, DamageReportStatus::Resolved, $behandelaar, 'Nieuwe roeispaan gemonteerd.');
    expect($report->refresh()->status)->toBe(DamageReportStatus::Resolved)
        ->and($report->resolution)->toBe('Nieuwe roeispaan gemonteerd.')
        ->and($report->resolved_at)->not->toBeNull();
});

it('mailt de verantwoordelijke van de eigen categorie', function () {
    Notification::fake();
    $object = makeReservableObject($this->child->id);
    $direct = Person::create(['first_name' => 'Cat', 'last_name' => 'Chief', 'email' => 'cat@example.test']);
    CategoryResponsible::create(['object_category_id' => $this->child->id, 'person_id' => $direct->id]);

    app(DamageReportService::class)->submit(
        object: $object,
        reporter: $this->reporter,
        description: 'X',
        severity: DamageSeverity::Low,
        reporterMarkedUnusable: false,
        photos: collect(),
    );

    Notification::assertSentOnDemand(DamageReportSubmitted::class, function ($notification, $channels, $notifiable) use ($direct) {
        return in_array('mail', $channels, true)
            && ($notifiable->routes['mail'] ?? null) === $direct->email;
    });
});

it('erft de verantwoordelijke van de parent-categorie als de eigen categorie er geen heeft (§22.4)', function () {
    Notification::fake();
    $object = makeReservableObject($this->child->id);
    $parentChief = Person::create(['first_name' => 'Par', 'last_name' => 'Ent', 'email' => 'parent@example.test']);
    CategoryResponsible::create(['object_category_id' => $this->parent->id, 'person_id' => $parentChief->id]);

    app(DamageReportService::class)->submit(
        object: $object,
        reporter: $this->reporter,
        description: 'X',
        severity: DamageSeverity::Low,
        reporterMarkedUnusable: false,
        photos: collect(),
    );

    Notification::assertSentOnDemand(DamageReportSubmitted::class, function ($notification, $channels, $notifiable) use ($parentChief) {
        return ($notifiable->routes['mail'] ?? null) === $parentChief->email;
    });
});

it('stuurt geen mail als geen enkele categorie in de keten een verantwoordelijke heeft', function () {
    Notification::fake();
    $object = makeReservableObject($this->child->id);

    app(DamageReportService::class)->submit(
        object: $object,
        reporter: $this->reporter,
        description: 'X',
        severity: DamageSeverity::Low,
        reporterMarkedUnusable: false,
        photos: collect(),
    );

    Notification::assertNothingSent();
});

it('logt "damage_report.submitted" in het auditlogboek', function () {
    Notification::fake();
    $object = makeReservableObject($this->child->id);

    $report = app(DamageReportService::class)->submit(
        object: $object,
        reporter: $this->reporter,
        description: 'X',
        severity: DamageSeverity::Low,
        reporterMarkedUnusable: true,
        photos: collect(),
    );

    expect(AuditEntry::query()->where('action', 'damage_report.submitted')->exists())->toBeTrue();
    expect(AuditEntry::query()->where('action', 'damage_report.object_marked_unusable')->exists())->toBeTrue();
});

it('heeft de melding gekoppeld aan een reservering als die wordt meegegeven', function () {
    Notification::fake();
    $object = makeReservableObject($this->child->id);

    $reservation = Reservation::create([
        'reservable_object_id' => $object->id,
        'person_id' => $this->reporter->id,
        'requested_by_person_id' => $this->reporter->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'status' => ReservationStatus::Confirmed,
    ]);

    $report = app(DamageReportService::class)->submit(
        object: $object,
        reporter: $this->reporter,
        description: 'X',
        severity: DamageSeverity::Low,
        reporterMarkedUnusable: false,
        photos: collect(),
        reservation: $reservation,
    );

    expect($report->reservation_id)->toBe($reservation->id);

    $damageReport = DamageReport::find($report->id);
    expect($damageReport->reservation->id)->toBe($reservation->id);
});
