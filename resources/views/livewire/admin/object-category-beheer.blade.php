<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
            Categorieën waarin reserveerbare objecten worden ingedeeld. "Vaartuig" markeren betekent dat de reserveerder bootrecht nodig heeft.
        </p>
        <a href="{{ route('admin.reservable-objects.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">→ Objecten</a>
    </div>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bootrecht</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Volgorde</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Objecten</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($categories as $cat)
                    <tr>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $cat->name }}</td>
                        <td class="px-4 py-2 text-gray-500 text-xs font-mono">{{ $cat->slug }}</td>
                        <td class="px-4 py-2">
                            @if ($cat->requires_boat_right)
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700 border border-blue-200">vereist</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $cat->sort_order }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $cat->objects()->count() }}</td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $cat->id }})" class="text-rzvg-600 hover:text-rzvg-800">Wijzigen</button>
                            <button type="button" wire:click="delete({{ $cat->id }})"
                                onclick="return confirm('Categorie verwijderen?');"
                                class="text-red-600 hover:text-red-800">Verwijderen</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Nog geen categorieën.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">{{ $editingId ? 'Categorie wijzigen' : 'Nieuwe categorie' }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <x-input-label for="cat-name" value="Naam" />
                <x-text-input id="cat-name" wire:model="name" class="mt-1 w-full" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="cat-sort" value="Volgorde (lager = eerst)" />
                <x-text-input id="cat-sort" type="number" wire:model="sortOrder" class="mt-1 w-full" />
            </div>
            <div class="sm:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="requiresBoatRight"
                        class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                    Vaartuig — reserveerder heeft bootrecht nodig
                </label>
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
