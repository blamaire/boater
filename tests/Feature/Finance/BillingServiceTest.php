<?php

use App\Enums\ChargeStatus;
use App\Enums\InvoiceStatus;
use App\Models\BtwCode;
use App\Models\Charge;
use App\Models\Dagboek;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Person;
use App\Models\Product;
use App\Services\Finance\BillingService;
use App\Services\Finance\LedgerService;
use Database\Seeders\BtwCodeSeeder;
use Database\Seeders\DagboekSeeder;
use Database\Seeders\LedgerAccountSeeder;

beforeEach(function () {
    $this->seed(LedgerAccountSeeder::class);
    $this->seed(DagboekSeeder::class);
    $this->seed(BtwCodeSeeder::class);
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
    expect($entry->dagboek->type->value)->toBe('verkoop');
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

it('splitst een post met een BTW-code in netto en BTW', function () {
    $revenue = LedgerAccount::query()->where('code', '8000')->firstOrFail();
    $product = Product::create(['name' => 'Advertentie', 'type' => 'advertentie', 'ledger_account_id' => $revenue->id]);
    $btwCode = BtwCode::query()->where('name', '21% hoog tarief')->firstOrFail();
    $product->update(['btw_code_id' => $btwCode->id]);

    $charge = $this->billing->createCharge($product, $this->debtor, '121.00', 'Advertentie met BTW');

    expect($charge->btw_code_id)->toBe($btwCode->id);

    $entry = JournalEntry::query()->where('reference', "charge:{$charge->id}")->firstOrFail();
    $lines = $entry->lines()->with('account')->get();

    expect($lines)->toHaveCount(3);
    $debtorLine = $lines->firstWhere(fn ($l) => (float) $l->debit > 0);
    $revenueLine = $lines->first(fn ($l) => $l->account->code === '8000');
    $btwLine = $lines->first(fn ($l) => $l->account->code === '1600');

    expect((float) $debtorLine->debit)->toBe(121.0)
        ->and((float) $revenueLine->credit)->toBe(100.0)
        ->and((float) $btwLine->credit)->toBe(21.0);
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

it('crediteert een post volledig via een nieuwe creditfactuur, zonder de oorspronkelijke te wijzigen', function () {
    $revenue = LedgerAccount::query()->where('code', '8000')->firstOrFail();
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie', 'ledger_account_id' => $revenue->id]);
    $charge = $this->billing->createCharge($product, $this->debtor, '100.00', 'Post A');
    $invoice = $this->billing->invoiceOpenCharges($this->debtor);

    $creditInvoice = $this->billing->creditCharge($charge->fresh(), '100.00', 'Foutieve inschrijving');

    // De oorspronkelijke factuur/post blijven ongewijzigd — een factuur is een vastliggend document.
    $charge->refresh();
    $invoice->refresh();
    expect($charge->status)->toBe(ChargeStatus::Gefactureerd)
        ->and((float) $charge->amount)->toBe(100.0)
        ->and($invoice->status)->toBe(InvoiceStatus::Verzonden)
        ->and((float) $invoice->total)->toBe(100.0);

    // Berekend resterend/gecrediteerd bedrag klopt via de credits()-relatie.
    expect((float) $charge->creditedAmount())->toBe(100.0)
        ->and($charge->remainingCreditable())->toBe('0.00');

    // De nieuwe creditfactuur.
    expect((float) $creditInvoice->total)->toBe(-100.0)
        ->and($creditInvoice->status)->toBe(InvoiceStatus::Verzonden)
        ->and($creditInvoice->number)->not->toBe($invoice->number);

    $creditCharge = $creditInvoice->charges()->firstOrFail();
    expect((float) $creditCharge->amount)->toBe(-100.0)
        ->and($creditCharge->subject_type)->toBe(Charge::class)
        ->and($creditCharge->subject_id)->toBe($charge->id);

    $entry = JournalEntry::query()->where('reference', "credit:charge:{$creditCharge->id}")->firstOrFail();
    expect($entry->dagboek->type->value)->toBe('verkoop');
    $lines = $entry->lines()->with('account')->get();
    $debit = $lines->firstWhere(fn ($l) => (float) $l->debit > 0);
    $credit = $lines->firstWhere(fn ($l) => (float) $l->credit > 0);

    expect($debit->account->code)->toBe('8000')
        ->and((float) $debit->debit)->toBe(100.0)
        ->and($credit->account->code)->toBe('1300')
        ->and((float) $credit->credit)->toBe(100.0);
});

it('crediteert een post gedeeltelijk en houdt het resterende bedrag correct bij', function () {
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $charge = $this->billing->createCharge($product, $this->debtor, '100.00', 'Post A');
    $this->billing->invoiceOpenCharges($this->debtor);

    $creditInvoice = $this->billing->creditCharge($charge->fresh(), '40.00', 'Gedeeltelijke restitutie');

    expect((float) $creditInvoice->total)->toBe(-40.0);

    $charge->refresh();
    expect($charge->status)->toBe(ChargeStatus::Gefactureerd)
        ->and((float) $charge->creditedAmount())->toBe(40.0)
        ->and($charge->remainingCreditable())->toBe('60.00');
});

it('crediteert een BTW-post proportioneel gesplitst', function () {
    $revenue = LedgerAccount::query()->where('code', '8000')->firstOrFail();
    $product = Product::create(['name' => 'Advertentie', 'type' => 'advertentie', 'ledger_account_id' => $revenue->id]);
    $btwCode = BtwCode::query()->where('name', '21% hoog tarief')->firstOrFail();
    $product->update(['btw_code_id' => $btwCode->id]);

    $charge = $this->billing->createCharge($product, $this->debtor, '121.00', 'Advertentie met BTW');
    $this->billing->invoiceOpenCharges($this->debtor);

    $creditInvoice = $this->billing->creditCharge($charge->fresh(), '121.00', 'Volledige creditering');

    $creditCharge = $creditInvoice->charges()->firstOrFail();
    $entry = JournalEntry::query()->where('reference', "credit:charge:{$creditCharge->id}")->firstOrFail();
    $lines = $entry->lines()->with('account')->get();

    expect($lines)->toHaveCount(3);
    $revenueLine = $lines->first(fn ($l) => $l->account->code === '8000');
    $btwLine = $lines->first(fn ($l) => $l->account->code === '1600');

    expect((float) $revenueLine->debit)->toBe(100.0)
        ->and((float) $btwLine->debit)->toBe(21.0);
});

it('twee opeenvolgende gedeeltelijke crediteringen leveren twee aparte facturen op', function () {
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $charge = $this->billing->createCharge($product, $this->debtor, '100.00', 'Post A');
    $this->billing->invoiceOpenCharges($this->debtor);

    $eerste = $this->billing->creditCharge($charge->fresh(), '40.00', 'Eerste deel');
    $tweede = $this->billing->creditCharge($charge->fresh(), '60.00', 'Tweede deel');

    expect($eerste->id)->not->toBe($tweede->id)
        ->and((float) $eerste->total)->toBe(-40.0)
        ->and((float) $tweede->total)->toBe(-60.0);

    $charge->refresh();
    expect((float) $charge->creditedAmount())->toBe(100.0)
        ->and($charge->remainingCreditable())->toBe('0.00');
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

it('weigert crediteren van een creditregel zelf', function () {
    $product = Product::create(['name' => 'Contributie', 'type' => 'contributie']);
    $charge = $this->billing->createCharge($product, $this->debtor, '100.00', 'Post A');
    $this->billing->invoiceOpenCharges($this->debtor);
    $creditInvoice = $this->billing->creditCharge($charge->fresh(), '100.00', 'Creditering');
    $creditCharge = $creditInvoice->charges()->firstOrFail();

    expect(fn () => $this->billing->creditCharge($creditCharge, '10.00', 'Dubbel crediteren'))
        ->toThrow(InvalidArgumentException::class);
});

it('weigert een journaalpost die niet in balans is', function () {
    $ledger = app(LedgerService::class);
    $acc = LedgerAccount::query()->where('code', '1300')->firstOrFail();
    $dagboek = Dagboek::query()->where('type', 'memoriaal')->firstOrFail();

    expect(fn () => $ledger->record(now(), 'scheef', null, [
        ['account_id' => $acc->id, 'debit' => '10.00'],
        ['account_id' => $acc->id, 'credit' => '9.00'],
    ], $dagboek))->toThrow(InvalidArgumentException::class);
});
