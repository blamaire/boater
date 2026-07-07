<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
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

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-3">
        <h2 class="font-display text-xl text-gray-900">Mijn reserveringen</h2>
        @if ($myReservations->isEmpty())
            <p class="text-sm text-gray-500 italic">Je hebt op dit moment geen komende reserveringen.</p>
        @else
            <ul class="divide-y divide-gray-100 border border-gray-100 rounded">
                @foreach ($myReservations as $r)
                    <li wire:key="mine-{{ $r->id }}" class="p-3 flex items-center justify-between text-sm gap-3">
                        <div>
                            <div class="font-medium text-gray-900">{{ $r->object->name }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $r->starts_at->translatedFormat('D j M H:i') }} – {{ $r->ends_at->translatedFormat('H:i') }}
                                @if ($r->person_id !== auth()->user()->person->id)
                                    · voor {{ $r->person->first_name }} {{ $r->person->last_name }}
                                @endif
                            </div>
                        </div>
                        <button type="button" wire:click="cancel({{ $r->id }})"
                            onclick="return confirm('Reservering intrekken?');"
                            class="text-xs px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50">
                            Intrekken
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="space-y-3">
        <h2 class="font-display text-xl text-gray-900">Beschikbare objecten</h2>
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <label class="flex items-center gap-2">
                Categorie:
                <select wire:model.live="filterCategoryId" class="border-gray-300 rounded text-sm">
                    <option value="">— Alle —</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <ul class="divide-y divide-gray-100 border border-gray-100 rounded bg-white">
            @forelse ($objects as $obj)
                <li wire:key="obj-{{ $obj->id }}" class="p-4 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="font-medium text-gray-900">
                                {{ $obj->name }}
                                @if ($obj->category->requires_boat_right)
                                    <span class="text-xs text-blue-600" title="Bootrecht vereist">⚓</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $obj->category->name }}@if ($obj->location) · {{ $obj->location }}@endif
                            </div>
                        </div>
                        @if ($selectedObjectId === $obj->id)
                            <button type="button" wire:click="closeForm" class="text-xs text-gray-500 hover:text-gray-700">Sluiten</button>
                        @else
                            <button type="button" wire:click="openForm({{ $obj->id }})"
                                class="text-sm px-3 py-1.5 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                                Reserveren
                            </button>
                        @endif
                    </div>

                    @if ($selectedObjectId === $obj->id)
                        <div class="border-t border-gray-100 pt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            @if ($eligible->count() > 1)
                                <div>
                                    <x-input-label value="Voor wie?" />
                                    <select wire:model="selectedPersonId" class="mt-1 w-full border-gray-300 rounded text-sm">
                                        @foreach ($eligible as $person)
                                            <option value="{{ $person->id }}">{{ $person->first_name }} {{ $person->last_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div>
                                <x-input-label for="r-start" value="Begint op" />
                                <input id="r-start" type="datetime-local" wire:model="startsAt"
                                    class="mt-1 w-full border-gray-300 rounded" />
                                @error('startsAt') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="r-end" value="Eindigt op" />
                                <input id="r-end" type="datetime-local" wire:model="endsAt"
                                    class="mt-1 w-full border-gray-300 rounded" />
                                @error('endsAt') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="r-note" value="Notitie (optioneel)" />
                                <textarea id="r-note" wire:model="note" rows="2"
                                    class="mt-1 w-full border-gray-300 rounded text-sm"></textarea>
                            </div>
                            <div class="sm:col-span-2 flex justify-end">
                                <button type="button" wire:click="reserve"
                                    class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                                    Bevestigen
                                </button>
                            </div>
                        </div>
                    @endif
                </li>
            @empty
                <li class="p-6 text-center text-sm text-gray-500 italic">Geen beschikbare objecten.</li>
            @endforelse
        </ul>
    </section>
</div>
