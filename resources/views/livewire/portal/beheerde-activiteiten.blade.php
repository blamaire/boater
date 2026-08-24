<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Activiteiten waarvoor jij als beheerder bent aangewezen: je ziet de inschrijvingen en kunt de basisvelden wijzigen.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    @forelse ($activities as $activity)
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" wire:key="activity-{{ $activity->id }}">
            <div class="p-4 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="font-medium text-gray-900">{{ $activity->title }}</div>
                    <div class="text-sm text-gray-600">
                        {{ $activity->starts_at->format('d-m-Y H:i') }} · {{ $activity->category->name }}
                        · {{ $activity->enrolledCount() }}@if ($activity->capacity) / {{ $activity->capacity }}@endif inschrijvingen
                    </div>
                    @php $own = $activity->managers->firstWhere('id', $ownPersonId); @endphp
                    @if ($own)
                        <label class="inline-flex items-center gap-1 mt-1 text-xs text-gray-500">
                            <input type="checkbox" wire:click="toggleOwnNotify({{ $activity->id }})"
                                @checked($own->pivot->notify) class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600 h-3 w-3" />
                            Mail mij bij wijzigingen/in- en uitschrijvingen
                        </label>
                    @endif
                </div>
                <button type="button" wire:click="editActivity({{ $activity->id }})" class="text-sm text-rzvg-600 hover:text-rzvg-800 whitespace-nowrap">
                    Wijzigen
                </button>
            </div>

            @if ($editingId === $activity->id)
                <div class="border-t border-gray-100 p-4 bg-gray-50 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <x-input-label value="Titel" />
                            <x-text-input wire:model="title" class="mt-1 w-full" />
                            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input-label value="Locatie" />
                            <x-text-input wire:model="location" class="mt-1 w-full" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Omschrijving" />
                            <textarea wire:model="description" rows="3" class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600"></textarea>
                        </div>
                        <div>
                            <x-input-label value="Begint op" />
                            <input type="datetime-local" wire:model="startsAt" class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            @error('startsAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input-label value="Eindigt op" />
                            <input type="datetime-local" wire:model="endsAt" class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            @error('endsAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input-label value="Capaciteit" />
                            <x-text-input type="number" min="1" wire:model="capacity" class="mt-1 w-full" />
                        </div>
                        <div>
                            <x-input-label value="Status" />
                            <select wire:model="status" class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                                @foreach ($statuses as $stat)
                                    <option value="{{ $stat->value }}">{{ $stat->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <button type="button" wire:click="cancelEdit" class="text-sm px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Annuleren</button>
                        <button type="button" wire:click="save" class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">Opslaan</button>
                    </div>
                </div>
            @endif

            <div class="border-t border-gray-100 p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Inschrijvingen</div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($activity->enrollments as $enrollment)
                        <li class="py-1.5 flex items-center justify-between">
                            <span>{{ $enrollment->person->first_name }} {{ $enrollment->person->last_name }}</span>
                            <span class="text-xs text-gray-500">{{ $enrollment->status->label() }}</span>
                        </li>
                    @empty
                        <li class="py-1.5 text-gray-400 italic">Nog geen inschrijvingen.</li>
                    @endforelse
                </ul>
            </div>
        </section>
    @empty
        <p class="text-sm text-gray-500 italic">Je bent voor geen enkele activiteit als beheerder aangewezen.</p>
    @endforelse
</div>
