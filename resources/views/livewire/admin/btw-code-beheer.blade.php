<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        BTW-codes: percentage, de gekoppelde grootboekrekeningen (af te dragen bij verkoop, voor te vorderen bij
        inkoop — beide tegelijk te koppelen aan hetzelfde percentage) en een geldigheidsperiode. Koppel een code aan
        een product via <a href="{{ route('admin.products.index') }}" class="text-rzvg-600 hover:underline">Producten</a>
        om de boeking automatisch te laten splitsen in netto + BTW.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif
    @if ($errorMessage)
        <div class="rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-2" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-medium text-gray-900">{{ $editingId ? 'BTW-code bewerken' : 'Nieuwe BTW-code' }}</h2>
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="text-sm text-rzvg-600 hover:text-rzvg-800">+ Nieuwe BTW-code</button>
            @endif
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-gray-600">Naam</span>
                <input type="text" wire:model="name" placeholder="bv. 21% hoog tarief"
                    class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Percentage</span>
                <input type="number" step="0.01" min="0" max="100" wire:model="percentage"
                    class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('percentage') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Af te dragen-rekening <span class="text-gray-400">(verkoop)</span></span>
                <select wire:model="afTeDragenLedgerAccountId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    <option value="">— Geen —</option>
                    @foreach ($ledgerAccounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->code }} · {{ $acc->name }}</option>
                    @endforeach
                </select>
                @error('afTeDragenLedgerAccountId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Voor te vorderen-rekening <span class="text-gray-400">(inkoop)</span></span>
                <select wire:model="voorTeVorderenLedgerAccountId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    <option value="">— Geen —</option>
                    @foreach ($ledgerAccounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->code }} · {{ $acc->name }}</option>
                    @endforeach
                </select>
                @error('voorTeVorderenLedgerAccountId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Geldig vanaf</span>
                <input type="date" wire:model="validFrom" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('validFrom') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Geldig tot <span class="text-gray-400">(optioneel)</span></span>
                <input type="date" wire:model="validUntil" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('validUntil') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="flex gap-2">
            <button type="button" wire:click="save"
                class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
                {{ $editingId ? 'Opslaan' : 'Aanmaken' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="resetForm"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Sluiten</button>
            @endif
        </div>
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Percentage</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Af te dragen</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Voor te vorderen</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Geldigheid</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($codes as $code)
                        @php($active = $code->isActiveOn(now()))
                        <tr wire:key="btw-code-{{ $code->id }}" @class(['bg-rzvg-50' => $editingId === $code->id])>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $code->name }}</td>
                            <td class="px-4 py-2 text-right text-gray-700 whitespace-nowrap">{{ number_format((float) $code->percentage, 2, ',', '.') }}%</td>
                            <td class="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">
                                {{ $code->afTeDragenLedgerAccount ? $code->afTeDragenLedgerAccount->code.' · '.$code->afTeDragenLedgerAccount->name : '—' }}
                            </td>
                            <td class="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">
                                {{ $code->voorTeVorderenLedgerAccount ? $code->voorTeVorderenLedgerAccount->code.' · '.$code->voorTeVorderenLedgerAccount->name : '—' }}
                            </td>
                            <td class="px-4 py-2 text-xs whitespace-nowrap">
                                <span class="{{ $active ? 'text-green-700' : 'text-gray-400' }}">
                                    {{ $code->valid_from->format('d-m-Y') }} — {{ $code->valid_until?->format('d-m-Y') ?? 'nu' }}
                                </span>
                                @unless ($active)
                                    <span class="text-gray-400">(niet actief)</span>
                                @endunless
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <button type="button" wire:click="edit({{ $code->id }})" title="Bewerken" class="text-rzvg-600 hover:text-rzvg-800">
                                    <svg width="16" height="16" class="h-4 w-4 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                                    </svg>
                                </button>
                                <button type="button" wire:click="delete({{ $code->id }})"
                                    onclick="return confirm('BTW-code verwijderen?');"
                                    title="Verwijderen" class="ml-2 text-red-600 hover:text-red-800">
                                    <svg width="16" height="16" class="h-4 w-4 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Nog geen BTW-codes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
