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
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <h2 class="font-medium text-gray-900 px-5 pt-4 pb-2">Hoofdverdichtingen</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col class="w-24">
                    <col>
                    <col class="w-8">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="2">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($hoofdverdichtingen as $hv)
                        @if ($hvEditingId === $hv->id)
                            <tr wire:key="hv-{{ $hv->id }}" class="bg-rzvg-50">
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="hvCode" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                    @error('hvCode') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="hvName" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                    @error('hvName') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <x-action-cell click="saveHoofdverdichting" icon="check" title="Opslaan" variant="success" />
                                <x-action-cell click="resetHoofdverdichtingForm" icon="xmark" title="Annuleren" />
                            </tr>
                        @else
                            <tr wire:key="hv-{{ $hv->id }}">
                                <td class="py-2 px-4 text-gray-500">{{ $hv->code }}</td>
                                <td class="py-2 px-4 text-gray-800">{{ $hv->name }}</td>
                                <x-action-cell click="editHoofdverdichting({{ $hv->id }})" icon="pencil" title="Bewerken" variant="primary" />
                                <x-action-cell click="deleteHoofdverdichting({{ $hv->id }})" icon="trash" title="Verwijderen" variant="danger" confirm="Hoofdverdichting verwijderen?" />
                            </tr>
                        @endif
                    @endforeach

                    @if ($hvEditingId === null)
                        <tr class="bg-gray-50/60 border-t-2 border-dashed border-gray-200">
                            <td class="px-2 py-2">
                                <input type="text" wire:model="hvCode" placeholder="Code" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                @error('hvCode') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <input type="text" wire:model="hvName" placeholder="Naam" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                @error('hvName') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="w-8"></td>
                            <td class="w-8 py-2 text-center">
                                <button type="button" wire:click="saveHoofdverdichting" title="Hoofdverdichting toevoegen" class="text-rzvg-600 hover:text-rzvg-800">
                                    <x-action-icon name="plus" />
                                </button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>

    {{-- Verdichtingen --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <h2 class="font-medium text-gray-900 px-5 pt-4 pb-2">Verdichtingen</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col class="w-24">
                    <col>
                    <col class="w-56">
                    <col class="w-8">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Hoofdverdichting</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="2">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($verdichtingen as $v)
                        @if ($vEditingId === $v->id)
                            <tr wire:key="v-{{ $v->id }}" class="bg-rzvg-50">
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="vCode" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                    @error('vCode') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="vName" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                    @error('vName') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <select wire:model="vHoofdverdichtingId" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                        <option value="">— Kies —</option>
                                        @foreach ($hoofdverdichtingen as $hv)
                                            <option value="{{ $hv->id }}">{{ $hv->code }} · {{ $hv->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('vHoofdverdichtingId') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <x-action-cell click="saveVerdichting" icon="check" title="Opslaan" variant="success" />
                                <x-action-cell click="resetVerdichtingForm" icon="xmark" title="Annuleren" />
                            </tr>
                        @else
                            <tr wire:key="v-{{ $v->id }}">
                                <td class="py-2 px-4 text-gray-500">{{ $v->code }}</td>
                                <td class="py-2 px-4 text-gray-800">{{ $v->name }}</td>
                                <td class="py-2 px-4 text-gray-500 text-xs">{{ $v->hoofdverdichting->code }} · {{ $v->hoofdverdichting->name }}</td>
                                <x-action-cell click="editVerdichting({{ $v->id }})" icon="pencil" title="Bewerken" variant="primary" />
                                <x-action-cell click="deleteVerdichting({{ $v->id }})" icon="trash" title="Verwijderen" variant="danger" confirm="Verdichting verwijderen?" />
                            </tr>
                        @endif
                    @endforeach

                    @if ($vEditingId === null)
                        <tr class="bg-gray-50/60 border-t-2 border-dashed border-gray-200">
                            <td class="px-2 py-2">
                                <input type="text" wire:model="vCode" placeholder="Code" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                @error('vCode') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <input type="text" wire:model="vName" placeholder="Naam" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                @error('vName') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <select wire:model="vHoofdverdichtingId" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                    <option value="">— Kies —</option>
                                    @foreach ($hoofdverdichtingen as $hv)
                                        <option value="{{ $hv->id }}">{{ $hv->code }} · {{ $hv->name }}</option>
                                    @endforeach
                                </select>
                                @error('vHoofdverdichtingId') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="w-8"></td>
                            <td class="w-8 py-2 text-center">
                                <button type="button" wire:click="saveVerdichting" title="Verdichting toevoegen" class="text-rzvg-600 hover:text-rzvg-800">
                                    <x-action-icon name="plus" />
                                </button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>

    {{-- Grootboekrekeningen --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <h2 class="font-medium text-gray-900 px-5 pt-4 pb-2">Grootboekrekeningen</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col class="w-24">
                    <col>
                    <col class="w-32">
                    <col class="w-56">
                    <col class="w-8">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rubriek</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Verdichting</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="2">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($accounts as $account)
                        @if ($editingId === $account->id)
                            <tr wire:key="account-{{ $account->id }}" class="bg-rzvg-50">
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="code" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                    @error('code') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="name" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                    @error('name') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <select wire:model="type" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                        @foreach ($types as $t)
                                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('type') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <select wire:model="verdichtingId" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                        <option value="">— Geen —</option>
                                        @foreach ($verdichtingen as $v)
                                            <option value="{{ $v->id }}">{{ $v->code }} · {{ $v->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('verdichtingId') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                </td>
                                <x-action-cell click="save" icon="check" title="Opslaan" variant="success" />
                                <x-action-cell click="resetForm" icon="xmark" title="Annuleren" />
                            </tr>
                        @else
                            <tr wire:key="account-{{ $account->id }}">
                                <td class="px-4 py-2 text-gray-700">{{ $account->code }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $account->name }}</td>
                                <td class="px-4 py-2 text-gray-700 text-xs">{{ $account->type->label() }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs whitespace-nowrap">
                                    {{ $account->verdichting ? $account->verdichting->code.' · '.$account->verdichting->name : '—' }}
                                </td>
                                <x-action-cell click="edit({{ $account->id }})" icon="pencil" title="Bewerken" variant="primary" />
                                <x-action-cell click="delete({{ $account->id }})" icon="trash" title="Verwijderen" variant="danger" confirm="Grootboekrekening verwijderen?" />
                            </tr>
                        @endif
                    @endforeach

                    @if ($editingId === null)
                        <tr class="bg-gray-50/60 border-t-2 border-dashed border-gray-200">
                            <td class="px-2 py-2">
                                <input type="text" wire:model="code" placeholder="Code" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                @error('code') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <input type="text" wire:model="name" placeholder="Naam" class="block w-full border-gray-300 rounded shadow-sm text-sm" />
                                @error('name') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <select wire:model="type" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                    @foreach ($types as $t)
                                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                    @endforeach
                                </select>
                                @error('type') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-2 py-2">
                                <select wire:model="verdichtingId" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                    <option value="">— Geen —</option>
                                    @foreach ($verdichtingen as $v)
                                        <option value="{{ $v->id }}">{{ $v->code }} · {{ $v->name }}</option>
                                    @endforeach
                                </select>
                                @error('verdichtingId') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                            </td>
                            <td class="w-8"></td>
                            <td class="w-8 py-2 text-center">
                                <button type="button" wire:click="save" title="Grootboekrekening toevoegen" class="text-rzvg-600 hover:text-rzvg-800">
                                    <x-action-icon name="plus" />
                                </button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>
</div>
