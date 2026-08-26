<?php

use App\Livewire\Admin\ActiviteitBeheer;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivitySeries;
use App\Models\Person;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActivityCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ActivityCategorySeeder::class);

    $this->category = ActivityCategory::query()->where('slug', 'roeien')->firstOrFail();

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    $person = Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id]);
    $person->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));
});

it('maakt een losse activiteit aan (standaardkeuze), eventueel over meerdere dagen', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->assertSet('creationMode', 'los')
        ->set('categoryId', $this->category->id)
        ->set('title', 'Zomerkamp weekend')
        ->set('startsAt', now()->addDays(5)->format('Y-m-d\TH:i'))
        ->set('endsAt', now()->addDays(7)->format('Y-m-d\TH:i'))
        ->call('createGroup')
        ->assertHasNoErrors();

    $series = ActivitySeries::query()->where('title', 'Zomerkamp weekend')->firstOrFail();
    expect($series->activities()->count())->toBe(1)
        ->and($series->enrollment_level->value)->toBe('bundel')
        ->and((int) $series->activities()->first()->starts_at->diffInDays($series->activities()->first()->ends_at))->toBe(2);
});

it('slaat inschrijfvenster en annuleringsdeadline op en past die toe op elk voorkomen', function () {
    $this->actingAs($this->beheerder);
    $opens = now()->addDay()->format('Y-m-d\TH:i');
    $closes = now()->addDays(4)->format('Y-m-d\TH:i');
    $deadline = now()->addDays(4)->format('Y-m-d\TH:i');

    Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->set('categoryId', $this->category->id)
        ->set('title', 'Clinic')
        ->set('startsAt', now()->addDays(5)->format('Y-m-d\TH:i'))
        ->set('enrollmentOpensAt', $opens)
        ->set('enrollmentClosesAt', $closes)
        ->set('cancellationDeadline', $deadline)
        ->call('createGroup')
        ->assertHasNoErrors();

    $activity = Activity::query()->where('title', 'Clinic')->firstOrFail();
    expect($activity->enrollment_opens_at->format('Y-m-d\TH:i'))->toBe($opens)
        ->and($activity->enrollment_closes_at->format('Y-m-d\TH:i'))->toBe($closes)
        ->and($activity->cancellation_deadline->format('Y-m-d\TH:i'))->toBe($deadline);
});

it('maakt tijdens het aanmaken extra inschrijfvelden aan (tekst, keuze, aantal) op elk voorkomen', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->set('categoryId', $this->category->id)
        ->set('title', 'Kamp')
        ->set('startsAt', now()->addDays(5)->format('Y-m-d\TH:i'))
        // Tekstveld.
        ->call('selectNewFieldType', 'text')
        ->set('newFieldLabel', 'Allergieën')
        ->call('addPendingRegistrationField')
        // Aantal-veld met prijs en maximum.
        ->call('selectNewFieldType', 'count')
        ->set('newFieldLabel', 'Introducees')
        ->set('newFieldRequired', true)
        ->set('newFieldPricePerUnit', 5)
        ->set('newFieldMaxCount', 3)
        ->call('addPendingRegistrationField')
        // Keuzeveld met twee opties.
        ->call('selectNewFieldType', 'choice')
        ->set('newFieldLabel', 'Maaltijd')
        ->set('newFieldOptionLabel', 'Vega')
        ->set('newFieldOptionPrice', 10)
        ->call('addNewFieldOption')
        ->set('newFieldOptionLabel', 'Vlees')
        ->set('newFieldOptionPrice', 12)
        ->call('addNewFieldOption')
        ->call('addPendingRegistrationField')
        ->call('createGroup')
        ->assertHasNoErrors();

    $activity = Activity::query()->where('title', 'Kamp')->firstOrFail();
    $fields = $activity->registrationFields()->with('options')->orderBy('sort_order')->get();

    expect($fields)->toHaveCount(3)
        ->and($fields[0]->type)->toBe('text')
        ->and($fields[0]->label)->toBe('Allergieën')
        ->and($fields[1]->type)->toBe('count')
        ->and($fields[1]->required)->toBeTrue()
        ->and($fields[1]->price_per_unit)->toBe(5.0)
        ->and($fields[1]->max_count)->toBe(3)
        ->and($fields[2]->type)->toBe('choice')
        ->and($fields[2]->options)->toHaveCount(2)
        ->and($fields[2]->options[0]->label)->toBe('Vega')
        ->and($fields[2]->options[0]->price)->toBe(10.0);
});

it('weigert een keuzeveld zonder minstens één optie', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->call('selectNewFieldType', 'choice')
        ->set('newFieldLabel', 'Maaltijd')
        ->call('addPendingRegistrationField')
        ->assertHasErrors('newFieldOptions');
});

it('maakt bij het aanmaken automatisch producten aan voor de ingevulde standaard-/annuleringskosten', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->set('categoryId', $this->category->id)
        ->set('title', 'Kamp')
        ->set('startsAt', now()->addDays(5)->format('Y-m-d\TH:i'))
        ->set('standardCostAmount', 25)
        ->set('cancellationCostAmount', 10)
        ->call('createGroup')
        ->assertHasNoErrors();

    $activity = Activity::query()->where('title', 'Kamp')->with(['standardCostProduct', 'cancellationCostProduct'])->firstOrFail();
    expect($activity->standardCostProduct)->not->toBeNull()
        ->and((float) $activity->standardCostProduct->currentPrice()->amount)->toBe(25.0)
        ->and((float) $activity->cancellationCostProduct->currentPrice()->amount)->toBe(10.0);
});

it('werkt het bedrag van een al gekoppeld kostenproduct bij i.p.v. een nieuw product aan te maken', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->set('categoryId', $this->category->id)
        ->set('title', 'Kamp')
        ->set('startsAt', now()->addDays(5)->format('Y-m-d\TH:i'))
        ->set('standardCostAmount', 25)
        ->call('createGroup');

    $activity = Activity::query()->where('title', 'Kamp')->firstOrFail();
    $productId = $activity->standard_cost_product_id;

    Livewire::test(ActiviteitBeheer::class)
        ->call('editActivity', $activity->id)
        ->set('standardCostAmount', 30)
        ->call('saveActivity');

    $activity->refresh();
    expect($activity->standard_cost_product_id)->toBe($productId)
        ->and((float) $activity->standardCostProduct->currentPrice()->amount)->toBe(30.0)
        ->and(Product::query()->count())->toBe(1);
});

it('koppelt de aanmaker automatisch als beheerder, plus eventueel extra gekozen beheerders', function () {
    $this->actingAs($this->beheerder);
    $manager = Person::create(['first_name' => 'Marieke', 'last_name' => 'Beheer']);

    $component = Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup');

    expect($component->get('pendingManagers'))->toHaveCount(1)
        ->and($component->get('pendingManagers')[0]['person_id'])->toBe($this->beheerder->person->id);

    $component->set('categoryId', $this->category->id)
        ->set('title', 'Bardienst')
        ->set('startsAt', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->set('pendingManagerPersonId', $manager->id)
        ->call('addPendingManager')
        ->call('togglePendingManagerNotify', $manager->id)
        ->call('createGroup')
        ->assertHasNoErrors();

    $activity = Activity::query()->where('title', 'Bardienst')->firstOrFail();
    $managerIds = $activity->managers()->pluck('persons.id')->all();
    expect($managerIds)->toHaveCount(2)
        ->and($managerIds)->toContain($this->beheerder->person->id, $manager->id);

    $mariekeManager = $activity->managers()->where('persons.id', $manager->id)->firstOrFail();
    $creatorManager = $activity->managers()->where('persons.id', $this->beheerder->person->id)->firstOrFail();
    // @phpstan-ignore property.notFound (dynamische pivot-kolom, withPivot('notify'))
    expect((bool) $mariekeManager->pivot->notify)->toBeFalse()
        // @phpstan-ignore property.notFound (dynamische pivot-kolom, withPivot('notify'))
        ->and((bool) $creatorManager->pivot->notify)->toBeTrue();
});

it('maakt een groep aan met handmatige en gegenereerde data, met een verwijderde rij ertussenuit', function () {
    $this->actingAs($this->beheerder);

    $component = Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->call('selectCreationMode', 'reeks')
        ->set('categoryId', $this->category->id)
        ->set('title', 'Zeilcursus')
        ->set('capacity', 10)
        ->set('manualDate', now()->addDays(20)->format('Y-m-d'))
        ->set('manualStartTime', '14:00')
        ->call('addManualDate')
        ->call('selectGenMode', 'weekly')
        ->set('genWeekday', (int) now()->addDays(7)->dayOfWeekIso)
        ->set('genStartDate', now()->addDays(7)->format('Y-m-d'))
        ->call('selectGenBoundMode', 'count')
        ->set('genCount', 3)
        ->set('genStartTime', '09:00')
        ->call('generateDates');

    expect($component->get('pendingDates'))->toHaveCount(4);

    $component->call('removePendingDate', 1)
        ->call('createGroup')
        ->assertHasNoErrors();

    $series = ActivitySeries::query()->where('title', 'Zeilcursus')->firstOrFail();
    expect($series->activities()->count())->toBe(3)
        ->and($series->activities()->first()->capacity)->toBe(10)
        ->and($series->enrollment_level->value)->toBe('reeks');
});

it('genereert maandelijkse en per-kwartaal data op een vaste dag van de maand', function () {
    $this->actingAs($this->beheerder);
    $start = now()->addDays(10)->startOfDay();

    $monthly = Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->call('selectCreationMode', 'reeks')
        ->call('selectGenMode', 'monthly')
        ->set('genMonthlyDayMode', 'fixed')
        ->set('genDayOfMonth', $start->day)
        ->set('genStartDate', $start->format('Y-m-d'))
        ->call('selectGenBoundMode', 'count')
        ->set('genCount', 3)
        ->set('genStartTime', '09:00')
        ->call('generateDates')
        ->assertHasNoErrors()
        ->get('pendingDates');

    expect($monthly)->toHaveCount(3);
    expect(Carbon::parse($monthly[1]['starts_at']))->toEqual($start->copy()->addMonthsNoOverflow(1)->setTime(9, 0));
    expect(Carbon::parse($monthly[2]['starts_at']))->toEqual($start->copy()->addMonthsNoOverflow(2)->setTime(9, 0));

    $quarterly = Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->call('selectCreationMode', 'reeks')
        ->call('selectGenMode', 'quarterly')
        ->set('genMonthlyDayMode', 'fixed')
        ->set('genDayOfMonth', $start->day)
        ->set('genStartDate', $start->format('Y-m-d'))
        ->call('selectGenBoundMode', 'count')
        ->set('genCount', 2)
        ->set('genStartTime', '09:00')
        ->call('generateDates')
        ->assertHasNoErrors()
        ->get('pendingDates');

    expect($quarterly)->toHaveCount(2);
    expect(Carbon::parse($quarterly[1]['starts_at']))->toEqual($start->copy()->addMonthsNoOverflow(3)->setTime(9, 0));
});

it('genereert maandelijkse data op de Nde weekdag van de maand', function () {
    $this->actingAs($this->beheerder);
    $start = Carbon::create(2027, 1, 1);

    // Tweede dinsdag van januari, februari, maart 2027.
    $secondTuesdays = Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->call('selectCreationMode', 'reeks')
        ->call('selectGenMode', 'monthly')
        ->set('genMonthlyDayMode', 'weekday')
        ->set('genOrdinal', '2')
        ->set('genWeekday', 2)
        ->set('genStartDate', $start->format('Y-m-d'))
        ->call('selectGenBoundMode', 'count')
        ->set('genCount', 3)
        ->set('genStartTime', '19:00')
        ->call('generateDates')
        ->assertHasNoErrors()
        ->get('pendingDates');

    expect($secondTuesdays)->toHaveCount(3);
    foreach ($secondTuesdays as $i => $date) {
        $parsed = Carbon::parse($date['starts_at']);
        expect($parsed->isTuesday())->toBeTrue()
            ->and($parsed->month)->toBe($start->copy()->addMonthsNoOverflow($i)->month)
            ->and((int) ceil($parsed->day / 7))->toBe(2);
    }

    // Laatste vrijdag van januari 2027.
    $lastFriday = Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->call('selectCreationMode', 'reeks')
        ->call('selectGenMode', 'monthly')
        ->set('genMonthlyDayMode', 'weekday')
        ->set('genOrdinal', '-1')
        ->set('genWeekday', 5)
        ->set('genStartDate', $start->format('Y-m-d'))
        ->call('selectGenBoundMode', 'count')
        ->set('genCount', 1)
        ->set('genStartTime', '19:00')
        ->call('generateDates')
        ->assertHasNoErrors()
        ->get('pendingDates');

    $parsedLastFriday = Carbon::parse($lastFriday[0]['starts_at']);
    expect($parsedLastFriday->isFriday())->toBeTrue()
        ->and($parsedLastFriday->month)->toBe(1)
        ->and($parsedLastFriday->copy()->addWeek()->month)->toBe(2);
});

it('voegt meerdere via de kalender geselecteerde datums in één keer toe', function () {
    $this->actingAs($this->beheerder);

    $first = now()->addDays(10)->format('Y-m-d');
    $second = now()->addDays(17)->format('Y-m-d');

    $component = Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->call('selectCreationMode', 'bundel')
        ->set('manualStartTime', '11:00')
        ->set('manualEndTime', '13:00')
        ->call('addManualDates', [$second, $first, $second, 'onzin', 123]);

    $dates = $component->get('pendingDates');
    expect($dates)->toHaveCount(2)
        ->and($dates[0]['starts_at'])->toBe("{$first}T11:00")
        ->and($dates[0]['ends_at'])->toBe("{$first}T13:00")
        ->and($dates[1]['starts_at'])->toBe("{$second}T11:00");
});

it('weigert aanmaken zonder minstens één datum', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('startCreateGroup')
        ->call('selectCreationMode', 'bundel')
        ->set('categoryId', $this->category->id)
        ->set('title', 'Lege groep')
        ->call('createGroup')
        ->assertHasErrors('pendingDates');

    expect(ActivitySeries::query()->count())->toBe(0);
});

it('past een wijziging toe op de hele groep behalve op uitzonderingen', function () {
    $series = ActivitySeries::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Kamp',
        'enrollment_level' => 'bundel',
    ]);
    $normal = Activity::create([
        'activity_category_id' => $this->category->id,
        'series_id' => $series->id,
        'title' => 'Kamp', 'starts_at' => now()->addDays(5),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);
    $exception = Activity::create([
        'activity_category_id' => $this->category->id,
        'series_id' => $series->id, 'is_exception' => true,
        'title' => 'Kamp (aangepast)', 'starts_at' => now()->addDays(6),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('editGroup', $series->id)
        ->set('title', 'Zomerkamp')
        ->set('editScope', 'hele_reeks')
        ->call('applyGroupEdit')
        ->assertHasNoErrors();

    expect($series->refresh()->title)->toBe('Zomerkamp')
        ->and($normal->refresh()->title)->toBe('Zomerkamp')
        ->and($exception->refresh()->title)->toBe('Kamp (aangepast)');
});

it('splitst de groep af vanaf een gekozen voorkomen bij "dit en volgende"', function () {
    $series = ActivitySeries::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Bardienst',
        'location' => 'Clubhuis',
        'enrollment_level' => 'bundel',
    ]);
    $before = Activity::create([
        'activity_category_id' => $this->category->id, 'series_id' => $series->id,
        'title' => 'Bardienst', 'location' => 'Clubhuis', 'starts_at' => now()->addDays(1),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);
    $pivot = Activity::create([
        'activity_category_id' => $this->category->id, 'series_id' => $series->id,
        'title' => 'Bardienst', 'location' => 'Clubhuis', 'starts_at' => now()->addDays(8),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)
        ->call('editGroup', $series->id)
        ->set('location', 'Nieuwe kantine')
        ->set('editScope', 'dit_en_volgende')
        ->set('splitFromActivityId', $pivot->id)
        ->call('applyGroupEdit')
        ->assertHasNoErrors();

    $newSeries = ActivitySeries::query()->where('split_from_id', $series->id)->firstOrFail();
    expect($newSeries->location)->toBe('Nieuwe kantine')
        ->and($series->refresh()->location)->toBe('Clubhuis')
        ->and($before->refresh()->series_id)->toBe($series->id)
        ->and($pivot->refresh()->series_id)->toBe($newSeries->id)
        ->and($pivot->location)->toBe('Nieuwe kantine');
});

it('weigert een groep te verwijderen met resterende voorkomens', function () {
    $series = ActivitySeries::create([
        'activity_category_id' => $this->category->id,
        'title' => 'Actief',
        'enrollment_level' => 'bundel',
    ]);
    Activity::create([
        'activity_category_id' => $this->category->id, 'series_id' => $series->id,
        'title' => 'Actief', 'starts_at' => now()->addDays(2),
        'visibility' => 'members', 'status' => 'gepubliceerd',
    ]);

    $this->actingAs($this->beheerder);

    Livewire::test(ActiviteitBeheer::class)->call('deleteGroup', $series->id);

    expect(ActivitySeries::query()->find($series->id))->not->toBeNull();
});
