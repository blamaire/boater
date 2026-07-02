<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <section class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">Persoonsgegevens</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700" for="first_name">Voornaam</label>
                <input type="text" id="first_name" wire:model="first_name"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
                @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700" for="last_name_prefix">Tussenvoegsel</label>
                <input type="text" id="last_name_prefix" wire:model="last_name_prefix"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
                @error('last_name_prefix') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700" for="last_name">Achternaam</label>
                <input type="text" id="last_name" wire:model="last_name"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
                @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700" for="date_of_birth">Geboortedatum</label>
                <input type="date" id="date_of_birth" wire:model="date_of_birth"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
                @error('date_of_birth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700" for="email">E-mailadres</label>
                <input type="email" id="email" wire:model="email"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700" for="phone">Telefoonnummer</label>
                <input type="text" id="phone" wire:model="phone"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
                @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="button" wire:click="savePerson"
                    class="inline-flex items-center px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 transition">
                Opslaan
            </button>
        </div>
    </section>

    <section class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">Lidmaatschap(pen)</h2>

        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vorm</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Startdatum</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Einddatum</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->person->memberships as $lidm)
                    <tr>
                        <td class="px-3 py-2 text-sm text-gray-900">{{ $lidm->type?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-sm text-gray-600">{{ optional($lidm->start_date)->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-2 text-sm text-gray-600">{{ optional($lidm->end_date)->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-2 text-sm">
                            <select wire:change="updateMembershipStatus({{ $lidm->id }}, $event.target.value)"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 text-xs">
                                @foreach ($statussen as $s)
                                    <option value="{{ $s->value }}" @selected($lidm->status->value === $s->value)>{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-3 py-2 text-sm text-right">
                            <button type="button" wire:click="$set('endingMembershipId', {{ $lidm->id }})"
                                    class="text-red-600 hover:text-red-800 text-xs">Beëindigen</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-gray-500 text-sm">Nog geen lidmaatschappen.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($endingMembershipId !== null)
            <div class="bg-gray-50 border border-gray-200 rounded p-4 space-y-3">
                <h3 class="text-sm font-medium text-gray-900">Lidmaatschap beëindigen</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700" for="endingEndDate">Einddatum</label>
                        <input type="date" id="endingEndDate" wire:model="endingMembershipEndDate"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
                        @error('endingMembershipEndDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end justify-end gap-2">
                        <button type="button" wire:click="$set('endingMembershipId', null)"
                                class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Annuleren</button>
                        <button type="button" wire:click="endMembership"
                                class="px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm">
                            Bevestigen
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <section class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">Nieuw lidmaatschap toekennen</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700" for="newMembershipTypeId">Lidmaatschapsvorm</label>
                <select id="newMembershipTypeId" wire:model="newMembershipTypeId"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm">
                    <option value="">— Kies een vorm —</option>
                    @foreach ($this->membershipTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                @error('newMembershipTypeId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700" for="newMembershipStartDate">Startdatum (optioneel)</label>
                <input type="date" id="newMembershipStartDate" wire:model="newMembershipStartDate"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
            </div>
            <div class="flex items-end justify-end">
                <button type="button" wire:click="grantMembership"
                        class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">
                    Toekennen
                </button>
            </div>
        </div>
    </section>
</div>
