<?php

namespace App\Livewire\Admin;

use App\Models\Dagboek;
use App\Models\LedgerAccount;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\FiscalYearService;
use App\Services\Finance\LedgerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Overzicht van de journaalposten in een dagboek, met een formulier om
 * handmatig een journaalpost te boeken (bv. een beginbalans of een correctie
 * — automatische boekingen lopen via `BillingService`/`ContributionRunService`).
 * Permissie: `dagboeken.manage` (zelfde als het dagboekbeheer zelf).
 */
#[Layout('layouts.app', ['header' => 'Dagboek'])]
class DagboekDetail extends Component
{
    public Dagboek $dagboek;

    public string $date = '';

    public string $description = '';

    public ?string $reference = null;

    public bool $isOpeningBalance = false;

    /** @var array<int, array{account_id: ?int, debit: string, credit: string}> */
    public array $lines = [];

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(Dagboek $dagboek): void
    {
        $this->dagboek = $dagboek;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['description', 'reference', 'isOpeningBalance']);
        $this->date = now()->toDateString();
        $this->lines = [
            ['account_id' => null, 'debit' => '', 'credit' => ''],
            ['account_id' => null, 'debit' => '', 'credit' => ''],
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['account_id' => null, 'debit' => '', 'credit' => ''];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 2) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(LedgerService $ledger, FiscalYearService $fiscalYears, AuditLogger $audit): void
    {
        abort_unless(auth()->user()?->can('dagboeken.manage'), 403);

        $data = $this->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:150'],
            'lines' => ['array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:ledger_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $hasLineErrors = false;
        foreach ($data['lines'] as $index => $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if (($debit > 0) === ($credit > 0)) {
                $this->addError("lines.{$index}.debit", 'Vul per regel precies één van debet of credit in (niet beide, niet geen van beide).');
                $hasLineErrors = true;
            }
        }

        if ($hasLineErrors) {
            return;
        }

        $period = $this->isOpeningBalance
            ? $fiscalYears->openingBalancePeriod(Carbon::parse($data['date'])->year)
            : null;

        try {
            $entry = DB::transaction(function () use ($data, $ledger, $audit, $period) {
                $entry = $ledger->record(
                    date: Carbon::parse($data['date']),
                    description: $data['description'],
                    reference: $data['reference'],
                    lines: array_map(fn (array $line): array => [
                        'account_id' => (int) $line['account_id'],
                        'debit' => $line['debit'] === '' || $line['debit'] === null ? 0 : $line['debit'],
                        'credit' => $line['credit'] === '' || $line['credit'] === null ? 0 : $line['credit'],
                    ], $data['lines']),
                    dagboek: $this->dagboek,
                    period: $period,
                );

                $audit->log('journal_entry.created', $entry, after: [
                    'dagboek_id' => $this->dagboek->id,
                    'period_id' => $entry->period_id,
                    'description' => $entry->description,
                ]);

                return $entry;
            });
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->statusMessage = "Journaalpost [{$entry->description}] aangemaakt.";
        $this->errorMessage = null;
        $this->resetForm();
        $this->dispatch('close-modal', 'journaalpost-form');
    }

    public function render(): View
    {
        return view('livewire.admin.dagboek-detail', [
            'entries' => $this->dagboek->journalEntries()
                ->with(['lines.account', 'period'])
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get(),
            'ledgerAccounts' => LedgerAccount::query()->orderBy('code')->get(),
        ]);
    }
}
