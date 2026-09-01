<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use App\Enums\EnrollmentStatus;
use App\Mail\TemplatedMail;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\EnrollmentFieldValue;
use App\Models\InAppNotification;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Activities\EnrollmentService;
use Database\Seeders\DagboekSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->category = ActivityCategory::create(['name' => 'Roeien', 'slug' => 'roeien', 'sort_order' => 10]);
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(DagboekSeeder::class);
    $this->seed(MessageTemplateSeeder::class);
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

it('mailt en meldt de gepromoveerde persoon bij wachtlijst-promotie', function () {
    Mail::fake();

    $activity = newActivity($this->category->id, capacity: 1);

    $a = Person::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.test']);
    $b = Person::create(['first_name' => 'B', 'last_name' => 'C', 'email' => 'b@example.test']);

    $eA = app(EnrollmentService::class)->enroll($activity, $a);
    app(EnrollmentService::class)->enroll($activity, $b);

    app(EnrollmentService::class)->cancel($eA);

    Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('b@example.test')
        && str_contains($mail->mailSubject, 'plek vrijgekomen'));

    expect(InAppNotification::query()->where('person_id', $b->id)->where('type', 'enrollment_waitlist_promoted')->exists())->toBeTrue();
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

it('weigert inschrijving buiten het inschrijfvenster', function () {
    $activity = newActivity($this->category->id, capacity: 5);
    $activity->update(['enrollment_opens_at' => now()->addDay()]);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    expect(fn () => app(EnrollmentService::class)->enroll($activity, $person))
        ->toThrow(RuntimeException::class);

    $activity->update(['enrollment_opens_at' => null, 'enrollment_closes_at' => now()->subDay()]);

    expect(fn () => app(EnrollmentService::class)->enroll($activity, $person))
        ->toThrow(RuntimeException::class);
});

it('staat inschrijving toe binnen het inschrijfvenster', function () {
    $activity = newActivity($this->category->id, capacity: 5);
    $activity->update(['enrollment_opens_at' => now()->subDay(), 'enrollment_closes_at' => now()->addDay()]);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    $enrollment = app(EnrollmentService::class)->enroll($activity, $person);

    expect($enrollment->status)->toBe(EnrollmentStatus::Enrolled);
});

it('weigert annuleren na de uiterste annuleringsdatum', function () {
    $activity = newActivity($this->category->id, capacity: 5);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    $enrollment = app(EnrollmentService::class)->enroll($activity, $person);

    $activity->update(['cancellation_deadline' => now()->subDay()]);

    expect(fn () => app(EnrollmentService::class)->cancel($enrollment))
        ->toThrow(RuntimeException::class);
});

it('mailt de ingeschrevene zelf een bevestiging', function () {
    Mail::fake();

    $activity = newActivity($this->category->id, capacity: 5);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.test']);

    app(EnrollmentService::class)->enroll($activity, $person);

    Mail::assertQueued(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('a@example.test'));
});

it('weigert inschrijving zonder antwoord op een verplicht inschrijfveld', function () {
    $activity = newActivity($this->category->id, capacity: 5);
    $field = $activity->registrationFields()->create(['type' => 'text', 'label' => 'Opmerking', 'required' => true]);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    expect(fn () => app(EnrollmentService::class)->enroll($activity, $person, fieldAnswers: []))
        ->toThrow(RuntimeException::class);

    $enrollment = app(EnrollmentService::class)->enroll($activity, $person, fieldAnswers: [$field->id => 'Geen noten']);
    expect(EnrollmentFieldValue::query()->where('enrollment_id', $enrollment->id)->where('field_id', $field->id)->first()->text_value)
        ->toBe('Geen noten');
});

it('weigert een aantal boven het maximum en slaat prijzen indicatief op', function () {
    $activity = newActivity($this->category->id, capacity: 5);
    $countField = $activity->registrationFields()->create([
        'type' => 'count', 'label' => 'Introducees', 'price_per_unit' => 5, 'max_count' => 2,
    ]);
    $choiceField = $activity->registrationFields()->create(['type' => 'choice', 'label' => 'Maaltijd']);
    $option = $choiceField->options()->create(['label' => 'Vega', 'price' => 10]);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    expect(fn () => app(EnrollmentService::class)->enroll($activity, $person, fieldAnswers: [$countField->id => 3]))
        ->toThrow(RuntimeException::class);

    expect(fn () => app(EnrollmentService::class)->enroll($activity, $person, fieldAnswers: [$choiceField->id => 999]))
        ->toThrow(RuntimeException::class);

    $enrollment = app(EnrollmentService::class)->enroll($activity, $person, fieldAnswers: [
        $countField->id => 2,
        $choiceField->id => $option->id,
    ]);

    expect($enrollment->fresh()->indicativeFieldsTotal())->toBe(20.0);
});

it('boekt standaardkosten als post bij een bevestigde inschrijving, niet bij de wachtlijst', function () {
    $product = Product::create(['name' => 'Kampbijdrage', 'type' => 'activiteitsbijdrage']);
    ProductPrice::create(['product_id' => $product->id, 'valid_from' => now()->subDay(), 'amount' => '25.00']);

    $activity = newActivity($this->category->id, capacity: 1);
    $activity->update(['standard_cost_product_id' => $product->id]);

    $a = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    $b = Person::create(['first_name' => 'B', 'last_name' => 'C']);

    $enrolledA = app(EnrollmentService::class)->enroll($activity, $a);
    $waitlistedB = app(EnrollmentService::class)->enroll($activity, $b);

    expect(Charge::query()->where('subject_type', Enrollment::class)->where('subject_id', $enrolledA->id)->count())->toBe(1)
        ->and(Charge::query()->where('subject_type', Enrollment::class)->where('subject_id', $waitlistedB->id)->count())->toBe(0);

    $charge = Charge::query()->where('subject_id', $enrolledA->id)->firstOrFail();
    expect((float) $charge->amount)->toBe(25.0)
        ->and($charge->debtor_person_id)->toBe($a->id);

    // Bij promotie vanaf de wachtlijst wordt alsnog geboekt.
    app(EnrollmentService::class)->cancel($enrolledA);
    expect(Charge::query()->where('subject_type', Enrollment::class)->where('subject_id', $waitlistedB->id)->count())->toBe(1);
});

it('boekt annuleringskosten alleen bij het afmelden van een bevestigde plek', function () {
    $cancelProduct = Product::create(['name' => 'Annuleringskosten kamp', 'type' => 'activiteitsbijdrage']);
    ProductPrice::create(['product_id' => $cancelProduct->id, 'valid_from' => now()->subDay(), 'amount' => '10.00']);

    $activity = newActivity($this->category->id, capacity: 1);
    $activity->update(['cancellation_cost_product_id' => $cancelProduct->id]);

    $a = Person::create(['first_name' => 'A', 'last_name' => 'B']);
    $b = Person::create(['first_name' => 'B', 'last_name' => 'C']);

    $enrolledA = app(EnrollmentService::class)->enroll($activity, $a);
    $waitlistedB = app(EnrollmentService::class)->enroll($activity, $b);

    app(EnrollmentService::class)->cancel($waitlistedB);
    expect(Charge::query()->where('subject_type', Enrollment::class)->where('subject_id', $waitlistedB->id)->count())->toBe(0);

    app(EnrollmentService::class)->cancel($enrolledA);
    $charge = Charge::query()->where('subject_type', Enrollment::class)->where('subject_id', $enrolledA->id)->firstOrFail();
    expect((float) $charge->amount)->toBe(10.0);
});

it('boekt geen kosten zonder gekoppeld product of zonder geldende prijs', function () {
    $activity = newActivity($this->category->id, capacity: 5);
    $person = Person::create(['first_name' => 'A', 'last_name' => 'B']);

    app(EnrollmentService::class)->enroll($activity, $person);
    expect(Charge::query()->count())->toBe(0);

    $productZonderPrijs = Product::create(['name' => 'Geen prijs', 'type' => 'activiteitsbijdrage']);
    $activity->update(['standard_cost_product_id' => $productZonderPrijs->id]);
    $other = Person::create(['first_name' => 'C', 'last_name' => 'D']);
    app(EnrollmentService::class)->enroll($activity, $other);

    expect(Charge::query()->count())->toBe(0);
});
