<?php

namespace App\Livewire\Admin;

use App\Enums\LedgerAccountType;
use App\Models\BtwCode;
use App\Models\Hoofdverdichting;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\Verdichting;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor het grootboek (§23.3 LEDGER_ACCOUNT): code, naam en
 * rubriek van een grootboekrekening, plus de groeperingsniveaus erboven
 * (hoofdverdichting > verdichting). Permissie: `ledger_accounts.manage`.
 */
#[Layout('layouts.app', ['header' => 'Grootboek'])]
class GrootboekBeheer extends Component
{
    // Hoofdverdichting
    public ?int $hvEditingId = null;

    public string $hvCode = '';

    public string $hvName = '';

    // Verdichting
    public ?int $vEditingId = null;

    public string $vCode = '';

    public string $vName = '';

    public ?int $vHoofdverdichtingId = null;

    // Grootboekrekening
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $type = 'activa';

    public ?int $verdichtingId = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    // --- Hoofdverdichting ---

    public function editHoofdverdichting(int $id): void
    {
        $hv = Hoofdverdichting::query()->findOrFail($id);
        $this->hvEditingId = $hv->id;
        $this->hvCode = $hv->code;
        $this->hvName = $hv->name;
    }

    public function resetHoofdverdichtingForm(): void
    {
        $this->reset(['hvEditingId', 'hvCode', 'hvName']);
    }

    public function saveHoofdverdichting(AuditLogger $audit): void
    {
        $creating = $this->hvEditingId === null;

        $data = $this->validate([
            'hvCode' => ['required', 'string', 'max:20', 'unique:hoofdverdichtingen,code'.($creating ? '' : ",{$this->hvEditingId}")],
            'hvName' => ['required', 'string', 'max:150'],
        ], attributes: ['hvCode' => 'code', 'hvName' => 'naam']);

        $attributes = ['code' => $data['hvCode'], 'name' => $data['hvName']];

        if ($creating) {
            $hv = Hoofdverdichting::create($attributes);
            $audit->log('hoofdverdichting.created', $hv, after: $attributes);
            $this->statusMessage = "Hoofdverdichting [{$hv->code} · {$hv->name}] aangemaakt.";
        } else {
            $hv = Hoofdverdichting::query()->findOrFail($this->hvEditingId);
            $before = $hv->only(['code', 'name']);
            $hv->update($attributes);
            $audit->log('hoofdverdichting.updated', $hv, before: $before, after: $attributes);
            $this->statusMessage = "Hoofdverdichting [{$hv->code} · {$hv->name}] bijgewerkt.";
        }

        $this->resetHoofdverdichtingForm();
    }

    public function deleteHoofdverdichting(int $id, AuditLogger $audit): void
    {
        $hv = Hoofdverdichting::query()->findOrFail($id);

        if ($hv->verdichtingen()->exists()) {
            $this->errorMessage = "Hoofdverdichting [{$hv->code} · {$hv->name}] heeft nog verdichtingen en kan niet verwijderd worden.";

            return;
        }

        $audit->log('hoofdverdichting.deleted', $hv, before: ['code' => $hv->code, 'name' => $hv->name]);
        $hv->delete();

        if ($this->hvEditingId === $id) {
            $this->resetHoofdverdichtingForm();
        }
        $this->errorMessage = null;
        $this->statusMessage = 'Hoofdverdichting verwijderd.';
    }

    // --- Verdichting ---

    public function editVerdichting(int $id): void
    {
        $v = Verdichting::query()->findOrFail($id);
        $this->vEditingId = $v->id;
        $this->vCode = $v->code;
        $this->vName = $v->name;
        $this->vHoofdverdichtingId = $v->hoofdverdichting_id;
    }

    public function resetVerdichtingForm(): void
    {
        $this->reset(['vEditingId', 'vCode', 'vName', 'vHoofdverdichtingId']);
    }

    public function saveVerdichting(AuditLogger $audit): void
    {
        $creating = $this->vEditingId === null;

        $data = $this->validate([
            'vCode' => ['required', 'string', 'max:20', 'unique:verdichtingen,code'.($creating ? '' : ",{$this->vEditingId}")],
            'vName' => ['required', 'string', 'max:150'],
            'vHoofdverdichtingId' => ['required', 'integer', 'exists:hoofdverdichtingen,id'],
        ], attributes: ['vCode' => 'code', 'vName' => 'naam', 'vHoofdverdichtingId' => 'hoofdverdichting']);

        $attributes = ['code' => $data['vCode'], 'name' => $data['vName'], 'hoofdverdichting_id' => $data['vHoofdverdichtingId']];

        if ($creating) {
            $v = Verdichting::create($attributes);
            $audit->log('verdichting.created', $v, after: $attributes);
            $this->statusMessage = "Verdichting [{$v->code} · {$v->name}] aangemaakt.";
        } else {
            $v = Verdichting::query()->findOrFail($this->vEditingId);
            $before = $v->only(['code', 'name', 'hoofdverdichting_id']);
            $v->update($attributes);
            $audit->log('verdichting.updated', $v, before: $before, after: $attributes);
            $this->statusMessage = "Verdichting [{$v->code} · {$v->name}] bijgewerkt.";
        }

        $this->resetVerdichtingForm();
    }

    public function deleteVerdichting(int $id, AuditLogger $audit): void
    {
        $v = Verdichting::query()->findOrFail($id);

        if ($v->ledgerAccounts()->exists()) {
            $this->errorMessage = "Verdichting [{$v->code} · {$v->name}] heeft nog gekoppelde grootboekrekeningen en kan niet verwijderd worden.";

            return;
        }

        $audit->log('verdichting.deleted', $v, before: ['code' => $v->code, 'name' => $v->name]);
        $v->delete();

        if ($this->vEditingId === $id) {
            $this->resetVerdichtingForm();
        }
        $this->errorMessage = null;
        $this->statusMessage = 'Verdichting verwijderd.';
    }

    // --- Grootboekrekening ---

    public function edit(int $id): void
    {
        $account = LedgerAccount::query()->findOrFail($id);
        $this->editingId = $account->id;
        $this->code = $account->code;
        $this->name = $account->name;
        $this->type = $account->type->value;
        $this->verdichtingId = $account->verdichting_id;
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'name', 'verdichtingId']);
        $this->type = 'activa';
    }

    public function save(AuditLogger $audit): void
    {
        $creating = $this->editingId === null;

        $data = $this->validate([
            'code' => ['required', 'string', 'max:20', 'unique:ledger_accounts,code'.($creating ? '' : ",{$this->editingId}")],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:'.implode(',', array_column(LedgerAccountType::cases(), 'value'))],
            'verdichtingId' => ['nullable', 'integer', 'exists:verdichtingen,id'],
        ]);
        $attributes = [
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'verdichting_id' => $data['verdichtingId'],
        ];

        if ($creating) {
            $account = LedgerAccount::create($attributes);
            $audit->log('ledger_account.created', $account, after: $attributes);
            $this->statusMessage = "Grootboekrekening [{$account->code} · {$account->name}] aangemaakt.";
        } else {
            $account = LedgerAccount::query()->findOrFail($this->editingId);
            $before = $account->only(array_keys($attributes));
            $account->update($attributes);
            $audit->log('ledger_account.updated', $account, before: $before, after: $attributes);
            $this->statusMessage = "Grootboekrekening [{$account->code} · {$account->name}] bijgewerkt.";
        }

        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $account = LedgerAccount::query()->findOrFail($id);

        $inUse = Product::query()->where('ledger_account_id', $account->id)->exists()
            || BtwCode::query()->where('af_te_dragen_ledger_account_id', $account->id)
                ->orWhere('voor_te_vorderen_ledger_account_id', $account->id)->exists()
            || JournalLine::query()->where('ledger_account_id', $account->id)->exists();

        if ($inUse) {
            $this->errorMessage = "Grootboekrekening [{$account->code} · {$account->name}] is in gebruik en kan niet verwijderd worden.";

            return;
        }

        $audit->log('ledger_account.deleted', $account, before: ['code' => $account->code, 'name' => $account->name]);
        $account->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }
        $this->errorMessage = null;
        $this->statusMessage = 'Grootboekrekening verwijderd.';
    }

    public function render(): View
    {
        return view('livewire.admin.grootboek-beheer', [
            'hoofdverdichtingen' => Hoofdverdichting::query()->orderBy('code')->get(),
            'verdichtingen' => Verdichting::query()->with('hoofdverdichting')->orderBy('code')->get(),
            'accounts' => LedgerAccount::query()->with('verdichting.hoofdverdichting')->orderBy('code')->get(),
            'types' => LedgerAccountType::cases(),
        ]);
    }
}
