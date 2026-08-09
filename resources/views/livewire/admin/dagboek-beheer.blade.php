<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Dagboeken: Verkoop, Inkoop en Memoriaal staan altijd klaar (één per administratie). Extra Bank- of
        Kas-dagboeken (bv. per bankrekening of kas) maak je hier zelf aan.
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

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-medium text-gray-900">{{ $editingId ? 'Dagboek bewerken' : 'Nieuw dagboek' }}</h2>
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="text-sm text-rzvg-600 hover:text-rzvg-800">+ Nieuw dagboek</button>
            @endif
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-gray-600">Naam</span>
                <input type="text" wire:model="name" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Soort</span>
                @if ($editingId)
                    <input type="text" disabled value="{{ \App\Enums\DagboekType::from($type)->label() }}"
                        class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm bg-gray-50 text-gray-500" />
                @else
                    <select wire:model="type" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                        @foreach ($createableTypes as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                @endif
                @error('type') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="flex gap-2">
            <button type="button" wire:click="save"
                class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
                {{ $editingId ? 'Opslaan' : 'Aanmaken' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="resetForm"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Sluiten</button>
            @endif
        </div>
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Soort</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($dagboeken as $dagboek)
                        <tr wire:key="dagboek-{{ $dagboek->id }}" @class(['bg-rzvg-50' => $editingId === $dagboek->id])>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $dagboek->name }}</td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ $dagboek->type->label() }}
                                @if ($dagboek->type->isSingleton())
                                    <span class="text-xs text-gray-400">(vast)</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <button type="button" wire:click="edit({{ $dagboek->id }})" class="text-rzvg-600 hover:text-rzvg-800 text-xs">Bewerken</button>
                                @unless ($dagboek->type->isSingleton())
                                    <button type="button" wire:click="delete({{ $dagboek->id }})"
                                        onclick="return confirm('Dagboek verwijderen?');"
                                        class="ml-2 text-red-600 hover:text-red-800 text-xs">Verwijderen</button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Nog geen dagboeken.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
