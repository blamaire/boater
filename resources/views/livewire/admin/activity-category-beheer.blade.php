<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
            Beheer de categorieën die je bij een activiteit kunt kiezen. Categorie met gekoppelde activiteiten kan niet verwijderd worden.
        </p>
        <a href="{{ route('admin.activities.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">← Activiteiten</a>
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
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Volgorde</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aantal</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($categories as $cat)
                    <tr>
                        <td class="px-4 py-2 text-gray-900 font-medium">{{ $cat->name }}</td>
                        <td class="px-4 py-2 text-gray-500 text-xs font-mono">{{ $cat->slug }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $cat->sort_order }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $cat->activities()->count() }}</td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $cat->id }})" class="text-rzvg-600 hover:text-rzvg-800">Wijzigen</button>
                            <button type="button" wire:click="delete({{ $cat->id }})"
                                onclick="return confirm('Categorie verwijderen?');"
                                class="text-red-600 hover:text-red-800">Verwijderen</button>
                        </td>
                    </tr>
                @endforeach
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
                @error('sortOrder') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
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
