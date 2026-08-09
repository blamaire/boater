<?php

namespace App\Livewire\Admin;

use App\Enums\BtwCodeDirection;
use App\Models\BtwCode;
use App\Models\Charge;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor BTW-codes: percentage, richting, gekoppelde grootboek-
 * rekening en geldigheidsperiode. Permissie: `btw_codes.manage`.
 */
#[Layout('layouts.app', ['header' => 'BTW-codes'])]
class BtwCodeBeheer extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $percentage = '';

    public string $direction = 'af_te_dragen';

    public ?int $ledgerAccountId = null;

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
        $this->direction = $code->direction->value;
        $this->ledgerAccountId = $code->ledger_account_id;
        $this->validFrom = $code->valid_from->toDateString();
        $this->validUntil = $code->valid_until?->toDateString();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'percentage', 'ledgerAccountId', 'validUntil']);
        $this->direction = 'af_te_dragen';
        $this->validFrom = now()->toDateString();
    }

    public function save(AuditLogger $audit): void
    {
        $creating = $this->editingId === null;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'direction' => ['required', 'in:'.implode(',', array_column(BtwCodeDirection::cases(), 'value'))],
            'ledgerAccountId' => ['required', 'integer', 'exists:ledger_accounts,id'],
            'validFrom' => ['required', 'date'],
            'validUntil' => ['nullable', 'date', 'after_or_equal:validFrom'],
        ]);

        $attributes = [
            'name' => $data['name'],
            'percentage' => $data['percentage'],
            'direction' => $data['direction'],
            'ledger_account_id' => $data['ledgerAccountId'],
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
            'codes' => BtwCode::query()->with('ledgerAccount')->orderByDesc('valid_from')->get(),
            'directions' => BtwCodeDirection::cases(),
            'ledgerAccounts' => LedgerAccount::query()->orderBy('code')->get(),
        ]);
    }
}
