<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Beheer alle activiteiten van de vereniging: losse voorkomens met capaciteit en wachtlijst. Categorieën beheer je in <a href="{{ route('admin.activity-categories.index') }}" class="text-rzvg-600 hover:text-rzvg-800 underline">categorieën</a>.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4 text-sm">
            <label class="flex items-center gap-2">
                Status:
                <select wire:model.live="filterStatus" class="border-gray-300 rounded shadow-sm text-sm">
                    <option value="all">Alle</option>
                    <option value="concept">Concept</option>
                    <option value="gepubliceerd">Gepubliceerd</option>
                    <option value="afgelast">Afgelast</option>
                </select>
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model.live="hideHistory" class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                Historie verbergen
            </label>
        </div>
        <button type="button" wire:click="toggleForm"
            class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
            {{ $showForm ? 'Annuleren' : ($editingId ? 'Wijzigen annuleren' : 'Nieuwe activiteit') }}
        </button>
    </div>

    @if ($showForm)
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            <h2 class="font-display text-xl text-gray-900">
                {{ $editingId ? 'Activiteit wijzigen' : 'Nieuwe activiteit' }}
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <x-input-label for="a-title" value="Titel" />
                    <x-text-input id="a-title" wire:model="title" class="mt-1 w-full" />
                    @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="a-category" value="Categorie" />
                    <select id="a-category" wire:model="categoryId"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                        <option value="">— Kies —</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="a-description" value="Omschrijving" />
                    <textarea id="a-description" wire:model="description" rows="3"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600"></textarea>
                </div>
                <div>
                    <x-input-label for="a-start" value="Begint op" />
                    <input id="a-start" type="datetime-local" wire:model="startsAt"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                    @error('startsAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="a-end" value="Eindigt op (optioneel)" />
                    <input id="a-end" type="datetime-local" wire:model="endsAt"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                    @error('endsAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="a-location" value="Locatie (optioneel)" />
                    <x-text-input id="a-location" wire:model="location" class="mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="a-capacity" value="Capaciteit (optioneel; leeg = onbeperkt)" />
                    <x-text-input id="a-capacity" type="number" min="1" wire:model="capacity" class="mt-1 w-full" />
                    @error('capacity') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="a-visibility" value="Zichtbaarheid" />
                    <select id="a-visibility" wire:model="visibility"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                        @foreach ($visibilities as $vis)
                            <option value="{{ $vis->value }}">{{ $vis->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="a-status" value="Status" />
                    <select id="a-status" wire:model="status"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                        @foreach ($statuses as $stat)
                            <option value="{{ $stat->value }}">{{ $stat->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" wire:click="save"
                    class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                    {{ $editingId ? 'Opslaan' : 'Aanmaken' }}
                </button>
            </div>
        </section>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Wanneer</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Categorie</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Inschrijvingen</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($activities as $activity)
                    <tr>
                        <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                            {{ $activity->starts_at->format('d-m-Y H:i') }}
                        </td>
                        <td class="px-4 py-2">
                            <a href="{{ route('activiteit.show', $activity) }}" class="font-medium text-rzvg-600 hover:text-rzvg-800">
                                {{ $activity->title }}
                            </a>
                            @if ($activity->location)
                                <div class="text-xs text-gray-500">{{ $activity->location }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $activity->category->name }}</td>
                        <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                            {{ $activity->enrolledCount() }}@if ($activity->capacity) / {{ $activity->capacity }}@endif
                        </td>
                        <td class="px-4 py-2">
                            @php $badge = ['concept' => 'yellow', 'gepubliceerd' => 'green', 'afgelast' => 'red'][$activity->status->value] ?? 'gray'; @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border
                                bg-{{ $badge }}-50 text-{{ $badge }}-700 border-{{ $badge }}-200">
                                {{ $activity->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            <button type="button" wire:click="editActivity({{ $activity->id }})" class="text-rzvg-600 hover:text-rzvg-800">Wijzigen</button>
                            @if ($activity->status !== \App\Enums\ActivityStatus::Cancelled)
                                <button type="button" wire:click="cancel({{ $activity->id }})"
                                    onclick="return confirm('Activiteit afgelasten?');"
                                    class="text-amber-700 hover:text-amber-900">Afgelasten</button>
                            @endif
                            <button type="button" wire:click="delete({{ $activity->id }})"
                                onclick="return confirm('Activiteit definitief verwijderen? Inschrijvingen worden ook gewist.');"
                                class="text-red-600 hover:text-red-800">Verwijderen</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                            Geen activiteiten gevonden met de huidige filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
