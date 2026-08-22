<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <a href="{{ route('admin.dagboeken.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Dagboeken</a>

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

    <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-lg font-medium text-gray-900">{{ $dagboek->number }} · {{ $dagboek->name }}</div>
                <div class="text-sm text-gray-500">{{ $dagboek->type->label() }}</div>
            </div>
            <button type="button" x-data="" wire:click="resetForm" x-on:click="$dispatch('open-modal', 'journaalpost-form')"
                class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
                + Nieuwe journaalpost
            </button>
        </div>

        @forelse ($entries as $entry)
            <div class="border border-gray-100 rounded-md overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-gray-50 border-b border-gray-100 text-sm">
                    <div>
                        <span class="font-medium text-gray-800">{{ $entry->description }}</span>
                        @if ($entry->reference)
                            <span class="text-xs text-gray-400">({{ $entry->reference }})</span>
                        @endif
                    </div>
                    <div class="text-right">
                        <span class="text-gray-500">{{ $entry->date->format('d-m-Y') }}</span>
                        <span class="text-xs text-gray-400 block">{{ $entry->period->label() }}</span>
                    </div>
                </div>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-4 py-1.5">Rekening</th>
                            <th class="px-4 py-1.5 text-right">Debet</th>
                            <th class="px-4 py-1.5 text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($entry->lines as $line)
                            <tr>
                                <td class="px-4 py-1.5 text-gray-700">{{ $line->account->code }} · {{ $line->account->name }}</td>
                                <td class="px-4 py-1.5 text-right text-gray-700 whitespace-nowrap">
                                    {{ (float) $line->debit > 0 ? '€ '.number_format((float) $line->debit, 2, ',', '.') : '' }}
                                </td>
                                <td class="px-4 py-1.5 text-right text-gray-700 whitespace-nowrap">
                                    {{ (float) $line->credit > 0 ? '€ '.number_format((float) $line->credit, 2, ',', '.') : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="text-sm text-gray-500">Nog geen journaalposten in dit dagboek.</p>
        @endforelse
    </div>

    {{-- Uitgebreid formulier (datum, omschrijving, referentie, beginbalans-vlag en een dynamische regeltabel) — een modal i.p.v. inline. --}}
    <x-modal name="journaalpost-form" maxWidth="4xl">
        <div class="p-6 space-y-4">
            <h2 class="font-medium text-gray-900 text-lg">Nieuwe journaalpost</h2>

            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="text-gray-600">Datum</span>
                    <input type="date" wire:model="date" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('date') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm sm:col-span-2">
                    <span class="text-gray-600">Omschrijving</span>
                    <input type="text" wire:model="description" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('description') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm">
                    <span class="text-gray-600">Referentie <span class="text-gray-400">(optioneel)</span></span>
                    <input type="text" wire:model="reference" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('reference') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </label>

                <label class="flex items-center gap-2 text-sm sm:col-span-2 sm:justify-end">
                    <input type="checkbox" wire:model="isOpeningBalance" class="rounded border-gray-300 text-rzvg-600" />
                    <span class="text-gray-600">Beginbalans <span class="text-gray-400">(boekt in periode 0 van het boekjaar van de gekozen datum)</span></span>
                </label>
            </div>

            <div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="py-1.5">Rekening</th>
                            <th class="py-1.5 text-right w-32">Debet</th>
                            <th class="py-1.5 text-right w-32">Credit</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($lines as $index => $line)
                            <tr wire:key="journaalpost-line-{{ $index }}">
                                <td class="py-1.5 pr-2">
                                    <select wire:model="lines.{{ $index }}.account_id" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                        <option value="">— Kies rekening —</option>
                                        @foreach ($ledgerAccounts as $acc)
                                            <option value="{{ $acc->id }}">{{ $acc->code }} · {{ $acc->name }}</option>
                                        @endforeach
                                    </select>
                                    @error("lines.{$index}.account_id") <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                </td>
                                <td class="py-1.5 pr-2">
                                    <input type="number" step="0.01" min="0" wire:model="lines.{{ $index }}.debit"
                                        class="block w-full border-gray-300 rounded shadow-sm text-sm text-right" />
                                    @error("lines.{$index}.debit") <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                </td>
                                <td class="py-1.5 pr-2">
                                    <input type="number" step="0.01" min="0" wire:model="lines.{{ $index }}.credit"
                                        class="block w-full border-gray-300 rounded shadow-sm text-sm text-right" />
                                    @error("lines.{$index}.credit") <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                </td>
                                <td class="py-1.5 text-center">
                                    <button type="button" wire:click="removeLine({{ $index }})" title="Regel verwijderen" class="text-red-600 hover:text-red-800">
                                        <x-action-icon name="trash" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @error('lines') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror

                <button type="button" wire:click="addLine" title="Regel toevoegen" class="mt-2 inline-flex items-center gap-1 text-sm text-rzvg-600 hover:text-rzvg-800">
                    <x-action-icon name="plus" /> Regel toevoegen
                </button>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" wire:click="resetForm" x-on:click="$dispatch('close')"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Annuleren</button>
                <button type="button" wire:click="save"
                    class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
                    Boeken
                </button>
            </div>
        </div>
    </x-modal>
</div>
