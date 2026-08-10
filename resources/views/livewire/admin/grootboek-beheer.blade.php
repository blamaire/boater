<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Grootboek (§23.3): rekeningen, gegroepeerd via verdichtingen onder een hoofdverdichting
        (hoofdverdichting &rarr; verdichting &rarr; grootboekrekening).
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

    {{-- Hoofdverdichtingen --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-medium text-gray-900">{{ $hvEditingId ? 'Hoofdverdichting bewerken' : 'Nieuwe hoofdverdichting' }}</h2>
            @if ($hvEditingId)
                <button type="button" wire:click="resetHoofdverdichtingForm" class="text-sm text-rzvg-600 hover:text-rzvg-800">+ Nieuwe hoofdverdichting</button>
            @endif
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="text-sm">
                <span class="text-gray-600 text-xs block">Code</span>
                <input type="text" wire:model="hvCode" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('hvCode') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
            </label>
            <label class="text-sm sm:col-span-2">
                <span class="text-gray-600 text-xs block">Naam</span>
                <input type="text" wire:model="hvName" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('hvName') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
            </label>
        </div>
        <button type="button" wire:click="saveHoofdverdichting"
            class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
            {{ $hvEditingId ? 'Opslaan' : 'Aanmaken' }}
        </button>

        @if ($hoofdverdichtingen->isNotEmpty())
            <table class="min-w-full text-sm border-t border-gray-100 pt-2">
                <tbody class="divide-y divide-gray-100">
                    @foreach ($hoofdverdichtingen as $hv)
                        <tr wire:key="hv-{{ $hv->id }}" @class(['bg-rzvg-50' => $hvEditingId === $hv->id])>
                            <td class="py-1.5 pr-4 text-gray-500 w-24">{{ $hv->code }}</td>
                            <td class="py-1.5 text-gray-800">{{ $hv->name }}</td>
                            <td class="py-1.5 text-right whitespace-nowrap">
                                <button type="button" wire:click="editHoofdverdichting({{ $hv->id }})" class="text-rzvg-600 hover:text-rzvg-800 text-xs">Bewerken</button>
                                <button type="button" wire:click="deleteHoofdverdichting({{ $hv->id }})"
                                    onclick="return confirm('Hoofdverdichting verwijderen?');"
                                    class="ml-2 text-red-600 hover:text-red-800 text-xs">Verwijderen</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- Verdichtingen --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-medium text-gray-900">{{ $vEditingId ? 'Verdichting bewerken' : 'Nieuwe verdichting' }}</h2>
            @if ($vEditingId)
                <button type="button" wire:click="resetVerdichtingForm" class="text-sm text-rzvg-600 hover:text-rzvg-800">+ Nieuwe verdichting</button>
            @endif
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="text-sm">
                <span class="text-gray-600 text-xs block">Code</span>
                <input type="text" wire:model="vCode" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('vCode') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
            </label>
            <label class="text-sm">
                <span class="text-gray-600 text-xs block">Naam</span>
                <input type="text" wire:model="vName" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('vName') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
            </label>
            <label class="text-sm">
                <span class="text-gray-600 text-xs block">Hoofdverdichting</span>
                <select wire:model="vHoofdverdichtingId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    <option value="">— Kies —</option>
                    @foreach ($hoofdverdichtingen as $hv)
                        <option value="{{ $hv->id }}">{{ $hv->code }} · {{ $hv->name }}</option>
                    @endforeach
                </select>
                @error('vHoofdverdichtingId') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
            </label>
        </div>
        <button type="button" wire:click="saveVerdichting"
            class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
            {{ $vEditingId ? 'Opslaan' : 'Aanmaken' }}
        </button>

        @if ($verdichtingen->isNotEmpty())
            <table class="min-w-full text-sm border-t border-gray-100 pt-2">
                <tbody class="divide-y divide-gray-100">
                    @foreach ($verdichtingen as $v)
                        <tr wire:key="v-{{ $v->id }}" @class(['bg-rzvg-50' => $vEditingId === $v->id])>
                            <td class="py-1.5 pr-4 text-gray-500 w-24">{{ $v->code }}</td>
                            <td class="py-1.5 text-gray-800">{{ $v->name }}</td>
                            <td class="py-1.5 text-gray-500 text-xs">{{ $v->hoofdverdichting->code }} · {{ $v->hoofdverdichting->name }}</td>
                            <td class="py-1.5 text-right whitespace-nowrap">
                                <button type="button" wire:click="editVerdichting({{ $v->id }})" class="text-rzvg-600 hover:text-rzvg-800 text-xs">Bewerken</button>
                                <button type="button" wire:click="deleteVerdichting({{ $v->id }})"
                                    onclick="return confirm('Verdichting verwijderen?');"
                                    class="ml-2 text-red-600 hover:text-red-800 text-xs">Verwijderen</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- Grootboekrekeningen --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-medium text-gray-900">{{ $editingId ? 'Grootboekrekening bewerken' : 'Nieuwe grootboekrekening' }}</h2>
            @if ($editingId)
                <button type="button" wire:click="resetForm" class="text-sm text-rzvg-600 hover:text-rzvg-800">+ Nieuwe rekening</button>
            @endif
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-gray-600">Code</span>
                <input type="text" wire:model="code" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('code') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Naam</span>
                <input type="text" wire:model="name" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Rubriek</span>
                <select wire:model="type" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
                @error('type') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm">
                <span class="text-gray-600">Verdichting <span class="text-gray-400">(optioneel)</span></span>
                <select wire:model="verdichtingId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    <option value="">— Geen —</option>
                    @foreach ($verdichtingen as $v)
                        <option value="{{ $v->id }}">{{ $v->code }} · {{ $v->name }}</option>
                    @endforeach
                </select>
                @error('verdichtingId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
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
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rubriek</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Verdichting</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($accounts as $account)
                        <tr wire:key="account-{{ $account->id }}" @class(['bg-rzvg-50' => $editingId === $account->id])>
                            <td class="px-4 py-2 text-gray-700">{{ $account->code }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $account->name }}</td>
                            <td class="px-4 py-2 text-gray-700 text-xs">{{ $account->type->label() }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">
                                {{ $account->verdichting ? $account->verdichting->code.' · '.$account->verdichting->name : '—' }}
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <button type="button" wire:click="edit({{ $account->id }})" class="text-rzvg-600 hover:text-rzvg-800 text-xs">Bewerken</button>
                                <button type="button" wire:click="delete({{ $account->id }})"
                                    onclick="return confirm('Grootboekrekening verwijderen?');"
                                    class="ml-2 text-red-600 hover:text-red-800 text-xs">Verwijderen</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Nog geen grootboekrekeningen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
