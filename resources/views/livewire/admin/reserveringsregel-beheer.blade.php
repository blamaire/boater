<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Drempels per objectcategorie. Een regel geldt automatisch ook voor subcategorieën (§18.4). Overschrijding blokkeert niet — de aanvraag gaat dan via de goedkeuringsmotor.
    </p>

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
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Categorie</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Limiet</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Scope</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rules as $r)
                    <tr>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $r->name }}</td>
                        <td class="px-4 py-2 text-gray-700">
                            {{ $r->category->name }}
                            @if ($r->category->parent)
                                <span class="text-xs text-gray-400">(onder {{ $r->category->parent->name }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $r->constraint_type->label() }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $r->limit_value }} <span class="text-xs text-gray-400">{{ $r->constraint_type->unit() }}</span></td>
                        <td class="px-4 py-2 text-gray-700">{{ $r->per_person ? 'per persoon' : 'in totaal' }}</td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $r->id }})" class="text-rzvg-600 hover:text-rzvg-800">Wijzigen</button>
                            <button type="button" wire:click="delete({{ $r->id }})"
                                onclick="return confirm('Regel verwijderen?');"
                                class="text-red-600 hover:text-red-800">Verwijderen</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Nog geen regels. Zonder regels is elke aanvraag binnen beleid (bij eigen boekingen).</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">{{ $editingId ? 'Regel wijzigen' : 'Nieuwe regel' }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <x-input-label for="rule-name" value="Naam" />
                <x-text-input id="rule-name" wire:model="name" class="mt-1 w-full" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="rule-cat" value="Categorie (geldt inclusief sub-categorieën)" />
                <select id="rule-cat" wire:model="categoryId" class="mt-1 w-full border-gray-300 rounded shadow-sm">
                    <option value="">— Kies categorie —</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('categoryId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="rule-type" value="Type drempel" />
                <select id="rule-type" wire:model="constraintType" class="mt-1 w-full border-gray-300 rounded shadow-sm">
                    @foreach ($constraintTypes as $ct)
                        <option value="{{ $ct->value }}">{{ $ct->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="rule-limit" value="Limiet" />
                <x-text-input id="rule-limit" type="number" wire:model="limitValue" class="mt-1 w-full" />
                @error('limitValue') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="perPerson"
                        class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                    Per persoon (uitgevinkt = telt over alle leden samen)
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
