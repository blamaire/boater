<?php

namespace App\Livewire\Admin;

use App\Models\Charge;
use App\Models\Invoice;
use App\Services\Finance\BillingService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Factuurdetail (§23): toont de gebundelde posten van een factuur en laat een
 * beheerder een post geheel of gedeeltelijk crediteren (§23.6 B3, basis).
 * Permissie: `invoices.manage`.
 */
#[Layout('layouts.app', ['header' => 'Factuur'])]
class FactuurDetail extends Component
{
    public Invoice $invoice;

    /** @var array<int, string> */
    public array $creditAmount = [];

    /** @var array<int, string> */
    public array $creditReason = [];

    public ?string $statusMessage = null;

    public ?int $lastCreditInvoiceId = null;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    public function creditCharge(int $chargeId, BillingService $billing): void
    {
        $charge = Charge::query()->where('invoice_id', $this->invoice->id)->findOrFail($chargeId);
        $remaining = round((float) $charge->remainingCreditable(), 2);

        $data = $this->validate([
            "creditAmount.{$chargeId}" => [
                'required', 'numeric', 'min:0.01',
                function (string $attribute, mixed $value, \Closure $fail) use ($remaining): void {
                    if ((float) $value > $remaining) {
                        $fail('Mag niet meer zijn dan het resterende bedrag (€ '.number_format($remaining, 2, ',', '.').').');
                    }
                },
            ],
            "creditReason.{$chargeId}" => ['required', 'string', 'max:200'],
        ]);

        $creditInvoice = $billing->creditCharge($charge, $data['creditAmount'][$chargeId], $data['creditReason'][$chargeId]);

        unset($this->creditAmount[$chargeId], $this->creditReason[$chargeId]);
        $this->lastCreditInvoiceId = $creditInvoice->id;
        $this->statusMessage = 'Creditfactuur '.$creditInvoice->number.' aangemaakt (-€ '.
            number_format(-(float) $creditInvoice->total, 2, ',', '.').').';
    }

    public function render(): View
    {
        $this->invoice->load(['debtor', 'charges.product', 'charges.credits.invoice']);

        foreach ($this->invoice->charges as $charge) {
            $remaining = $charge->remainingCreditable();
            if ((float) $remaining > 0 && ! isset($this->creditAmount[$charge->id])) {
                $this->creditAmount[$charge->id] = $remaining;
            }
        }

        return view('livewire.admin.factuur-detail');
    }
}
