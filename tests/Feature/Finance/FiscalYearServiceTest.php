<?php

use App\Models\AuditEntry;
use App\Models\FiscalYear;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\FiscalYearService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->fiscalYears = app(FiscalYearService::class);
    $this->audit = app(AuditLogger::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('maakt lazy een boekjaar met dertien periodes aan bij de eerste aanroep voor een nieuw jaar', function () {
    expect(FiscalYear::query()->where('year', 2031)->exists())->toBeFalse();

    $period = $this->fiscalYears->periodForDate(Carbon::create(2031, 5, 15));

    $fiscalYear = FiscalYear::query()->where('year', 2031)->firstOrFail();
    expect($fiscalYear->periods()->count())->toBe(13)
        ->and($period->number)->toBe(5)
        ->and($period->fiscal_year_id)->toBe($fiscalYear->id);
});

it('is idempotent: herhaalde aanroep voor hetzelfde jaar maakt geen dubbele rijen', function () {
    $this->fiscalYears->periodForDate(Carbon::create(2032, 3, 1));
    $this->fiscalYears->periodForDate(Carbon::create(2032, 3, 1));
    $this->fiscalYears->periodForDate(Carbon::create(2032, 11, 1));

    expect(FiscalYear::query()->where('year', 2032)->count())->toBe(1);
    $fiscalYear = FiscalYear::query()->where('year', 2032)->firstOrFail();
    expect($fiscalYear->periods()->count())->toBe(13);
});

it('lost de eerste en laatste dag van een maand naar de juiste periode op, nooit periode 0', function () {
    $eersteDag = $this->fiscalYears->periodForDate(Carbon::create(2033, 6, 1));
    $laatsteDag = $this->fiscalYears->periodForDate(Carbon::create(2033, 6, 30));

    expect($eersteDag->number)->toBe(6)
        ->and($eersteDag->id)->toBe($laatsteDag->id)
        ->and($eersteDag->number)->not->toBe(0);
});

it('openingBalancePeriod geeft altijd periode 0, ongeacht het jaar', function () {
    $period = $this->fiscalYears->openingBalancePeriod(2034);

    expect($period->number)->toBe(0)
        ->and($period->isOpeningBalance())->toBeTrue()
        ->and($period->label())->toBe('Beginbalans');
});

it('close zet closed_at en schrijft een audit_entries-rij met action period.closed', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 15));
    $period = $this->fiscalYears->periodForDate(Carbon::create(2026, 1, 10));

    expect($period->isClosed())->toBeFalse();

    $this->fiscalYears->close($period, $this->audit);

    $period->refresh();
    expect($period->isClosed())->toBeTrue()
        ->and($period->closed_at)->not->toBeNull();

    $entry = AuditEntry::query()->where('action', 'period.closed')->where('subject_id', $period->id)->firstOrFail();
    expect($entry->after['number'])->toBe(1)
        ->and($entry->after['fiscal_year'])->toBe(2026);
});

it('close op een reeds gesloten periode is idempotent, geen tweede audit-entry', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 15));
    $period = $this->fiscalYears->periodForDate(Carbon::create(2026, 1, 10));

    $this->fiscalYears->close($period, $this->audit);
    $this->fiscalYears->close($period->fresh(), $this->audit);

    expect(AuditEntry::query()->where('action', 'period.closed')->where('subject_id', $period->id)->count())->toBe(1);
});

it('weigert het afsluiten van de lopende of een toekomstige periode', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 15));

    $lopend = $this->fiscalYears->periodForDate(Carbon::create(2026, 3, 1));
    $toekomstig = $this->fiscalYears->periodForDate(Carbon::create(2026, 12, 1));

    expect(fn () => $this->fiscalYears->close($lopend, $this->audit))->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->fiscalYears->close($toekomstig, $this->audit))->toThrow(InvalidArgumentException::class);

    expect($lopend->fresh()->isClosed())->toBeFalse()
        ->and($toekomstig->fresh()->isClosed())->toBeFalse();
});

it('staat het afsluiten van een reeds voorbije periode toe', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 15));

    $voorbij = $this->fiscalYears->periodForDate(Carbon::create(2026, 1, 1));

    $this->fiscalYears->close($voorbij, $this->audit);

    expect($voorbij->fresh()->isClosed())->toBeTrue();
});

it('staat het afsluiten van periode 0 altijd toe, ongeacht de datum', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 15));

    $beginbalans = $this->fiscalYears->openingBalancePeriod(2026);

    $this->fiscalYears->close($beginbalans, $this->audit);

    expect($beginbalans->fresh()->isClosed())->toBeTrue();
});
