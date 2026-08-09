<?php

namespace App\Services\Finance;

use App\Enums\ChargeStatus;
use App\Enums\InvoiceStatus;
use App\Models\Charge;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Facturatie-kern (§23): posten aanmaken (met directe journaalpost) en
 * openstaande posten van één betaler bundelen tot een factuur.
 *
 * Boekmoment: bij het aanmaken van de post (accrual) — debet Debiteuren,
 * credit de opbrengstrekening van het product. Facturering bundelt alleen
 * en boekt niet opnieuw. De betaling wordt in de betaal-fase geboekt.
 */
class BillingService
{
    private const string DEBTORS_CODE = '1300';

    private const string DEFAULT_REVENUE_CODE = '8900';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function createCharge(
        Product $product,
        Person $debtor,
        string $amount,
        string $description,
        ?Carbon $dueAt = null,
        ?Model $subject = null,
        ?string $period = null,
    ): Charge {
        return DB::transaction(function () use ($product, $debtor, $amount, $description, $dueAt, $subject, $period): Charge {
            $charge = Charge::create([
                'product_id' => $product->id,
                'debtor_person_id' => $debtor->id,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'amount' => $amount,
                'period' => $period,
                'status' => ChargeStatus::Open,
                'due_at' => $dueAt,
            ]);

            $this->ledger->record(
                date: Carbon::now(),
                description: "Post: {$description}",
                reference: "charge:{$charge->id}",
                lines: [
                    ['account_id' => $this->debtorsAccount()->id, 'debit' => $amount],
                    ['account_id' => $this->revenueAccountFor($product)->id, 'credit' => $amount],
                ],
            );

            $this->audit->log('charge.created', $charge, after: [
                'debtor_person_id' => $debtor->id,
                'product_id' => $product->id,
                'amount' => $amount,
            ]);

            return $charge;
        });
    }

    /**
     * Bundelt alle openstaande, nog niet gefactureerde posten van de betaler
     * tot één factuur. Geeft null als er niets te factureren valt.
     */
    public function invoiceOpenCharges(Person $debtor): ?Invoice
    {
        return DB::transaction(function () use ($debtor): ?Invoice {
            $charges = Charge::query()
                ->open()
                ->where('debtor_person_id', $debtor->id)
                ->whereNull('invoice_id')
                ->get();

            if ($charges->isEmpty()) {
                return null;
            }

            $total = $charges->sum(fn (Charge $c): float => (float) $c->amount);

            $issuedAt = Carbon::now();
            $invoice = Invoice::create([
                'number' => $this->nextNumber($issuedAt),
                'debtor_person_id' => $debtor->id,
                'status' => InvoiceStatus::Verzonden,
                'issued_at' => $issuedAt,
                'due_at' => $issuedAt->copy()->addDays(30),
                'total' => number_format($total, 2, '.', ''),
            ]);

            Charge::query()
                ->whereIn('id', $charges->pluck('id'))
                ->update(['invoice_id' => $invoice->id, 'status' => ChargeStatus::Gefactureerd->value]);

            $this->audit->log('invoice.created', $invoice, after: [
                'debtor_person_id' => $debtor->id,
                'charge_ids' => $charges->pluck('id')->all(),
                'total' => $invoice->total,
            ]);

            return $invoice;
        });
    }

    /**
     * Crediteert een (deel van een) gefactureerde post (§23.6 B3, basis).
     * Boekt de tegenboeking (debet opbrengst, credit Debiteuren) en werkt de
     * factuurstatus bij op basis van hoeveel er in totaal is gecrediteerd.
     */
    public function creditCharge(Charge $charge, string $amount, string $description): void
    {
        if ($charge->invoice_id === null) {
            throw new \InvalidArgumentException('Alleen een reeds gefactureerde post kan worden gecrediteerd.');
        }

        $remaining = round((float) $charge->remainingCreditable(), 2);
        $creditAmount = round((float) $amount, 2);

        if ($creditAmount <= 0.0 || $creditAmount > $remaining) {
            throw new \InvalidArgumentException("Te crediteren bedrag moet tussen 0 en het resterende bedrag (€{$remaining}) liggen.");
        }

        DB::transaction(function () use ($charge, $creditAmount, $description): void {
            $before = ['credited_amount' => $charge->credited_amount, 'status' => $charge->status->value];

            $newCredited = round((float) $charge->credited_amount + $creditAmount, 2);
            $charge->credited_amount = number_format($newCredited, 2, '.', '');
            if ($newCredited >= round((float) $charge->amount, 2)) {
                $charge->status = ChargeStatus::Gecrediteerd;
            }
            $charge->save();

            $this->ledger->record(
                date: Carbon::now(),
                description: "Creditering: {$description}",
                reference: "credit:charge:{$charge->id}",
                lines: [
                    ['account_id' => $this->revenueAccountFor($charge->product)->id, 'debit' => $creditAmount],
                    ['account_id' => $this->debtorsAccount()->id, 'credit' => $creditAmount],
                ],
            );

            $this->recalculateInvoiceStatus($charge->invoice);

            $this->audit->log('charge.credited', $charge, before: $before, after: [
                'credited_amount' => $charge->credited_amount,
                'status' => $charge->status->value,
            ]);
        });
    }

    private function recalculateInvoiceStatus(?Invoice $invoice): void
    {
        if ($invoice === null) {
            return;
        }

        $creditedTotal = round((float) Charge::query()->where('invoice_id', $invoice->id)->sum('credited_amount'), 2);
        $invoiceTotal = round((float) $invoice->total, 2);

        if ($creditedTotal <= 0.0) {
            return;
        }

        $invoice->status = $creditedTotal >= $invoiceTotal ? InvoiceStatus::Gecrediteerd : InvoiceStatus::DeelsGecrediteerd;
        $invoice->save();
    }

    private function nextNumber(Carbon $date): string
    {
        $year = $date->year;
        $sequence = Invoice::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('%d-%04d', $year, $sequence);
    }

    private function debtorsAccount(): LedgerAccount
    {
        return LedgerAccount::query()->where('code', self::DEBTORS_CODE)->firstOr(function (): never {
            throw new \RuntimeException('Grootboekrekening Debiteuren ('.self::DEBTORS_CODE.') ontbreekt; seed het rekeningschema.');
        });
    }

    private function revenueAccountFor(Product $product): LedgerAccount
    {
        if ($product->ledger_account_id !== null && $product->ledgerAccount !== null) {
            return $product->ledgerAccount;
        }

        return LedgerAccount::query()->where('code', self::DEFAULT_REVENUE_CODE)->firstOr(function (): never {
            throw new \RuntimeException('Standaard opbrengstrekening ('.self::DEFAULT_REVENUE_CODE.') ontbreekt; seed het rekeningschema.');
        });
    }
}
