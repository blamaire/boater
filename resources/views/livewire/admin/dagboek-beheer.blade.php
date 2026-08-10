<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Dagboeken: Verkoop, Inkoop en Memoriaal staan altijd klaar (één per administratie). Extra Bank- of
        Kas-dagboeken (bv. per bankrekening of kas) maak je hieronder zelf aan.
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

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col class="w-16">
                    <col>
                    <col class="w-40">
                    <col class="w-8">
                    <col class="w-8">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nr.</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Soort</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="3">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($dagboeken as $dagboek)
                        @if ($editingId === $dagboek->id)
                            <tr wire:key="dagboek-{{ $dagboek->id }}" class="bg-rzvg-50">
                                <td class="px-4 py-2 text-gray-500">{{ $dagboek->number }}</td>
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="name" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                    @error('name') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $dagboek->type->label() }}</td>
                                <x-action-cell click="save" icon="check" title="Opslaan" variant="success" />
                                <x-action-cell click="resetForm" icon="xmark" title="Annuleren" />
                                <td class="w-8"></td>
                            </tr>
                        @else
                            <tr wire:key="dagboek-{{ $dagboek->id }}">
                                <td class="px-4 py-2 text-gray-500">{{ $dagboek->number }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900">
                                    <a href="{{ route('admin.dagboeken.show', $dagboek) }}" class="hover:underline">{{ $dagboek->name }}</a>
                                </td>
                                <td class="px-4 py-2 text-gray-700">
                                    {{ $dagboek->type->label() }}
                                    @if ($dagboek->type->isSingleton())
                                        <span class="text-xs text-gray-400">(vast)</span>
                                    @endif
                                </td>
                                <x-action-cell href="{{ route('admin.dagboeken.show', $dagboek) }}" icon="eye" title="Bekijken" />
                                <x-action-cell click="edit({{ $dagboek->id }})" icon="pencil" title="Bewerken" variant="primary" />
                                @if (! $dagboek->type->isSingleton())
                                    <x-action-cell click="delete({{ $dagboek->id }})" icon="trash" title="Verwijderen" variant="danger" confirm="Dagboek verwijderen?" />
                                @else
                                    <td class="w-8"></td>
                                @endif
                            </tr>
                        @endif
                    @endforeach

                    {{-- Inline: nieuw dagboek toevoegen (eenvoudige tabel — geen apart formulier/modal nodig) --}}
                    @if ($editingId === null)
                        <tr class="bg-gray-50/60 border-t-2 border-dashed border-gray-200">
                            <td class="px-4 py-2 text-gray-400 text-xs">nieuw</td>
                            <td class="px-2 py-2">
                                <input type="text" wire:model="name" placeholder="Naam" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                @error('name') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <select wire:model="type" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                    @foreach ($createableTypes as $t)
                                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                    @endforeach
                                </select>
                                @error('type') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="w-8"></td>
                            <td class="w-8"></td>
                            <td class="w-8 py-2 text-center">
                                <button type="button" wire:click="save" title="Dagboek toevoegen" class="text-rzvg-600 hover:text-rzvg-800">
                                    <x-action-icon name="plus" />
                                </button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>
</div>
