<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Beheer alle inlog-accounts van de vereniging: leden, ouders/verzorgers en externe functionarissen. Bij aanmaken kun je een uitnodigingsmail met wachtwoord-link versturen.
    </p>

    <x-policy-reference subject="membership.application" />
    <x-policy-reference subject="person.field_update" />

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <div class="flex-1 max-w-sm">
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Zoek op naam of e-mail…" class="w-full text-sm" />
        </div>
        <button type="button" wire:click="toggleForm"
            class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
            {{ $showForm ? 'Annuleren' : 'Nieuwe gebruiker' }}
        </button>
    </div>

    @if ($showForm)
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            <h2 class="font-display text-xl text-gray-900">Nieuwe gebruiker</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <x-input-label for="new-first-name" value="Voornaam" />
                    <x-text-input id="new-first-name" wire:model="firstName" class="mt-1 w-full" />
                    @error('firstName') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="new-last-name-prefix" value="Tussenvoegsel" />
                    <x-text-input id="new-last-name-prefix" wire:model="lastNamePrefix" class="mt-1 w-full" placeholder="van, de, …" />
                </div>
                <div>
                    <x-input-label for="new-last-name" value="Achternaam" />
                    <x-text-input id="new-last-name" wire:model="lastName" class="mt-1 w-full" />
                    @error('lastName') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="new-email" value="E-mailadres" />
                    <x-text-input id="new-email" type="email" wire:model="email" class="mt-1 w-full" />
                    @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="new-phone" value="Telefoon (optioneel)" />
                    <x-text-input id="new-phone" wire:model="phone" class="mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="new-dob" value="Geboortedatum (optioneel)" />
                    <x-text-input id="new-dob" type="date" wire:model="dateOfBirth" class="mt-1 w-full" />
                    @error('dateOfBirth') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="new-type" value="Lidmaatschapstype" />
                    <select id="new-type" wire:model.live="membershipTypeId"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                        <option value="">— Kies een type —</option>
                        <optgroup label="Leden">
                            @foreach ($membershipTypes->where('is_member', true) as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Niet-leden">
                            @foreach ($membershipTypes->where('is_member', false) as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                    @error('membershipTypeId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                @php
                    $selectedType = $membershipTypes->firstWhere('id', $membershipTypeId);
                @endphp
                @if ($selectedType && $selectedType->key === 'ouder_verzorger')
                    <div>
                        <x-input-label for="new-related" value="Gekoppeld jeugdlid / aspirantlid" />
                        <select id="new-related" wire:model="relatedPersonId"
                            class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                            <option value="">— Kies een lid —</option>
                            @foreach ($jeugdledenVoorKoppeling as $lid)
                                <option value="{{ $lid->id }}">{{ $lid->first_name }}{{ $lid->last_name_prefix ? ' '.$lid->last_name_prefix : '' }} {{ $lid->last_name }}</option>
                            @endforeach
                        </select>
                        @error('relatedPersonId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label for="new-relation-type" value="Relatie" />
                        <select id="new-relation-type" wire:model="relationType"
                            class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                            <option value="ouder_van">Ouder van</option>
                            <option value="verzorger_van">Verzorger van</option>
                        </select>
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <x-input-label value="Rollen (optioneel)" />
                    <div class="mt-1 flex flex-wrap gap-3">
                        @foreach ($roles as $role)
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" value="{{ $role->id }}" wire:model="roleIds"
                                    class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                                {{ $role->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="sendInvitationMail"
                            class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                        Uitnodigingsmail met wachtwoord-link versturen
                    </label>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" wire:click="save"
                    class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                    Aanmaken
                </button>
            </div>
        </section>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam / e-mail</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rollen</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    @php
                        $person = $user->person;
                        $currentMembership = $person?->memberships->first();
                    @endphp
                    <tr>
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-900">
                                @if ($person)
                                    {{ $person->first_name }}{{ $person->last_name_prefix ? ' '.$person->last_name_prefix : '' }} {{ $person->last_name }}
                                @else
                                    {{ $user->name }}
                                @endif
                            </div>
                            <div class="text-gray-500 text-xs">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                            {{ $currentMembership?->type?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                            @if ($person && $person->roles->isNotEmpty())
                                {{ $person->roles->pluck('name')->join(', ') }}
                            @else
                                <span class="text-gray-400 italic">geen</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if ($user->disabled_at)
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700 border border-red-200">gedeactiveerd</span>
                            @elseif (! $user->email_verified_at)
                                <span class="inline-flex items-center rounded-full bg-yellow-50 px-2 py-0.5 text-xs text-yellow-700 border border-yellow-200">nog niet bevestigd</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 border border-green-200">actief</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            @if ($person)
                                <a href="{{ route('admin.person-permissions.index', $person) }}" class="text-rzvg-600 hover:text-rzvg-800">Rollen &amp; rechten</a>
                            @endif
                            <button type="button" wire:click="resendInvitation({{ $user->id }})"
                                class="text-gray-600 hover:text-gray-800">
                                Uitnodiging&nbsp;opnieuw
                            </button>
                            <button type="button" wire:click="toggleActive({{ $user->id }})"
                                onclick="return confirm('{{ $user->disabled_at ? 'Account weer activeren?' : 'Account deactiveren?' }}');"
                                class="{{ $user->disabled_at ? 'text-green-700 hover:text-green-900' : 'text-red-600 hover:text-red-800' }}">
                                {{ $user->disabled_at ? 'Activeren' : 'Deactiveren' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                            Geen gebruikers gevonden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
