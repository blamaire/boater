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
                                    <x-action-icon name="pencil" />
                                </button>
                                <button type="button" wire:click="delete({{ $code->id }})"
                                    onclick="return confirm('BTW-code verwijderen?');"
                                    title="Verwijderen" class="ml-2 text-red-600 hover:text-red-800">
                                    <x-action-icon name="trash" />
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
