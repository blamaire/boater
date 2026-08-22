<?php

use App\Livewire\Admin\DagboekDetail;
use App\Models\Dagboek;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\BillingService;
use App\Services\Finance\FiscalYearService;
use Database\Seeders\DagboekSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(DagboekSeeder::class);

    $this->beheerder = User::factory()->create(['email_verified_at' => now()]);
    Person::create(['first_name' => 'B', 'last_name' => 'Heer', 'account_id' => $this->beheerder->id])
        ->roles()->attach(Role::query()->where('name', 'Beheerder')->value('id'));

    $this->memoriaal = Dagboek::query()->where('type', 'memoriaal')->firstOrFail();
    $this->debiteuren = LedgerAccount::query()->where('code', '1300')->firstOrFail();
    $this->opbrengsten = LedgerAccount::query()->where('code', '8000')->firstOrFail();
});

it('vereist dagboeken.manage permissie', function () {
    $dagboek = Dagboek::query()->where('type', 'verkoop')->firstOrFail();
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid)->get("/beheer/dagboeken/{$dagboek->id}")->assertForbidden();
});

it('toont de journaalposten van een dagboek', function () {
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $debtor = Person::create(['first_name' => 'Piet', 'last_name' => 'Betaler']);
    app(BillingService::class)->createCharge($product, $debtor, '90.00', 'Contributie 2026');

    $verkoop = Dagboek::query()->where('type', 'verkoop')->firstOrFail();

    $this->actingAs($this->beheerder)
        ->get("/beheer/dagboeken/{$verkoop->id}")
        ->assertOk()
        ->assertSee($verkoop->name)
        ->assertSee('Post: Contributie 2026')
        ->assertSee('90,00');
});

it('toont een leeg dagboek zonder fouten', function () {
    $kas = Dagboek::query()->where('type', 'kas')->firstOrFail();

    $this->actingAs($this->beheerder)
        ->get("/beheer/dagboeken/{$kas->id}")
        ->assertOk()
        ->assertSee('Nog geen journaalposten');
});

it('vereist dagboeken.manage permissie bij het opslaan van een journaalpost, niet alleen op de pagina', function () {
    $lid = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($lid);

    Livewire::test(DagboekDetail::class, ['dagboek' => $this->memoriaal])
        ->set('description', 'Poging zonder rechten')
        ->set('lines', [
            ['account_id' => $this->debiteuren->id, 'debit' => '10.00', 'credit' => ''],
            ['account_id' => $this->opbrengsten->id, 'debit' => '', 'credit' => '10.00'],
        ])
        ->call('save')
        ->assertStatus(403);
});

it('boekt een handmatige, balancerende journaalpost in de periode van de gekozen datum', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(DagboekDetail::class, ['dagboek' => $this->memoriaal])
        ->set('description', 'Handmatige correctie')
        ->set('lines', [
            ['account_id' => $this->debiteuren->id, 'debit' => '25.00', 'credit' => ''],
            ['account_id' => $this->opbrengsten->id, 'debit' => '', 'credit' => '25.00'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $entry = JournalEntry::query()->where('description', 'Handmatige correctie')->firstOrFail();
    $verwachtePeriode = app(FiscalYearService::class)->periodForDate(now());
    expect($entry->period_id)->toBe($verwachtePeriode->id)
        ->and($entry->dagboek_id)->toBe($this->memoriaal->id);
});

it('boekt in periode 0 (beginbalans) als de beginbalans-vlag aan staat', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(DagboekDetail::class, ['dagboek' => $this->memoriaal])
        ->set('description', 'Beginbalans 2026')
        ->set('isOpeningBalance', true)
        ->set('lines', [
            ['account_id' => $this->debiteuren->id, 'debit' => '100.00', 'credit' => ''],
            ['account_id' => $this->opbrengsten->id, 'debit' => '', 'credit' => '100.00'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $entry = JournalEntry::query()->where('description', 'Beginbalans 2026')->firstOrFail();
    $beginbalans = app(FiscalYearService::class)->openingBalancePeriod(now()->year);
    expect($entry->period_id)->toBe($beginbalans->id)
        ->and($entry->period->isOpeningBalance())->toBeTrue();
});

it('weigert minder dan twee regels', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(DagboekDetail::class, ['dagboek' => $this->memoriaal])
        ->set('description', 'Te weinig regels')
        ->set('lines', [
            ['account_id' => $this->debiteuren->id, 'debit' => '10.00', 'credit' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['lines']);

    expect(JournalEntry::query()->where('description', 'Te weinig regels')->exists())->toBeFalse();
});

it('weigert een regel met zowel debet als credit ingevuld', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(DagboekDetail::class, ['dagboek' => $this->memoriaal])
        ->set('description', 'Regel met beide')
        ->set('lines', [
            ['account_id' => $this->debiteuren->id, 'debit' => '10.00', 'credit' => '5.00'],
            ['account_id' => $this->opbrengsten->id, 'debit' => '', 'credit' => '5.00'],
        ])
        ->call('save')
        ->assertHasErrors(['lines.0.debit']);

    expect(JournalEntry::query()->where('description', 'Regel met beide')->exists())->toBeFalse();
});

it('weigert een regel met noch debet noch credit ingevuld', function () {
    $this->actingAs($this->beheerder);

    Livewire::test(DagboekDetail::class, ['dagboek' => $this->memoriaal])
        ->set('description', 'Regel met geen van beide')
        ->set('lines', [
            ['account_id' => $this->debiteuren->id, 'debit' => '', 'credit' => ''],
            ['account_id' => $this->opbrengsten->id, 'debit' => '', 'credit' => '10.00'],
        ])
        ->call('save')
        ->assertHasErrors(['lines.0.debit']);

    expect(JournalEntry::query()->where('description', 'Regel met geen van beide')->exists())->toBeFalse();
});

it('toont een foutmelding bij een onbalans, zonder journaalpost aan te maken', function () {
    $this->actingAs($this->beheerder);
    $countBefore = JournalEntry::count();

    $component = Livewire::test(DagboekDetail::class, ['dagboek' => $this->memoriaal])
        ->set('description', 'Scheve post')
        ->set('lines', [
            ['account_id' => $this->debiteuren->id, 'debit' => '10.00', 'credit' => ''],
            ['account_id' => $this->opbrengsten->id, 'debit' => '', 'credit' => '9.00'],
        ])
        ->call('save');

    expect($component->get('errorMessage'))->toContain('niet in balans')
        ->and(JournalEntry::count())->toBe($countBefore);
});

it('toont een foutmelding bij het boeken in een afgesloten periode, zonder journaalpost aan te maken', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 15));
    $fiscalYears = app(FiscalYearService::class);
    $voorbijePeriode = $fiscalYears->periodForDate(Carbon::create(2026, 1, 10));
    $fiscalYears->close($voorbijePeriode, app(AuditLogger::class));

    $this->actingAs($this->beheerder);
    $countBefore = JournalEntry::count();

    $component = Livewire::test(DagboekDetail::class, ['dagboek' => $this->memoriaal])
        ->set('date', '2026-01-10')
        ->set('description', 'Te laat geboekt')
        ->set('lines', [
            ['account_id' => $this->debiteuren->id, 'debit' => '10.00', 'credit' => ''],
            ['account_id' => $this->opbrengsten->id, 'debit' => '', 'credit' => '10.00'],
        ])
        ->call('save');

    expect($component->get('errorMessage'))->toContain('afgesloten')
        ->and(JournalEntry::count())->toBe($countBefore);

    Carbon::setTestNow();
});
