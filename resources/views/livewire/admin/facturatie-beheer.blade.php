<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Facturatie (§23): voeg te factureren posten toe en bundel de openstaande posten van een betaler tot één factuur.
        Elke post wordt direct als journaalpost geboekt (debet Debiteuren, credit de opbrengstrekening van het product).
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    {{-- Nieuwe post --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
        <h2 class="font-medium text-gray-900">Nieuwe post</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-gray-600">Betaler</span>
                <select wire:model="chargeDebtorId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    <option value="">— Kies persoon —</option>
                    @foreach ($persons as $p)
                        <option value="{{ $p->id }}">{{ $p->last_name }}, {{ $p->first_name }}</option>
                    @endforeach
                </select>
                @error('chargeDebtorId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Product</span>
                <select wire:model.live="chargeProductId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    <option value="">— Kies product —</option>
                    @foreach ($products as $prod)
                        <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                    @endforeach
                </select>
                @error('chargeProductId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Omschrijving</span>
                <input type="text" wire:model="chargeDescription" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('chargeDescription') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm">
                    <span class="text-gray-600">Bedrag (€)</span>
                    <input type="number" step="0.01" min="0" wire:model="chargeAmount" placeholder="0,00"
                        class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('chargeAmount') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">Vervaldatum</span>
                    <input type="date" wire:model="chargeDueAt" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('chargeDueAt') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </label>
            </div>
        </div>
        <button type="button" wire:click="addCharge"
            class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">Post toevoegen</button>
    </section>

    {{-- Openstaande posten per betaler --}}
    <section class="space-y-4">
        <h2 class="font-medium text-gray-900">Openstaande posten</h2>
        @forelse ($openChargesByDebtor as $charges)
            @php($debtor = $charges->first()->debtor)
            @php($subtotal = $charges->sum(fn ($c) => (float) $c->amount))
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-gray-50 border-b border-gray-100">
                    <span class="font-medium text-gray-800">{{ $debtor->last_name }}, {{ $debtor->first_name }}</span>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-600">Subtotaal: &euro; {{ number_format($subtotal, 2, ',', '.') }}</span>
                        <button type="button" wire:click="invoiceDebtor({{ $debtor->id }})"
                            class="px-3 py-1.5 bg-gray-800 text-white rounded-md hover:bg-gray-900 text-xs">Factureer</button>
                    </div>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($charges as $charge)
                            <tr>
                                <td class="px-4 py-2 text-gray-700">{{ $charge->description }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $charge->product->name }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $charge->due_at?->format('d-m-Y') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-gray-700 whitespace-nowrap">&euro; {{ number_format((float) $charge->amount, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="text-sm text-gray-500">Geen openstaande posten.</p>
        @endforelse
    </section>

    {{-- Facturen --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-2 border-b border-gray-100"><h2 class="font-medium text-gray-900">Facturen</h2></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nummer</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Betaler</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Totaal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-rzvg-600 hover:text-rzvg-800 hover:underline">{{ $invoice->number }}</a>
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ $invoice->debtor->last_name }}, {{ $invoice->debtor->first_name }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $invoice->issued_at?->format('d-m-Y') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600 text-xs">{{ $invoice->status->label() }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 whitespace-nowrap">&euro; {{ number_format((float) $invoice->total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Nog geen facturen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
