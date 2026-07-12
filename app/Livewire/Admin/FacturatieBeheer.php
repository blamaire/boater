<?php

namespace App\Livewire\Admin;

use App\Models\Charge;
use App\Models\Invoice;
use App\Models\Person;
use App\Models\Product;
use App\Services\Finance\BillingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Facturatie-beheer (§23): te factureren posten aanmaken en de openstaande
 * posten van een betaler bundelen tot een factuur. Permissie: `invoices.manage`.
 */
#[Layout('layouts.app', ['header' => 'Facturatie'])]
class FacturatieBeheer extends Component
{
    public ?int $chargeDebtorId = null;

    public ?int $chargeProductId = null;

    public string $chargeAmount = '';

    public string $chargeDescription = '';

    public ?string $chargeDueAt = null;

    public ?string $statusMessage = null;

    /**
     * Bij het kiezen van een product: bedrag en omschrijving voorvullen op basis
     * van de geldende prijs.
     */
    public function updatedChargeProductId(?int $value): void
    {
        if ($value === null) {
            return;
        }

        $product = Product::query()->find($value);
        if ($product === null) {
            return;
        }

        $price = $product->currentPrice();
        if ($price !== null) {
            $this->chargeAmount = (string) $price->amount;
        }
        if ($this->chargeDescription === '') {
            $this->chargeDescription = $product->name;
        }
    }

    public function addCharge(BillingService $billing): void
    {
        $data = $this->validate([
            'chargeDebtorId' => ['required', 'integer', 'exists:persons,id'],
            'chargeProductId' => ['required', 'integer', 'exists:products,id'],
            'chargeAmount' => ['required', 'numeric', 'min:0.01'],
            'chargeDescription' => ['required', 'string', 'max:200'],
            'chargeDueAt' => ['nullable', 'date'],
        ]);

        $billing->createCharge(
            product: Product::query()->findOrFail($data['chargeProductId']),
            debtor: Person::query()->findOrFail($data['chargeDebtorId']),
            amount: $data['chargeAmount'],
            description: $data['chargeDescription'],
            dueAt: $data['chargeDueAt'] !== null ? Carbon::parse($data['chargeDueAt']) : null,
        );

        $this->reset(['chargeProductId', 'chargeAmount', 'chargeDescription', 'chargeDueAt']);
        $this->statusMessage = 'Post toegevoegd en geboekt.';
    }

    public function invoiceDebtor(int $debtorId, BillingService $billing): void
    {
        $debtor = Person::query()->findOrFail($debtorId);
        $invoice = $billing->invoiceOpenCharges($debtor);

        $this->statusMessage = $invoice !== null
            ? "Factuur {$invoice->number} aangemaakt (€ ".number_format((float) $invoice->total, 2, ',', '.').').'
            : 'Geen openstaande posten om te factureren.';
    }

    public function render(): View
    {
        $openCharges = Charge::query()
            ->open()
            ->whereNull('invoice_id')
            ->with(['product', 'debtor'])
            ->orderBy('debtor_person_id')
            ->get()
            ->groupBy('debtor_person_id');

        return view('livewire.admin.facturatie-beheer', [
            'openChargesByDebtor' => $openCharges,
            'invoices' => Invoice::query()->with('debtor')->orderByDesc('id')->limit(50)->get(),
            'persons' => Person::query()->orderBy('last_name')->orderBy('first_name')->get(),
            'products' => Product::query()->orderBy('name')->get(),
        ]);
    }
}
