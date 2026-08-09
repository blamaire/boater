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

it('crediteert een post volledig en zet zowel post als factuur op Gecrediteerd', function () {
    $revenue = LedgerAccount::query()->where('code', '8000')->firstOrFail();
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie', 'ledger_account_id' => $revenue->id]);
    $charge = $this->billing->createCharge($product, $this->debtor, '100.00', 'Post A');
    $invoice = $this->billing->invoiceOpenCharges($this->debtor);

    $this->billing->creditCharge($charge->fresh(), '100.00', 'Foutieve inschrijving');

    $charge->refresh();
    $invoice->refresh();

    expect($charge->status)->toBe(ChargeStatus::Gecrediteerd)
        ->and((float) $charge->credited_amount)->toBe(100.0)
        ->and($invoice->status)->toBe(InvoiceStatus::Gecrediteerd);

    $entry = JournalEntry::query()->where('reference', "credit:charge:{$charge->id}")->firstOrFail();
    $lines = $entry->lines()->with('account')->get();
    $debit = $lines->firstWhere(fn ($l) => (float) $l->debit > 0);
    $credit = $lines->firstWhere(fn ($l) => (float) $l->credit > 0);

    expect($debit->account->code)->toBe('8000')
        ->and((float) $debit->debit)->toBe(100.0)
        ->and($credit->account->code)->toBe('1300')
        ->and((float) $credit->credit)->toBe(100.0);
});

it('crediteert een post gedeeltelijk en zet de factuur op Deels gecrediteerd', function () {
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $charge = $this->billing->createCharge($product, $this->debtor, '100.00', 'Post A');
    $invoice = $this->billing->invoiceOpenCharges($this->debtor);

    $this->billing->creditCharge($charge->fresh(), '40.00', 'Gedeeltelijke restitutie');

    $charge->refresh();
    $invoice->refresh();

    expect($charge->status)->toBe(ChargeStatus::Gefactureerd)
        ->and((float) $charge->credited_amount)->toBe(40.0)
        ->and($charge->remainingCreditable())->toBe('60.00')
        ->and($invoice->status)->toBe(InvoiceStatus::DeelsGecrediteerd);
});

it('weigert crediteren van meer dan het resterende bedrag', function () {
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $charge = $this->billing->createCharge($product, $this->debtor, '100.00', 'Post A');
    $this->billing->invoiceOpenCharges($this->debtor);

    expect(fn () => $this->billing->creditCharge($charge->fresh(), '150.00', 'Te veel'))
        ->toThrow(InvalidArgumentException::class);
});

it('weigert crediteren van een nog niet gefactureerde post', function () {
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $charge = $this->billing->createCharge($product, $this->debtor, '100.00', 'Post A');

    expect(fn () => $this->billing->creditCharge($charge, '100.00', 'Te vroeg'))
        ->toThrow(InvalidArgumentException::class);
});

it('weigert een journaalpost die niet in balans is', function () {
    $ledger = app(LedgerService::class);
    $acc = LedgerAccount::query()->where('code', '1300')->firstOrFail();

    expect(fn () => $ledger->record(now(), 'scheef', null, [
        ['account_id' => $acc->id, 'debit' => '10.00'],
        ['account_id' => $acc->id, 'credit' => '9.00'],
    ]))->toThrow(InvalidArgumentException::class);
});
