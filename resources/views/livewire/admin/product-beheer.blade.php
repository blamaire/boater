<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Producten/artikelen (§23): contributie, activiteitsbijdragen en advertenties met hun prijshistorie en opbrengstrekening.
        Een contributie-product koppel je aan één of meer lidmaatschapsvormen.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    @unless ($editingId)
        <div class="flex justify-end">
            <button type="button" x-data="" wire:click="resetForm" x-on:click="$dispatch('open-modal', 'product-form')"
                class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
                + Nieuw product
            </button>
        </div>
    @endunless

    {{-- Product bewerken (incl. prijshistorie) — alleen zichtbaar tijdens bewerken. Aanmaken
         gebeurt via de modal hieronder; direct na aanmaken opent dit scherm automatisch zodat
         er meteen meer prijzen bij kunnen. --}}
    @if ($editingId)
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-medium text-gray-900">Product bewerken</h2>
                <button type="button" wire:click="resetForm" class="text-sm text-rzvg-600 hover:text-rzvg-800">Sluiten</button>
            </div>

            @include('livewire.admin.partials.product-fields')

            <div class="flex gap-2">
                <button type="button" wire:click="save"
                    class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">Opslaan</button>
                <button type="button" wire:click="resetForm"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Sluiten</button>
            </div>

            {{-- Prijshistorie --}}
            <div class="border-t border-gray-100 pt-4 space-y-3">
                <h3 class="text-sm font-medium text-gray-800">Prijs</h3>
                @if ($editingPrices->isEmpty())
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                        Dit product heeft nog geen prijs. Leg hieronder een bedrag met ingangsdatum vast.
                    </p>
                @endif

                <div class="flex flex-wrap items-end gap-3">
                    <label class="text-sm">
                        <span class="text-gray-600 text-xs block">Geldig vanaf</span>
                        <input type="date" wire:model="priceValidFrom" class="mt-1 block border-gray-300 rounded shadow-sm text-sm" />
                    </label>
                    <label class="text-sm">
                        <span class="text-gray-600 text-xs block">Bedrag (€)</span>
                        <input type="number" step="0.01" min="0" wire:model="priceAmount" placeholder="0,00"
                            class="mt-1 block w-32 border-gray-300 rounded shadow-sm text-sm" />
                    </label>
                    <button type="button" wire:click="addPrice"
                        class="px-3 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-900 text-sm">Prijs vastleggen</button>
                </div>
                @error('priceValidFrom') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                @error('priceAmount') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror

                @if ($editingPrices->isNotEmpty())
                    <table class="min-w-full text-sm border-t border-gray-100">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="py-1">Vanaf</th>
                                <th class="py-1">Bedrag</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($editingPrices as $price)
                                <tr>
                                    <td class="py-1 text-gray-700">{{ $price->valid_from->format('d-m-Y') }}</td>
                                    <td class="py-1 text-gray-700">&euro; {{ number_format((float) $price->amount, 2, ',', '.') }}</td>
                                    <td class="py-1 text-right">
                                        <button type="button" wire:click="deletePrice({{ $price->id }})"
                                            onclick="return confirm('Prijs verwijderen?');"
                                            class="text-red-600 hover:text-red-800 text-xs">Verwijderen</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    @endif

    {{-- Lijst --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Soort</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Huidige prijs</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rekening</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($products as $product)
                        @php($current = $product->currentPrice())
                        <tr wire:key="product-{{ $product->id }}" @class(['bg-rzvg-50' => $editingId === $product->id])>
                            <td class="px-4 py-2">
                                <div class="font-medium text-gray-900">{{ $product->name }}</div>
                                @if ($product->membershipTypes->isNotEmpty())
                                    <div class="text-xs text-gray-500">{{ $product->membershipTypes->pluck('name')->implode(', ') }}</div>
                                @endif
                                @if ($product->is_recurring && $product->recurrence)
                                    <span class="text-xs text-gray-400">{{ $product->recurrence->label() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ $product->type->label() }}</td>
                            <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                                @if ($current)
                                    &euro; {{ number_format((float) $current->amount, 2, ',', '.') }}
                                @elseif ($upcoming = $product->upcomingPrice())
                                    <span class="text-gray-500">
                                        &euro; {{ number_format((float) $upcoming->amount, 2, ',', '.') }}
                                        <span class="text-xs text-gray-400">(vanaf {{ $upcoming->valid_from->format('d-m-Y') }})</span>
                                    </span>
                                @else
                                    <span class="text-amber-600">geen prijs</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">
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
        </div>
    </section>

    {{-- Uitgebreide invoer (7+ velden incl. lidmaatschapskoppeling en optionele beginprijs)
         — formulier in een modal i.p.v. inline in de tabel. --}}
    <x-modal name="product-form" maxWidth="3xl">
        <div class="p-6 space-y-4">
            <h2 class="font-medium text-gray-900 text-lg">Nieuw product</h2>

            @include('livewire.admin.partials.product-fields')

            <div class="border-t border-gray-100 pt-4 space-y-2">
                <h3 class="text-sm font-medium text-gray-800">Beginprijs <span class="text-gray-400 font-normal">(optioneel)</span></h3>
                <div class="flex flex-wrap items-start gap-3">
                    <label class="text-sm">
                        <span class="text-gray-600 text-xs block">Geldig vanaf</span>
                        <input type="date" wire:model="priceValidFrom" class="mt-1 block border-gray-300 rounded shadow-sm text-sm" />
                    </label>
                    <label class="text-sm">
                        <span class="text-gray-600 text-xs block">Bedrag (€)</span>
                        <input type="number" step="0.01" min="0" wire:model="priceAmount" placeholder="0,00"
                            class="mt-1 block w-32 border-gray-300 rounded shadow-sm text-sm" />
                    </label>
                </div>
                @error('priceValidFrom') <span class="block text-red-600 text-xs">{{ $message }}</span> @enderror
                @error('priceAmount') <span class="block text-red-600 text-xs">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-400">Je kunt na het aanmaken meer prijzen (met andere ingangsdatums) toevoegen.</p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" wire:click="resetForm" x-on:click="$dispatch('close')"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Annuleren</button>
                <button type="button" wire:click="save"
                    class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">Aanmaken</button>
            </div>
        </div>
    </x-modal>
</div>
