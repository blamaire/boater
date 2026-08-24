<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
            Een activiteitenpagina bundelt activiteiten die inhoudelijk bij elkaar horen (bv. "Zomerkamp") en krijgt
            een eigen CMS-infopagina. Koppel een activiteit eraan via het activiteitenformulier.
        </p>
        <a href="{{ route('admin.activities.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">← Activiteiten</a>
    </div>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col>
                    <col class="w-24">
                    <col class="w-8">
                    <col class="w-8">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Activiteiten</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="3">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($events as $event)
                        @if ($editingId === $event->id)
                            <tr wire:key="event-{{ $event->id }}" class="bg-rzvg-50">
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="title" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                    @error('title') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $event->activities_count }}</td>
                                <x-action-cell click="save" icon="check" title="Opslaan" variant="success" />
                                <x-action-cell click="resetForm" icon="xmark" title="Annuleren" />
                                <td class="w-8"></td>
                            </tr>
                        @else
                            <tr wire:key="event-{{ $event->id }}">
                                <td class="px-4 py-2 font-medium text-gray-900">
                                    {{ $event->page->title }}
                                    <div class="text-xs text-gray-500 font-mono">{{ $event->page->publicUrl() }}</div>
                                </td>
                                <td class="px-4 py-2 text-gray-700">{{ $event->activities_count }}</td>
                                <x-action-cell href="{{ route('admin.pages.editor', $event->page) }}" icon="eye" title="Infopagina bewerken" variant="primary" />
                                <x-action-cell click="edit({{ $event->id }})" icon="pencil" title="Titel wijzigen" />
                                <x-action-cell click="delete({{ $event->id }})" icon="trash" title="Verwijderen" variant="danger" confirm="Event verwijderen? De infopagina blijft bestaan." />
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                Nog geen activiteitenpagina's.
                            </td>
                        </tr>
                    @endforelse

                    {{-- Inline: nieuw event toevoegen (eenvoudige tabel — geen apart formulier/modal nodig) --}}
                    @if ($editingId === null)
                        <tr class="bg-gray-50/60 border-t-2 border-dashed border-gray-200">
                            <td class="px-2 py-2">
                                <input type="text" wire:model="title" placeholder="Titel" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                @error('title') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-4 py-2"></td>
                            <td class="w-8"></td>
                            <td class="w-8"></td>
                            <td class="w-8 py-2 text-center">
                                <button type="button" wire:click="save" title="Event toevoegen" class="text-rzvg-600 hover:text-rzvg-800">
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
