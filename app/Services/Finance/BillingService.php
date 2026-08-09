<?php

namespace App\Services\Finance;

use App\Enums\ChargeStatus;
use App\Enums\DagboekType;
use App\Enums\InvoiceStatus;
use App\Models\BtwCode;
use App\Models\Charge;
use App\Models\Dagboek;
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
 * credit de opbrengstrekening van het product (en, bij een gekoppelde
 * BTW-code, credit de BTW-rekening voor het BTW-deel). Facturering bundelt
 * alleen en boekt niet opnieuw. De betaling wordt in de betaal-fase geboekt.
 *
 * Crediteren (§23.6 B3) levert een nieuwe factuur op — de oorspronkelijke
 * factuur/post is een vastliggend document en wordt nooit gemuteerd.
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
            $now = Carbon::now();
            $btwCode = $this->activeBtwCodeFor($product, $now);

            $charge = Charge::create([
                'product_id' => $product->id,
                'btw_code_id' => $btwCode?->id,
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
                date: $now,
                description: "Post: {$description}",
                reference: "charge:{$charge->id}",
                lines: [
                    ['account_id' => $this->debtorsAccount()->id, 'debit' => $amount],
                    ...$this->revenueLines($product, $amount, 'credit', $btwCode),
                ],
                dagboek: $this->verkoopDagboek(),
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
     * De oorspronkelijke factuur/post is een vastliggend document en wordt
     * nooit aangepast: dit maakt een nieuwe creditfactuur met een negatieve
     * post, met een tegenboeking op dezelfde grootboek-/BTW-rekeningen als
     * de oorspronkelijke post gebruikte.
     */
    public function creditCharge(Charge $originalCharge, string $amount, string $description): Invoice
    {
        if ($originalCharge->invoice_id === null) {
            throw new \InvalidArgumentException('Alleen een reeds gefactureerde post kan worden gecrediteerd.');
        }
        if ($originalCharge->subject_type === Charge::class) {
            throw new \InvalidArgumentException('Een creditregel kan niet zelf gecrediteerd worden.');
        }

        $remaining = round((float) $originalCharge->remainingCreditable(), 2);
        $creditAmount = round((float) $amount, 2);

        if ($creditAmount <= 0.0 || $creditAmount > $remaining) {
            throw new \InvalidArgumentException("Te crediteren bedrag moet tussen 0 en het resterende bedrag (€{$remaining}) liggen.");
        }

        return DB::transaction(function () use ($originalCharge, $creditAmount, $description): Invoice {
            $now = Carbon::now();
            $creditAmountStr = number_format($creditAmount, 2, '.', '');

            $creditInvoice = Invoice::create([
                'number' => $this->nextNumber($now),
                'debtor_person_id' => $originalCharge->debtor_person_id,
                'status' => InvoiceStatus::Verzonden,
                'issued_at' => $now,
                'due_at' => $now->copy()->addDays(30),
                'total' => number_format(-$creditAmount, 2, '.', ''),
            ]);

            $creditCharge = Charge::create([
                'product_id' => $originalCharge->product_id,
                'btw_code_id' => $originalCharge->btw_code_id,
                'debtor_person_id' => $originalCharge->debtor_person_id,
                'subject_type' => Charge::class,
                'subject_id' => $originalCharge->id,
                'description' => $description,
                'amount' => number_format(-$creditAmount, 2, '.', ''),
                'status' => ChargeStatus::Gefactureerd,
                'invoice_id' => $creditInvoice->id,
            ]);

            $this->ledger->record(
                date: $now,
                description: "Creditering: {$description}",
                reference: "credit:charge:{$creditCharge->id}",
                lines: [
                    ['account_id' => $this->debtorsAccount()->id, 'credit' => $creditAmountStr],
                    ...$this->revenueLines($originalCharge->product, $creditAmountStr, 'debit', $originalCharge->btwCode),
                ],
                dagboek: $this->verkoopDagboek(),
            );

            $this->audit->log('charge.created', $creditCharge, after: [
                'debtor_person_id' => $originalCharge->debtor_person_id,
                'subject_type' => Charge::class,
                'subject_id' => $originalCharge->id,
                'amount' => $creditCharge->amount,
            ]);
            $this->audit->log('invoice.created', $creditInvoice, after: [
                'debtor_person_id' => $originalCharge->debtor_person_id,
                'charge_ids' => [$creditCharge->id],
                'total' => $creditInvoice->total,
            ]);

            return $creditInvoice;
        });
    }

    /**
     * Journaalregel(s) voor de opbrengstkant van een (bruto) bedrag: zonder
     * BTW-code één regel op de opbrengstrekening, mét een actieve BTW-code
     * gesplitst in een netto-regel en een BTW-regel (bedrag wordt als
     * inclusief BTW behandeld).
     *
     * @return list<array{account_id: int, debit?: string, credit?: string}>
     */
    private function revenueLines(Product $product, string $grossAmount, string $side, ?BtwCode $btwCode): array
    {
        $revenueAccountId = $this->revenueAccountFor($product)->id;

        if ($btwCode === null) {
            return [['account_id' => $revenueAccountId, $side => $grossAmount]];
        }

        $pct = (float) $btwCode->percentage;
        $gross = (float) $grossAmount;
        $net = round($gross / (1 + $pct / 100), 2);
        $btw = round($gross - $net, 2);

        $lines = [['account_id' => $revenueAccountId, $side => number_format($net, 2, '.', '')]];
        if ($btw > 0.0) {
            $lines[] = ['account_id' => $btwCode->ledger_account_id, $side => number_format($btw, 2, '.', '')];
        }

        return $lines;
    }

    private function activeBtwCodeFor(Product $product, Carbon $date): ?BtwCode
    {
        $code = $product->btwCode;
        if ($code === null || ! $code->isActiveOn($date)) {
            return null;
        }

        return $code;
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

    private function verkoopDagboek(): Dagboek
    {
        return Dagboek::query()->where('type', DagboekType::Verkoop->value)->firstOr(function (): never {
            throw new \RuntimeException('Dagboek Verkoop ontbreekt; seed de dagboeken.');
        });
    }
}
