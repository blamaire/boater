<?php

namespace App\Services\Finance;

use App\Models\FiscalYear;
use App\Models\Period;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Boekjaren/periodes worden lazy aangemaakt: een boekjaar is altijd een
 * kalenderjaar (jan-dec) met dertien periodes (0 = beginbalans, 1-12 =
 * januari t/m december). Er is bewust geen apart "nieuw boekjaar"-scherm.
 */
class FiscalYearService
{
    public function periodForDate(Carbon $date): Period
    {
        $fiscalYear = $this->ensureFiscalYear($date->year);

        return $fiscalYear->periods()->where('number', $date->month)->firstOrFail();
    }

    public function openingBalancePeriod(int $year): Period
    {
        $fiscalYear = $this->ensureFiscalYear($year);

        return $fiscalYear->periods()->where('number', 0)->firstOrFail();
    }

    public function close(Period $period, AuditLogger $audit): void
    {
        if ($period->isClosed()) {
            return;
        }

        if (! $period->isOpeningBalance() && ! $period->end_date->isPast()) {
            throw new \InvalidArgumentException('Alleen periodes die al voorbij zijn kunnen worden afgesloten (voorkomt dat de lopende boekhoudmaand per ongeluk op slot gaat).');
        }

        $period->update(['closed_at' => Carbon::now()]);

        $audit->log('period.closed', $period, after: [
            'fiscal_year' => $period->fiscalYear->year,
            'number' => $period->number,
            'closed_at' => $period->closed_at->toIso8601String(),
        ]);
    }

    private function ensureFiscalYear(int $year): FiscalYear
    {
        return DB::transaction(function () use ($year): FiscalYear {
            $fiscalYear = FiscalYear::query()->firstOrCreate(['year' => $year]);

            if ($fiscalYear->periods()->count() < 13) {
                for ($number = 0; $number <= 12; $number++) {
                    [$start, $end] = $this->rangeFor($year, $number);

                    Period::query()->updateOrCreate(
                        ['fiscal_year_id' => $fiscalYear->id, 'number' => $number],
                        ['start_date' => $start, 'end_date' => $end],
                    );
                }
            }

            return $fiscalYear;
        });
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function rangeFor(int $year, int $number): array
    {
        if ($number === 0) {
            $marker = Carbon::create($year, 1, 1)->subDay();

            return [$marker, $marker];
        }

        $start = Carbon::create($year, $number, 1);

        return [$start, $start->copy()->endOfMonth()];
    }
}
