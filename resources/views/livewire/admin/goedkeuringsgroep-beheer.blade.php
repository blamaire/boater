<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Centrale lijst van goedkeuringsgroepen. Elke policy in de goedkeuringsmotor wijst naar één van deze groepen — de kruisverwijzing zie je per groep. Beheerders zitten automatisch in elke groep, dus een leeg gelaten groep blokkeert nooit de flow.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <section class="space-y-3">
        @forelse ($groups as $g)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" wire:key="group-{{ $g->id }}">
                <div class="p-4 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900">{{ $g->name }}</div>
                        @if ($g->description)
                            <div class="text-sm text-gray-600">{{ $g->description }}</div>
                        @endif
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $g->members->count() }} expliciete leden · {{ $beheerders->count() }} beheerders (automatisch)
                        </div>
                        @php
                            $refs = $policiesByGroup->get($g->id, collect());
                        @endphp
                        @if ($refs->isNotEmpty())
                            <div class="mt-2 text-xs text-gray-600">
                                Gebruikt door
                                <span class="text-gray-800">{{ $refs->pluck('name')->implode(', ') }}</span>
                            </div>
                        @else
                            <div class="mt-2 text-xs text-yellow-700 italic">Nog niet aan een policy gekoppeld</div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 whitespace-nowrap">
                        <button type="button" wire:click="toggle({{ $g->id }})" class="text-xs text-rzvg-700 hover:text-rzvg-900">
                            {{ $expandedId === $g->id ? 'Verbergen' : 'Leden openen' }}
                        </button>
                        <button type="button" wire:click="edit({{ $g->id }})" class="text-xs text-gray-600 hover:text-gray-900">Wijzigen</button>
                        <button type="button" wire:click="delete({{ $g->id }})"
                            onclick="return confirm('Groep verwijderen?');"
                            class="text-xs text-red-600 hover:text-red-800">Verwijderen</button>
                    </div>
                </div>

                @if ($expandedId === $g->id)
                    <div class="border-t border-gray-100 p-4 bg-gray-50 space-y-4">
                        <div>
                            <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Beheerders (automatisch, niet te verwijderen hier)</div>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($beheerders as $b)
                                    <span class="inline-flex items-center rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-xs text-blue-800">
                                        {{ $b->first_name }} {{ $b->last_name }}
                                    </span>
                                @empty
                                    <span class="text-xs text-gray-400 italic">Geen personen met de Beheerder-rol.</span>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Expliciete leden</div>
                            <div class="flex flex-wrap gap-1 mb-2">
                                @forelse ($g->members as $m)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-xs">
                                        {{ $m->first_name }} {{ $m->last_name }}
                                        <button type="button" wire:click="removeMember({{ $g->id }}, {{ $m->id }})"
                                            class="text-red-600 hover:text-red-800" title="Verwijderen">×</button>
                                    </span>
                                @empty
                                    <span class="text-xs text-gray-400 italic">Nog geen expliciete leden.</span>
                                @endforelse
                            </div>
                            <div class="flex items-center gap-2">
                                <select wire:model="addMemberInput" class="border-gray-300 rounded shadow-sm text-xs">
                                    <option value="">— Kies persoon —</option>
                                    @foreach ($personsForAssignment as $p)
                                        <option value="{{ $p->id }}">{{ $p->first_name }} {{ $p->last_name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="addMember({{ $g->id }})"
                                    class="text-xs px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-50">Toevoegen</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500 italic">Nog geen groepen. Standaard zijn Redactie, Ledenadministratie en Materialen aangelegd.</p>
        @endforelse
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">{{ $editingId ? 'Groep wijzigen' : 'Nieuwe groep' }}</h2>
        <div class="grid grid-cols-1 gap-4 text-sm">
            <div>
                <x-input-label for="grp-name" value="Naam" />
                <x-text-input id="grp-name" wire:model="name" class="mt-1 w-full" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="grp-desc" value="Omschrijving (optioneel)" />
                <textarea id="grp-desc" wire:model="description" rows="2" class="mt-1 block w-full border-gray-300 rounded shadow-sm"></textarea>
            </div>
        </div>
        <div class="flex justify-between">
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="text-sm px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Annuleren</button>
            @else
                <span></span>
            @endif
            <button type="button" wire:click="save"
                class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                {{ $editingId ? 'Opslaan' : 'Toevoegen' }}
            </button>
        </div>
    </section>
</div>
