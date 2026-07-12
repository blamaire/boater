<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Producten/artikelen (§23): contributie, activiteitsbijdragen en advertenties met hun prijshistorie en opbrengstrekening.
        Een contributie-product koppel je aan één of meer lidmaatschapsvormen.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Formulier --}}
        <section class="lg:col-span-1 bg-white rounded-lg shadow-sm border border-gray-200 p-4 space-y-4 self-start">
            <h2 class="font-medium text-gray-900">{{ $editingId ? 'Product bewerken' : 'Nieuw product' }}</h2>

            <label class="block text-sm">
                <span class="text-gray-600">Naam</span>
                <input type="text" wire:model="name" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Soort</span>
                <select wire:model="type" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
                @error('type') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Opbrengstrekening</span>
                <select wire:model="ledgerAccountId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    <option value="">— Geen —</option>
                    @foreach ($ledgerAccounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->code }} · {{ $acc->name }}</option>
                    @endforeach
                </select>
                @error('ledgerAccountId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="isRecurring" class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                Terugkerend product
            </label>

            @if ($isRecurring)
                <label class="block text-sm">
                    <span class="text-gray-600">Herhaalschema</span>
                    <select wire:model="recurrence" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                        <option value="">— Kies —</option>
                        @foreach ($recurrences as $r)
                            <option value="{{ $r->value }}">{{ $r->label() }}</option>
                        @endforeach
                    </select>
                    @error('recurrence') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </label>
            @endif

            <div class="text-sm">
                <span class="text-gray-600">Contributie voor lidmaatschapsvorm(en)</span>
                <div class="mt-1 max-h-40 overflow-y-auto border border-gray-200 rounded p-2 space-y-1">
                    @foreach ($membershipTypes as $mt)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="linkedMembershipTypeIds" value="{{ $mt->id }}"
                                class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                            <span>{{ $mt->name }}</span>
                            @if ($mt->product_id && (! $editingId || $mt->product_id !== $editingId))
                                <span class="text-xs text-gray-400">(nu gekoppeld aan een ander product)</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" wire:click="save"
                    class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
                    {{ $editingId ? 'Opslaan' : 'Aanmaken' }}
                </button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Annuleren</button>
                @endif
            </div>

            {{-- Prijshistorie (alleen bij bewerken) --}}
            @if ($editingId)
                <div class="border-t border-gray-100 pt-4 space-y-3">
                    <h3 class="text-sm font-medium text-gray-800">Prijshistorie</h3>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="py-1">Vanaf</th>
                                <th class="py-1">Bedrag</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($editingPrices as $price)
                                <tr>
                                    <td class="py-1 text-gray-700">{{ $price->valid_from->format('d-m-Y') }}</td>
                                    <td class="py-1 text-gray-700">&euro; {{ number_format((float) $price->amount, 2, ',', '.') }}</td>
                                    <td class="py-1 text-right">
                                        <button type="button" wire:click="deletePrice({{ $price->id }})"
                                            onclick="return confirm('Prijs verwijderen?');"
                                            class="text-red-600 hover:text-red-800 text-xs">Verwijderen</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-2 text-gray-400 text-xs">Nog geen prijs vastgelegd.</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="flex items-end gap-2">
                        <label class="text-sm">
                            <span class="text-gray-600 text-xs">Geldig vanaf</span>
                            <input type="date" wire:model="priceValidFrom" class="mt-1 block border-gray-300 rounded shadow-sm text-sm" />
                        </label>
                        <label class="text-sm">
                            <span class="text-gray-600 text-xs">Bedrag (€)</span>
                            <input type="number" step="0.01" min="0" wire:model="priceAmount" class="mt-1 block w-28 border-gray-300 rounded shadow-sm text-sm" />
                        </label>
                        <button type="button" wire:click="addPrice"
                            class="px-3 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-900 text-sm">Vastleggen</button>
                    </div>
                    @error('priceValidFrom') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    @error('priceAmount') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
            @endif
        </section>

        {{-- Lijst --}}
        <section class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden self-start">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Soort</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Huidige prijs</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rekening</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($products as $product)
                        @php($current = $product->currentPrice())
                        <tr wire:key="product-{{ $product->id }}">
                            <td class="px-4 py-2">
                                <div class="font-medium text-gray-900">{{ $product->name }}</div>
                                @if ($product->membershipTypes->isNotEmpty())
                                    <div class="text-xs text-gray-500">{{ $product->membershipTypes->pluck('name')->implode(', ') }}</div>
                                @endif
                                @if ($product->is_recurring && $product->recurrence)
                                    <span class="text-xs text-gray-400">{{ $product->recurrence->label() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ $product->type->label() }}</td>
                            <td class="px-4 py-2 text-gray-700">
                                @if ($current)
                                    &euro; {{ number_format((float) $current->amount, 2, ',', '.') }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-500 text-xs">
                                {{ $product->ledgerAccount ? $product->ledgerAccount->code.' · '.$product->ledgerAccount->name : '—' }}
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <button type="button" wire:click="edit({{ $product->id }})" class="text-rzvg-600 hover:text-rzvg-800 text-xs">Bewerken</button>
                                <button type="button" wire:click="delete({{ $product->id }})"
                                    onclick="return confirm('Product verwijderen?');"
                                    class="ml-2 text-red-600 hover:text-red-800 text-xs">Verwijderen</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Nog geen producten.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</div>
