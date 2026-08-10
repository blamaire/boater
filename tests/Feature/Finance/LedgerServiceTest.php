<?php

use App\Models\Dagboek;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\FiscalYearService;
use App\Services\Finance\LedgerService;
use Database\Seeders\DagboekSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(DagboekSeeder::class);

    $this->ledger = app(LedgerService::class);
    $this->fiscalYears = app(FiscalYearService::class);
    $this->audit = app(AuditLogger::class);
    $this->account = LedgerAccount::query()->where('code', '1300')->firstOrFail();
    $this->revenue = LedgerAccount::query()->where('code', '8000')->firstOrFail();
    $this->dagboek = Dagboek::query()->where('type', 'memoriaal')->firstOrFail();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('resolveert automatisch de periode op basis van de datum als er geen periode is meegegeven', function () {
    $date = Carbon::create(2027, 4, 12);

    $entry = $this->ledger->record($date, 'Automatische periode', null, [
        ['account_id' => $this->account->id, 'debit' => '10.00'],
        ['account_id' => $this->revenue->id, 'credit' => '10.00'],
    ], $this->dagboek);

    expect($entry->period->number)->toBe(4)
        ->and($entry->period->fiscalYear->year)->toBe(2027);
});

it('gebruikt de expliciet meegegeven periode in plaats van de datumresolutie', function () {
    $date = Carbon::create(2027, 6, 1);
    $beginbalans = $this->fiscalYears->openingBalancePeriod(2027);

    $entry = $this->ledger->record($date, 'Beginbalans', null, [
        ['account_id' => $this->account->id, 'debit' => '50.00'],
        ['account_id' => $this->revenue->id, 'credit' => '50.00'],
    ], $this->dagboek, period: $beginbalans);

    expect($entry->period_id)->toBe($beginbalans->id)
        ->and($entry->period->number)->toBe(0);
});

it('weigert een journaalpost in een afgesloten periode en schrijft niets weg', function () {
    Carbon::setTestNow(Carbon::create(2027, 3, 15));
    $voorbijePeriode = $this->fiscalYears->periodForDate(Carbon::create(2027, 1, 10));
    $this->fiscalYears->close($voorbijePeriode, $this->audit);

    $countBefore = JournalEntry::count();

    expect(fn () => $this->ledger->record(Carbon::create(2027, 1, 10), 'Te laat', null, [
        ['account_id' => $this->account->id, 'debit' => '10.00'],
        ['account_id' => $this->revenue->id, 'credit' => '10.00'],
    ], $this->dagboek))->toThrow(InvalidArgumentException::class);

    expect(JournalEntry::count())->toBe($countBefore);
});

it('weigert een journaalpost zonder bedrag, ook met de nieuwe signatuur', function () {
    expect(fn () => $this->ledger->record(now(), 'leeg', null, [
        ['account_id' => $this->account->id, 'debit' => '0.00'],
        ['account_id' => $this->revenue->id, 'credit' => '0.00'],
    ], $this->dagboek))->toThrow(InvalidArgumentException::class);
});

it('weigert een journaalpost die niet in balans is, ook met de nieuwe signatuur', function () {
    expect(fn () => $this->ledger->record(now(), 'scheef', null, [
        ['account_id' => $this->account->id, 'debit' => '10.00'],
        ['account_id' => $this->revenue->id, 'credit' => '9.00'],
    ], $this->dagboek))->toThrow(InvalidArgumentException::class);
});
