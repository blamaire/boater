<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8">
    <header>
        <h1 class="font-display text-3xl text-rzvg-600">Mijn lidmaatschap</h1>
        <p class="text-sm text-gray-500 mt-1">
            Beheer hier je persoonlijke gegevens, adres, lidmaatschap, zichtbaarheid en ICE-contacten.
        </p>
    </header>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    {{-- Openstaande wijzigingen ------------------------------------------ --}}
    @if ($openProposals->isNotEmpty())
        <section class="bg-amber-50 border border-amber-200 rounded-lg p-4 space-y-3">
            <h2 class="font-display text-base text-amber-900">Openstaande wijzigingen</h2>
            <p class="text-xs text-amber-800">
                Deze wijzigingen wachten op beoordeling. Je kunt ze intrekken; opnieuw indienen met een nieuwe waarde overschrijft de vorige aanvraag.
            </p>
            <ul class="space-y-2 text-sm">
                @foreach ($openProposals as $prop)
                    @php
                        $field = $prop->payload['field'] ?? 'onbekend';
                        $newValue = $prop->payload['new_value'] ?? '—';
                        $label = match ($field) {
                            'first_name' => 'Voornaam',
                            'last_name_prefix' => 'Tussenvoegsel',
                            'last_name' => 'Achternaam',
                            'date_of_birth' => 'Geboortedatum',
                            'membership_type_id' => 'Lidmaatschapsvorm',
                            default => $field,
                        };
                        if ($field === 'membership_type_id') {
                            $typeName = \App\Models\MembershipType::query()->whereKey($newValue)->value('name');
                            $newValueLabel = $typeName ?? '—';
                        } else {
                            $newValueLabel = (string) ($newValue ?? '—');
                        }
                    @endphp
                    <li class="flex items-center justify-between bg-white border border-amber-200 rounded px-3 py-2">
                        <div>
                            <span class="font-medium text-gray-900">{{ $label }}</span>
                            <span class="text-gray-600">→ {{ $newValueLabel }}</span>
                            <span class="ml-2 text-xs uppercase tracking-wide text-amber-700">{{ $prop->status->value }}</span>
                        </div>
                        <button type="button" wire:click="withdrawProposal({{ $prop->id }})"
                            class="text-xs px-2 py-1 rounded border border-amber-400 text-amber-800 hover:bg-amber-100">
                            Intrekken
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Persoonsgegevens ------------------------------------------------ --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
        <h2 class="font-display text-xl text-gray-900">Persoonlijke gegevens</h2>

        @if ($personModel)
            <div class="space-y-6">
                {{-- Naam + geboortedatum → via goedkeuring --}}
                <div class="border border-amber-100 bg-amber-50 rounded-md p-4 space-y-3">
                    <p class="text-xs text-amber-800 italic">
                        Wijzigingen aan naam en geboortedatum lopen via een goedkeuringsverzoek en zijn zichtbaar zodra ze zijn goedgekeurd.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <x-input-label for="name-first" value="Voornaam" />
                            <x-text-input id="name-first" wire:model="name.first_name" class="mt-1 w-full" />
                        </div>
                        <div>
                            <x-input-label for="name-prefix" value="Tussenvoegsel" />
                            <x-text-input id="name-prefix" wire:model="name.last_name_prefix" class="mt-1 w-full" />
                        </div>
                        <div>
                            <x-input-label for="name-last" value="Achternaam" />
                            <x-text-input id="name-last" wire:model="name.last_name" class="mt-1 w-full" />
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" wire:click="submitNameChanges"
                            class="text-sm px-3 py-1.5 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                            Naam ter goedkeuring indienen
                        </button>
                    </div>

                    <div class="pt-2 border-t border-amber-200">
                        <x-input-label for="dob" value="Geboortedatum" />
                        <div class="flex items-center gap-2 mt-1">
                            <x-text-input id="dob" type="date" wire:model="date_of_birth" class="flex-1" />
                            <button type="button" wire:click="submitDateOfBirth"
                                class="text-sm px-3 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                                Ter goedkeuring indienen
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Contactgegevens → direct --}}
                <div class="space-y-3">
                    <p class="text-xs text-gray-500 italic">E-mail en telefoon kun je zelf direct wijzigen.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="person-email" value="E-mail" />
                            <x-text-input id="person-email" type="email" wire:model="person.email" class="mt-1 w-full" />
                        </div>
                        <div>
                            <x-input-label for="person-phone" value="Telefoon" />
                            <x-text-input id="person-phone" type="tel" wire:model="person.phone" class="mt-1 w-full" />
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" wire:click="saveContact"
                            class="text-sm px-3 py-1.5 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                            Contactgegevens opslaan
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- Adres ----------------------------------------------------------- --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
        <h2 class="font-display text-xl text-gray-900">Adres</h2>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="abroad" class="rounded border-gray-300 text-rzvg-600">
            <span>Ik woon in het buitenland (geen BAG-koppeling)</span>
        </label>

        @if (! $abroad)
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm items-end">
                <div>
                    <x-input-label for="household-postal_code" value="Postcode" />
                    <x-text-input id="household-postal_code" wire:model="household.postal_code" placeholder="1234AB" class="mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="household-house_number" value="Huisnummer" />
                    <x-text-input id="household-house_number" wire:model="household.house_number" class="mt-1 w-full" />
                </div>
                <div class="sm:col-span-2">
                    <button type="button" wire:click="lookupAddress"
                        class="text-sm px-3 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                        Adres opzoeken (BAG)
                    </button>
                </div>
            </div>
            @if ($bag_error)
                <p class="text-sm text-red-600">{{ $bag_error }}</p>
            @endif
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <x-input-label for="household-street" value="Straat" />
                <x-text-input id="household-street" wire:model="household.street" class="mt-1 w-full" />
            </div>
            <div>
                <x-input-label for="household-city" value="Plaats" />
                <x-text-input id="household-city" wire:model="household.city" class="mt-1 w-full" />
            </div>
            @if ($abroad)
                <div>
                    <x-input-label for="household-house_number-abroad" value="Huisnummer" />
                    <x-text-input id="household-house_number-abroad" wire:model="household.house_number" class="mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="household-postal_code-abroad" value="Postcode (optioneel)" />
                    <x-text-input id="household-postal_code-abroad" wire:model="household.postal_code" class="mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="household-country" value="Land (ISO 2-letter, bv. DE, BE, FR)" />
                    <x-text-input id="household-country" wire:model="household.country" maxlength="2" class="mt-1 w-full" />
                </div>
            @endif
        </div>

        <div class="flex justify-end">
            <button type="button" wire:click="saveAddress"
                class="text-sm px-3 py-1.5 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                Adres opslaan
            </button>
        </div>
    </section>

    {{-- Zichtbaarheid --------------------------------------------------- --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">Zichtbaarheid naar andere leden</h2>
        <p class="text-sm text-gray-500">
            Bepaal per gegeven of andere leden dit mogen zien. Voor de vereniging (bestuur, ledenadministratie) blijven je gegevens altijd zichtbaar.
        </p>
        <ul class="space-y-2 text-sm">
            @foreach (\App\Livewire\Portal\MijnLidmaatschap::VISIBILITY_TOGGLE_FIELDS as $field)
                <li class="flex items-center justify-between">
                    <span class="text-gray-800">
                        {{ match ($field) {
                            'email' => 'E-mailadres',
                            'phone' => 'Telefoonnummer',
                            'date_of_birth' => 'Geboortedatum',
                            default => $field,
                        } }}
                    </span>
                    <button type="button" wire:click="toggleVisibility('{{ $field }}')"
                        wire:key="vis-{{ $field }}"
                        class="text-xs px-3 py-1 rounded-full border
                            @if ($visibility[$field] ?? false) bg-green-50 border-green-300 text-green-800
                            @else bg-gray-50 border-gray-300 text-gray-700 @endif">
                        {{ ($visibility[$field] ?? false) ? 'Zichtbaar' : 'Verborgen' }}
                    </button>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Huidig lidmaatschap --------------------------------------------- --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">Huidig lidmaatschap</h2>

        @if ($currentMembership)
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">Type</dt>
                    <dd class="font-medium text-gray-900">{{ $currentMembership->type->name }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Status</dt>
                    <dd class="font-medium text-gray-900">{{ $currentMembership->status->label() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Startdatum</dt>
                    <dd class="font-medium text-gray-900">{{ $currentMembership->start_date->format('d-m-Y') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Einddatum</dt>
                    <dd class="font-medium text-gray-900">
                        {{ $currentMembership->end_date?->format('d-m-Y') ?? 'Onbepaald' }}
                    </dd>
                </div>
            </dl>

            <div class="pt-3 border-t border-gray-100 flex items-center gap-3">
                @if (! $confirmCancelMembership)
                    <button type="button" wire:click="$set('confirmCancelMembership', true)"
                        class="text-sm px-3 py-1.5 rounded border border-red-300 text-red-700 hover:bg-red-50">
                        Lidmaatschap opzeggen
                    </button>
                @else
                    <span class="text-sm text-red-700">Weet je het zeker? Je einddatum wordt vandaag.</span>
                    <button type="button" wire:click="cancelMembership"
                        class="text-sm px-3 py-1.5 rounded bg-red-600 text-white hover:bg-red-700">
                        Ja, opzeggen
                    </button>
                    <button type="button" wire:click="$set('confirmCancelMembership', false)"
                        class="text-sm px-3 py-1.5 rounded border border-gray-300">
                        Annuleren
                    </button>
                @endif
            </div>
        @else
            <p class="text-sm text-gray-600">Je hebt op dit moment geen lopend lidmaatschap.</p>
        @endif

        {{-- Vormkeuze: nieuwe aanvraag of wijziging --}}
        <div class="pt-4 border-t border-gray-100 space-y-3">
            <p class="text-sm text-gray-700">
                @if ($currentMembership)
                    Wil je van lidmaatschapsvorm wisselen? Kies hieronder en dien de wijziging ter goedkeuring in.
                @else
                    Vraag hieronder een lidmaatschap aan. De ledenadministratie beoordeelt je aanvraag.
                @endif
            </p>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
                <div class="flex-1">
                    <x-input-label for="membership-type" value="Lidmaatschapsvorm" />
                    <select id="membership-type" wire:model="membership_type_key"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                        <option value="">— Kies een vorm —</option>
                        @foreach ($membershipTypes as $type)
                            <option value="{{ $type->key }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" wire:click="submitMembershipTypeChange"
                    class="text-sm px-3 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                    {{ $currentMembership ? 'Wijziging indienen' : 'Aanvragen' }}
                </button>
            </div>
        </div>
    </section>

    {{-- ICE-contacten --------------------------------------------------- --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex items-baseline justify-between">
            <div>
                <h2 class="font-display text-xl text-gray-900">ICE-contacten</h2>
                <p class="text-sm text-gray-500">Contactpersonen "In Case of Emergency" die de vereniging tijdens activiteiten kan bellen.</p>
            </div>
            <button type="button" wire:click="openIceForm"
                class="text-sm px-3 py-1.5 rounded bg-rzvg-500 text-white hover:bg-rzvg-600">
                + Contact toevoegen
            </button>
        </div>

        @if ($iceContacts->isEmpty())
            <p class="text-sm text-gray-500 italic">Nog geen ICE-contacten geregistreerd.</p>
        @else
            <ul class="divide-y divide-gray-100 border border-gray-100 rounded">
                @foreach ($iceContacts as $contact)
                    <li wire:key="ice-{{ $contact->id }}" class="flex items-start justify-between px-4 py-3">
                        <div class="text-sm">
                            <div class="font-medium text-gray-900">{{ $contact->name }} <span class="text-gray-500">({{ $contact->relation }})</span></div>
                            <div class="text-gray-600">{{ $contact->phone }} @if ($contact->email) &middot; {{ $contact->email }} @endif</div>
                            @if ($contact->notes)
                                <div class="text-gray-500 text-xs mt-1">{{ $contact->notes }}</div>
                            @endif
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button type="button" wire:click="openIceForm({{ $contact->id }})"
                                class="text-xs px-2 py-1 rounded border border-gray-300 hover:bg-gray-50">Bewerken</button>
                            <button type="button"
                                wire:click="deleteIceContact({{ $contact->id }})"
                                wire:confirm="Verwijder dit ICE-contact?"
                                class="text-xs px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50">Verwijderen</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($iceFormOpen)
            <div class="border border-gray-200 rounded p-4 space-y-3 bg-gray-50" wire:key="ice-form">
                <h3 class="font-medium text-gray-900">
                    {{ $editingIceContactId ? 'ICE-contact bewerken' : 'Nieuw ICE-contact' }}
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <x-input-label for="ice-name" value="Naam" />
                        <x-text-input id="ice-name" wire:model="iceForm.name" class="mt-1 w-full" />
                        @error('iceForm.name') <x-input-error :messages="[$message]" class="mt-1" /> @enderror
                    </div>
                    <div>
                        <x-input-label for="ice-relation" value="Relatie" />
                        <x-text-input id="ice-relation" wire:model="iceForm.relation" class="mt-1 w-full" placeholder="bv. partner, ouder" />
                        @error('iceForm.relation') <x-input-error :messages="[$message]" class="mt-1" /> @enderror
                    </div>
                    <div>
                        <x-input-label for="ice-phone" value="Telefoon" />
                        <x-text-input id="ice-phone" wire:model="iceForm.phone" class="mt-1 w-full" />
                        @error('iceForm.phone') <x-input-error :messages="[$message]" class="mt-1" /> @enderror
                    </div>
                    <div>
                        <x-input-label for="ice-email" value="E-mail (optioneel)" />
                        <x-text-input id="ice-email" type="email" wire:model="iceForm.email" class="mt-1 w-full" />
                        @error('iceForm.email') <x-input-error :messages="[$message]" class="mt-1" /> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="ice-notes" value="Opmerkingen (optioneel)" />
                        <textarea id="ice-notes" wire:model="iceForm.notes"
                            class="mt-1 w-full border-gray-300 rounded-md shadow-sm" rows="2"></textarea>
                        @error('iceForm.notes') <x-input-error :messages="[$message]" class="mt-1" /> @enderror
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="saveIceContact"
                        class="text-sm px-3 py-1.5 rounded bg-rzvg-500 text-white hover:bg-rzvg-600">
                        Opslaan
                    </button>
                    <button type="button" wire:click="closeIceForm"
                        class="text-sm px-3 py-1.5 rounded border border-gray-300">
                        Annuleren
                    </button>
                </div>
            </div>
        @endif
    </section>
</div>
