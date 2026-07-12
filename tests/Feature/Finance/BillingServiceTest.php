<?php

use App\Enums\ChargeStatus;
use App\Enums\InvoiceStatus;
use App\Models\Charge;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Product;
use App\Services\Finance\BillingService;
use App\Services\Finance\LedgerService;
use Database\Seeders\LedgerAccountSeeder;

beforeEach(function () {
    $this->seed(LedgerAccountSeeder::class);
    $this->billing = app(BillingService::class);
    $this->debtor = Person::create(['first_name' => 'Piet', 'last_name' => 'Betaler']);
});

it('boekt een post direct als journaalpost (debet Debiteuren, credit opbrengst)', function () {
    $revenue = LedgerAccount::query()->where('code', '8000')->firstOrFail();
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie', 'ledger_account_id' => $revenue->id]);

    $charge = $this->billing->createCharge($product, $this->debtor, '120.00', 'Contributie 2026');

    expect($charge->status)->toBe(ChargeStatus::Open)
        ->and((float) $charge->amount)->toBe(120.0);

    $entry = JournalEntry::query()->where('reference', "charge:{$charge->id}")->firstOrFail();
    $lines = $entry->lines()->with('account')->get();

    $debit = $lines->firstWhere(fn ($l) => (float) $l->debit > 0);
    $credit = $lines->firstWhere(fn ($l) => (float) $l->credit > 0);

    expect($debit->account->code)->toBe('1300')
        ->and((float) $debit->debit)->toBe(120.0)
        ->and($credit->account->code)->toBe('8000')
        ->and((float) $credit->credit)->toBe(120.0);
});

it('valt terug op de standaard opbrengstrekening als het product er geen heeft', function () {
    $product = Product::create(['name' => 'Los artikel', 'type' => 'overig']);

    $charge = $this->billing->createCharge($product, $this->debtor, '10.00', 'Iets');
    $entry = JournalEntry::query()->where('reference', "charge:{$charge->id}")->firstOrFail();
    $credit = $entry->lines()->get()->firstWhere(fn ($l) => (float) $l->credit > 0);

    expect($credit->account->code)->toBe('8900');
});

it('bundelt openstaande posten van een betaler tot één factuur', function () {
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $this->billing->createCharge($product, $this->debtor, '100.00', 'Post A');
    $this->billing->createCharge($product, $this->debtor, '25.50', 'Post B');

    $invoice = $this->billing->invoiceOpenCharges($this->debtor);

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Verzonden)
        ->and((float) $invoice->total)->toBe(125.5)
        ->and($invoice->number)->toMatch('/^\d{4}-\d{4}$/')
        ->and($invoice->charges()->count())->toBe(2);

    // Posten zijn nu gefactureerd en gekoppeld.
    expect(Charge::query()->where('status', ChargeStatus::Gefactureerd->value)->count())->toBe(2)
        ->and(Charge::query()->whereNull('invoice_id')->count())->toBe(0);
});

it('factureert alleen de posten van de betreffende betaler', function () {
    $ander = Person::create(['first_name' => 'Klaas', 'last_name' => 'Ander']);
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);

    $this->billing->createCharge($product, $this->debtor, '100.00', 'Van Piet');
    $this->billing->createCharge($product, $ander, '200.00', 'Van Klaas');

    $invoice = $this->billing->invoiceOpenCharges($this->debtor);

    expect((float) $invoice->total)->toBe(100.0)
        ->and(Charge::query()->where('debtor_person_id', $ander->id)->where('status', ChargeStatus::Open->value)->count())->toBe(1);
});

it('geeft null als er niets te factureren valt', function () {
    expect($this->billing->invoiceOpenCharges($this->debtor))->toBeNull();
});

it('weigert een journaalpost die niet in balans is', function () {
    $ledger = app(LedgerService::class);
    $acc = LedgerAccount::query()->where('code', '1300')->firstOrFail();

    expect(fn () => $ledger->record(now(), 'scheef', null, [
        ['account_id' => $acc->id, 'debit' => '10.00'],
        ['account_id' => $acc->id, 'credit' => '9.00'],
    ]))->toThrow(InvalidArgumentException::class);
});
