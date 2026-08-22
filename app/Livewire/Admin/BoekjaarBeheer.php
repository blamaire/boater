<?php

namespace App\Livewire\Admin;

use App\Models\FiscalYear;
use App\Models\Period;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\FiscalYearService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Overzicht van het lopende boekjaar en zijn dertien periodes (0 =
 * beginbalans, 1-12 = januari t/m december), met een actie om een periode
 * die al voorbij is af te sluiten/vergrendelen. Boekjaren/periodes worden
 * lazy aangemaakt — er is bewust geen apart "nieuw boekjaar"-scherm.
 * Permissie: `boekjaren.manage`.
 */
#[Layout('layouts.app', ['header' => 'Boekjaren'])]
class BoekjaarBeheer extends Component
{
    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(FiscalYearService $fiscalYears): void
    {
        $fiscalYears->periodForDate(now());
    }

    public function close(int $periodId, FiscalYearService $fiscalYears, AuditLogger $audit): void
    {
        $period = Period::query()->findOrFail($periodId);

        try {
            $fiscalYears->close($period, $audit);
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->errorMessage = null;
        $this->statusMessage = "Periode [{$period->label()}] afgesloten.";
    }

    public function render(): View
    {
        $fiscalYear = FiscalYear::query()->where('year', now()->year)->with('periods')->firstOrFail();

        return view('livewire.admin.boekjaar-beheer', [
            'fiscalYear' => $fiscalYear,
            'periods' => $fiscalYear->periods->sortBy('number')->values(),
        ]);
    }
}
