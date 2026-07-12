<?php

namespace App\Livewire\Admin;

use App\Enums\ProductRecurrence;
use App\Enums\ProductType;
use App\Models\LedgerAccount;
use App\Models\MembershipType;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beheer-UI voor producten/artikelen (§23): naam, soort, opbrengstrekening,
 * herhaalschema, prijshistorie, en de koppeling met lidmaatschapsvormen
 * (`MEMBERSHIP_TYPE.product_id`). Permissie: `products.manage`.
 */
#[Layout('layouts.app', ['header' => 'Producten'])]
class ProductBeheer extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'contributie';

    public ?int $ledgerAccountId = null;

    public bool $isRecurring = false;

    public ?string $recurrence = null;

    /** Geselecteerde lidmaatschapsvormen die dit product als contributie voeren. */
    /** @var array<int, int> */
    public array $linkedMembershipTypeIds = [];

    // Prijs toevoegen (per bewerkt product).
    public ?string $priceValidFrom = null;

    public ?string $priceAmount = null;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->resetPriceInputs();
    }

    private function resetPriceInputs(): void
    {
        // "Geldig vanaf" staat standaard op vandaag zodat je 'm niet elke keer hoeft te kiezen.
        $this->priceValidFrom = now()->toDateString();
        $this->priceAmount = null;
    }

    public function edit(int $id): void
    {
        $this->loadProduct($id);
    }

    private function loadProduct(int $id): void
    {
        $product = Product::query()->with('membershipTypes')->findOrFail($id);
        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->type = $product->type->value;
        $this->ledgerAccountId = $product->ledger_account_id;
        $this->isRecurring = $product->is_recurring;
        $this->recurrence = $product->recurrence?->value;
        $this->linkedMembershipTypeIds = $product->membershipTypes->pluck('id')->all();
        $this->resetPriceInputs();
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'ledgerAccountId', 'isRecurring',
            'recurrence', 'linkedMembershipTypeIds',
        ]);
        $this->type = 'contributie';
        $this->resetPriceInputs();
    }

    public function save(AuditLogger $audit): void
    {
        $creating = $this->editingId === null;

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:'.implode(',', array_column(ProductType::cases(), 'value'))],
            'ledgerAccountId' => ['nullable', 'integer', 'exists:ledger_accounts,id'],
            'isRecurring' => ['boolean'],
            'recurrence' => [
                'nullable',
                'required_if:isRecurring,true',
                'in:'.implode(',', array_column(ProductRecurrence::cases(), 'value')),
            ],
            'linkedMembershipTypeIds' => ['array'],
            'linkedMembershipTypeIds.*' => ['integer', 'exists:membership_types,id'],
        ];

        if ($creating) {
            // Beginprijs is optioneel: "Geldig vanaf" staat standaard op vandaag,
            // dus de prijs telt alleen mee als er een bedrag is ingevuld. Is dat
            // zo, dan is een ingangsdatum verplicht.
            $rules['priceValidFrom'] = ['nullable', 'required_with:priceAmount', 'date'];
            $rules['priceAmount'] = ['nullable', 'numeric', 'min:0'];
        }

        $data = $this->validate($rules);

        $product = DB::transaction(function () use ($data, $audit, $creating): Product {
            $attributes = [
                'name' => $data['name'],
                'type' => $data['type'],
                'ledger_account_id' => $data['ledgerAccountId'],
                'is_recurring' => $data['isRecurring'],
                'recurrence' => $data['isRecurring'] ? $data['recurrence'] : null,
            ];

            if (! $creating) {
                $product = Product::query()->findOrFail($this->editingId);
                $before = $product->only(array_keys($attributes));
                $product->update($attributes);
                $audit->log('product.updated', $product, before: $before, after: $attributes);
                $this->statusMessage = "Product [{$product->name}] bijgewerkt.";
            } else {
                $product = Product::create($attributes);
                $audit->log('product.created', $product, after: $attributes);

                // Beginprijs direct meegeven bij het aanmaken (alleen als er een
                // bedrag is; de datum staat standaard op vandaag).
                if (($this->priceAmount ?? '') !== '' && ($this->priceValidFrom ?? '') !== '') {
                    $this->recordPrice($product, $this->priceValidFrom, $this->priceAmount, $audit);
                }
                $this->statusMessage = "Product [{$product->name}] aangemaakt.";
            }

            $this->syncMembershipTypes($product, $audit);

            return $product;
        });

        // Blijf op het product staan zodat er direct meer prijzen bij kunnen.
        $this->loadProduct($product->id);
    }

    /**
     * Zet `MEMBERSHIP_TYPE.product_id` gelijk aan de selectie: koppel de gekozen
     * vormen aan dit product en ontkoppel wat niet meer geselecteerd is.
     */
    private function syncMembershipTypes(Product $product, AuditLogger $audit): void
    {
        $selected = $this->linkedMembershipTypeIds;

        $current = MembershipType::query()->where('product_id', $product->id)->pluck('id')->all();

        $toDetach = array_diff($current, $selected);
        $toAttach = array_diff($selected, $current);

        if ($toDetach !== []) {
            MembershipType::query()->whereIn('id', $toDetach)->update(['product_id' => null]);
        }
        if ($toAttach !== []) {
            MembershipType::query()->whereIn('id', $toAttach)->update(['product_id' => $product->id]);
        }

        if ($toDetach !== [] || $toAttach !== []) {
            $audit->log('product.membership_types_synced', $product, after: ['membership_type_ids' => array_values($selected)]);
        }
    }

    public function addPrice(AuditLogger $audit): void
    {
        if ($this->editingId === null) {
            return;
        }

        $data = $this->validate([
            'priceValidFrom' => ['required', 'date'],
            'priceAmount' => ['required', 'numeric', 'min:0'],
        ]);

        $product = Product::query()->findOrFail($this->editingId);
        $price = $this->recordPrice($product, $data['priceValidFrom'], (string) $data['priceAmount'], $audit);

        $this->resetPriceInputs();
        $this->statusMessage = "Prijs vanaf {$price->valid_from->format('d-m-Y')} vastgelegd.";
    }

    /**
     * Legt een prijs vast (of overschrijft de prijs met dezelfde ingangsdatum)
     * en logt de mutatie. Gedeeld door de beginprijs bij aanmaken en het
     * toevoegen van prijzen bij een bestaand product.
     */
    private function recordPrice(Product $product, string $validFrom, string $amount, AuditLogger $audit): ProductPrice
    {
        $price = $product->prices()->updateOrCreate(
            ['valid_from' => $validFrom],
            ['amount' => $amount],
        );

        $audit->log('product_price.set', $product, after: [
            'valid_from' => $validFrom,
            'amount' => $amount,
        ]);

        return $price;
    }

    public function deletePrice(int $priceId, AuditLogger $audit): void
    {
        if ($this->editingId === null) {
            return;
        }

        $product = Product::query()->findOrFail($this->editingId);
        $price = $product->prices()->whereKey($priceId)->firstOrFail();
        $before = ['valid_from' => $price->valid_from->toDateString(), 'amount' => $price->amount];
        $price->delete();

        $audit->log('product_price.deleted', $product, before: $before);
        $this->statusMessage = 'Prijs verwijderd.';
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        DB::transaction(function () use ($id, $audit) {
            $product = Product::query()->findOrFail($id);
            // Ontkoppel lidmaatschapsvormen (FK is nullOnDelete, maar expliciet
            // voor de duidelijkheid en de auditregel).
            MembershipType::query()->where('product_id', $product->id)->update(['product_id' => null]);
            $audit->log('product.deleted', $product, before: ['name' => $product->name]);
            $product->delete();
        });

        if ($this->editingId === $id) {
            $this->resetForm();
        }
        $this->statusMessage = 'Product verwijderd.';
    }

    public function render(): View
    {
        return view('livewire.admin.product-beheer', [
            'products' => Product::query()
                ->with(['ledgerAccount', 'membershipTypes'])
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'types' => ProductType::cases(),
            'recurrences' => ProductRecurrence::cases(),
            'ledgerAccounts' => LedgerAccount::query()->orderBy('code')->get(),
            'membershipTypes' => MembershipType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'editingPrices' => $this->editingId !== null
                ? Product::query()->findOrFail($this->editingId)->prices()->get()
                : collect(),
        ]);
    }
}
