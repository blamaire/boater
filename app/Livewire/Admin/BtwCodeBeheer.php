<?php

namespace App\Livewire\Admin;

use App\Models\BtwCode;
use App\Models\Charge;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor BTW-codes: percentage, gekoppelde grootboekrekeningen
 * (af te dragen bij verkoop, voor te vorderen bij inkoop — beide tegelijk
 * te koppelen) en geldigheidsperiode. Permissie: `btw_codes.manage`.
 */
#[Layout('layouts.app', ['header' => 'BTW-codes'])]
class BtwCodeBeheer extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $percentage = '';

    public ?int $afTeDragenLedgerAccountId = null;

    public ?int $voorTeVorderenLedgerAccountId = null;

    public ?string $validFrom = null;

    public ?string $validUntil = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->validFrom = now()->toDateString();
    }

    public function edit(int $id): void
    {
        $code = BtwCode::query()->findOrFail($id);
        $this->editingId = $code->id;
        $this->name = $code->name;
        $this->percentage = (string) $code->percentage;
        $this->afTeDragenLedgerAccountId = $code->af_te_dragen_ledger_account_id;
        $this->voorTeVorderenLedgerAccountId = $code->voor_te_vorderen_ledger_account_id;
        $this->validFrom = $code->valid_from->toDateString();
        $this->validUntil = $code->valid_until?->toDateString();

        $this->dispatch('open-modal', 'btw-code-form');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'percentage', 'afTeDragenLedgerAccountId', 'voorTeVorderenLedgerAccountId', 'validUntil']);
        $this->validFrom = now()->toDateString();
    }

    public function save(AuditLogger $audit): void
    {
        $creating = $this->editingId === null;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'afTeDragenLedgerAccountId' => ['nullable', 'integer', 'exists:ledger_accounts,id'],
            'voorTeVorderenLedgerAccountId' => ['nullable', 'integer', 'exists:ledger_accounts,id'],
            'validFrom' => ['required', 'date'],
            'validUntil' => ['nullable', 'date', 'after_or_equal:validFrom'],
        ]);

        if ($data['afTeDragenLedgerAccountId'] === null && $data['voorTeVorderenLedgerAccountId'] === null) {
            $this->addError('voorTeVorderenLedgerAccountId', 'Koppel minstens één van de twee rekeningen (af te dragen of voor te vorderen).');

            return;
        }

        $attributes = [
            'name' => $data['name'],
            'percentage' => $data['percentage'],
            'af_te_dragen_ledger_account_id' => $data['afTeDragenLedgerAccountId'],
            'voor_te_vorderen_ledger_account_id' => $data['voorTeVorderenLedgerAccountId'],
            'valid_from' => $data['validFrom'],
            'valid_until' => $data['validUntil'],
        ];

        if ($creating) {
            $code = BtwCode::create($attributes);
            $audit->log('btw_code.created', $code, after: $attributes);
            $this->statusMessage = "BTW-code [{$code->name}] aangemaakt.";
        } else {
            $code = BtwCode::query()->findOrFail($this->editingId);
            $before = $code->only(array_keys($attributes));
            $code->update($attributes);
            $audit->log('btw_code.updated', $code, before: $before, after: $attributes);
            $this->statusMessage = "BTW-code [{$code->name}] bijgewerkt.";
        }

        $this->resetForm();
        $this->dispatch('close-modal', 'btw-code-form');
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $code = BtwCode::query()->findOrFail($id);

        $inUse = Product::query()->where('btw_code_id', $code->id)->exists()
            || Charge::query()->where('btw_code_id', $code->id)->exists();

        if ($inUse) {
            $this->errorMessage = "BTW-code [{$code->name}] is gekoppeld aan een product of post en kan niet verwijderd worden.";

            return;
        }

        $audit->log('btw_code.deleted', $code, before: ['name' => $code->name]);
        $code->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }
        $this->errorMessage = null;
        $this->statusMessage = 'BTW-code verwijderd.';
    }

    public function render(): View
    {
        return view('livewire.admin.btw-code-beheer', [
            'codes' => BtwCode::query()->with(['afTeDragenLedgerAccount', 'voorTeVorderenLedgerAccount'])->orderByDesc('valid_from')->get(),
            'ledgerAccounts' => LedgerAccount::query()->orderBy('code')->get(),
        ]);
    }
}
