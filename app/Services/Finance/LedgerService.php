<?php

namespace App\Services\Finance;

use App\Models\Dagboek;
use App\Models\JournalEntry;
use App\Models\Period;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Schrijft journaalposten weg voor de lichte dubbele boekhouding (§23.3).
 * Een post moet in balans zijn: de som van debet is gelijk aan de som van
 * credit, en groter dan nul. Elke post hoort bij een dagboek en een periode.
 */
class LedgerService
{
    public function __construct(private readonly FiscalYearService $fiscalYears) {}

    /**
     * @param  list<array{account_id: int, debit?: numeric-string|float|int, credit?: numeric-string|float|int}>  $lines
     */
    public function record(Carbon $date, string $description, ?string $reference, array $lines, Dagboek $dagboek, ?Period $period = null): JournalEntry
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as $line) {
            $totalDebit += (float) ($line['debit'] ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }

        if (round($totalDebit, 2) <= 0.0) {
            throw new \InvalidArgumentException('Een journaalpost moet een bedrag groter dan nul hebben.');
        }
        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new \InvalidArgumentException('Journaalpost is niet in balans: debet en credit lopen uiteen.');
        }

        $resolvedPeriod = $period ?? $this->fiscalYears->periodForDate($date);

        if ($resolvedPeriod->isClosed()) {
            throw new \InvalidArgumentException("Periode [{$resolvedPeriod->label()}] is afgesloten; er kunnen geen nieuwe journaalposten meer in geboekt worden.");
        }

        return DB::transaction(function () use ($date, $description, $reference, $lines, $dagboek, $resolvedPeriod): JournalEntry {
            $entry = JournalEntry::create([
                'dagboek_id' => $dagboek->id,
                'period_id' => $resolvedPeriod->id,
                'date' => $date,
                'description' => $description,
                'reference' => $reference,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'ledger_account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                ]);
            }

            return $entry;
        });
    }
}
