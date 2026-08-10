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

    <div class="flex justify-end">
        <button type="button" x-data="" wire:click="resetForm" x-on:click="$dispatch('open-modal', 'btw-code-form')"
            class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
            + Nieuwe BTW-code
        </button>
    </div>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col>
                    <col class="w-28">
                    <col class="w-40">
                    <col class="w-40">
                    <col class="w-56">
                    <col class="w-8">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Percentage</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Af te dragen</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Voor te vorderen</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Geldigheid</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="2">Acties</th>
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
                            <x-action-cell click="edit({{ $code->id }})" icon="pencil" title="Bewerken" variant="primary" />
                            <x-action-cell click="delete({{ $code->id }})" icon="trash" title="Verwijderen" variant="danger" confirm="BTW-code verwijderen?" />
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Nog geen BTW-codes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Uitgebreide tabel (6 velden incl. 2 datums) — formulier in een modal i.p.v. inline in de tabel. --}}
    <x-modal name="btw-code-form" maxWidth="3xl">
        <div class="p-6 space-y-4">
            <h2 class="font-medium text-gray-900 text-lg">{{ $editingId ? 'BTW-code bewerken' : 'Nieuwe BTW-code' }}</h2>

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

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" wire:click="resetForm" x-on:click="$dispatch('close')"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Annuleren</button>
                <button type="button" wire:click="save"
                    class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
                    {{ $editingId ? 'Opslaan' : 'Aanmaken' }}
                </button>
            </div>
        </div>
    </x-modal>
</div>
