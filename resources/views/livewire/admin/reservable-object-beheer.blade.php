<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
            Beheer de reserveerbare objecten. Objecten op "Buiten gebruik" kunnen niet worden gereserveerd (§18/22).
        </p>
        <a href="{{ route('admin.object-categories.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">→ Categorieën</a>
    </div>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-4 text-sm">
        <label class="flex items-center gap-2">
            Categorie:
            <select wire:model.live="filterCategoryId" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2">
            Status:
            <select wire:model.live="filterStatus" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="all">Alle</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Categorie</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Locatie</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($objects as $obj)
                    <tr>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $obj->name }}</td>
                        <td class="px-4 py-2 text-gray-700">
                            {{ $obj->category->name }}
                            @if ($obj->category->requires_boat_right)
                                <span class="text-xs text-blue-600" title="Botengebruik-recht vereist">⚓</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $obj->location ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($obj->status === \App\Enums\ReservableObjectStatus::Available)
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 border border-green-200">Beschikbaar</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700 border border-amber-200">Buiten gebruik</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $obj->id }})" class="text-rzvg-600 hover:text-rzvg-800">Wijzigen</button>
                            <button type="button" wire:click="delete({{ $obj->id }})"
                                onclick="return confirm('Object én bestaande reserveringen definitief verwijderen?');"
                                class="text-red-600 hover:text-red-800">Verwijderen</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">Geen objecten met de huidige filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">{{ $editingId ? 'Object wijzigen' : 'Nieuw object' }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <x-input-label for="obj-name" value="Naam" />
                <x-text-input id="obj-name" wire:model="name" class="mt-1 w-full" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="obj-cat" value="Categorie" />
                <select id="obj-cat" wire:model="categoryId"
                    class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                    <option value="">— Kies —</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('categoryId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="obj-loc" value="Locatie (optioneel)" />
                <x-text-input id="obj-loc" wire:model="location" class="mt-1 w-full" />
            </div>
            <div>
                <x-input-label for="obj-status" value="Status" />
                <select id="obj-status" wire:model="status"
                    class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
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
