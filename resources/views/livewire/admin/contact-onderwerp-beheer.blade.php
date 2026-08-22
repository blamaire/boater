<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Onderwerpen waaruit een bezoeker kiest op het publieke contactformulier. Elk onderwerp heeft een
        verantwoordelijke persoon die nieuwe verzoeken per mail ontvangt.
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
                    <col>
                    <col class="w-56">
                    <col class="w-8">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Verantwoordelijke</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="2">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($topics as $topic)
                        @if ($editingId === $topic->id)
                            <tr wire:key="topic-{{ $topic->id }}" class="bg-rzvg-50">
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="name" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                    @error('name') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <select wire:model="responsible_person_id" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                        <option value="">— Kies —</option>
                                        @foreach ($persons as $person)
                                            <option value="{{ $person->id }}">{{ $person->fullName() }}</option>
                                        @endforeach
                                    </select>
                                    @error('responsible_person_id') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <x-action-cell click="save" icon="check" title="Opslaan" variant="success" />
                                <x-action-cell click="resetForm" icon="xmark" title="Annuleren" />
                            </tr>
                        @else
                            <tr wire:key="topic-{{ $topic->id }}">
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $topic->name }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $topic->responsible?->fullName() ?? '—' }}</td>
                                <x-action-cell click="edit({{ $topic->id }})" icon="pencil" title="Bewerken" variant="primary" />
                                <x-action-cell click="delete({{ $topic->id }})" icon="trash" title="Verwijderen" variant="danger" confirm="Onderwerp verwijderen?" />
                            </tr>
                        @endif
                    @endforeach

                    {{-- Inline: nieuw onderwerp toevoegen (eenvoudige tabel — geen apart formulier/modal nodig) --}}
                    @if ($editingId === null)
                        <tr class="bg-gray-50/60 border-t-2 border-dashed border-gray-200">
                            <td class="px-2 py-2">
                                <input type="text" wire:model="name" placeholder="Naam" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                @error('name') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <select wire:model="responsible_person_id" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                    <option value="">— Kies —</option>
                                    @foreach ($persons as $person)
                                        <option value="{{ $person->id }}">{{ $person->fullName() }}</option>
                                    @endforeach
                                </select>
                                @error('responsible_person_id') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="w-8"></td>
                            <td class="w-8 py-2 text-center">
                                <button type="button" wire:click="save" title="Onderwerp toevoegen" class="text-rzvg-600 hover:text-rzvg-800">
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
