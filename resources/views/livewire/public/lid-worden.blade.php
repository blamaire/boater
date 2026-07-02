<div class="py-8">
    @if ($submitted)
        <div class="rounded-lg border border-green-200 bg-green-50 p-6 text-green-900">
            <h1 class="font-display text-2xl mb-2">Bedankt voor je aanmelding!</h1>
            <p>Je aanvraag is ontvangen en wordt beoordeeld door de ledenadministratie. Zodra er een besluit is genomen, ontvang je een e-mail op <strong>{{ $email }}</strong>.</p>
        </div>
    @else
        <div class="mb-6">
            <h1 class="font-display text-3xl text-rzvg-600">Lid worden</h1>
            <p class="text-gray-600 mt-2">Vul onderstaande gegevens in. Op basis van je leeftijd en adres zie je welke lidmaatschapsvormen op jou van toepassing zijn.</p>
        </div>

        <form wire:submit="submit" class="space-y-8">
            <fieldset class="space-y-4 rounded-lg border border-gray-200 bg-white p-6">
                <legend class="px-2 font-display text-lg text-rzvg-600">1. Jouw gegevens</legend>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Voornaam</span>
                        <input type="text" wire:model="first_name" class="mt-1 block w-full rounded border-gray-300">
                        @error('first_name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Tussenvoegsel</span>
                        <input type="text" wire:model="last_name_prefix" class="mt-1 block w-full rounded border-gray-300">
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Achternaam</span>
                        <input type="text" wire:model="last_name" class="mt-1 block w-full rounded border-gray-300">
                        @error('last_name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Geboortedatum</span>
                        <input type="date" wire:model.live="date_of_birth" class="mt-1 block w-full rounded border-gray-300">
                        @error('date_of_birth') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">E-mail</span>
                        <input type="email" wire:model="email" class="mt-1 block w-full rounded border-gray-300">
                        @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Telefoon (optioneel)</span>
                        <input type="tel" wire:model="phone" class="mt-1 block w-full rounded border-gray-300">
                    </label>
                </div>
            </fieldset>

            <fieldset class="space-y-4 rounded-lg border border-gray-200 bg-white p-6">
                <legend class="px-2 font-display text-lg text-rzvg-600">2. Adres</legend>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-4 items-end">
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Postcode</span>
                        <input type="text" wire:model="postal_code" placeholder="1234AB" class="mt-1 block w-full rounded border-gray-300">
                        @error('postal_code') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Huisnummer</span>
                        <input type="text" wire:model="house_number" class="mt-1 block w-full rounded border-gray-300">
                        @error('house_number') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-700">Toevoeging</span>
                        <input type="text" wire:model="house_number_addition" class="mt-1 block w-full rounded border-gray-300">
                    </label>
                    <button type="button" wire:click="lookupAddress" class="rounded bg-rzvg-600 text-white px-4 py-2 hover:bg-rzvg-700">
                        Adres opzoeken
                    </button>
                </div>

                @if ($bag_error)
                    <p class="text-sm text-red-600">{{ $bag_error }}</p>
                @endif

                @if ($street && $city)
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700">Straat</span>
                            <input type="text" wire:model="street" readonly class="mt-1 block w-full rounded border-gray-300 bg-gray-50">
                            @error('street') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700">Woonplaats</span>
                            <input type="text" wire:model="city" readonly class="mt-1 block w-full rounded border-gray-300 bg-gray-50">
                            @error('city') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </label>
                    </div>
                @endif
            </fieldset>

            @if ($this->isMinor)
                <fieldset class="space-y-4 rounded-lg border border-gray-200 bg-white p-6">
                    <legend class="px-2 font-display text-lg text-rzvg-600">3. Ouder/verzorger</legend>
                    @auth
                        <p class="text-sm text-gray-700">
                            Je bent ingelogd als <strong>{{ auth()->user()->name }}</strong> en wordt als ouder/verzorger van dit kind gekoppeld.
                        </p>
                    @else
                        <p class="text-sm text-gray-700">
                            Heb je al een account? <a href="{{ route('login') }}" class="text-rzvg-600 hover:underline">Log dan eerst in</a> — dan word je automatisch als ouder/verzorger gekoppeld. Anders vul je hieronder je gegevens in.
                        </p>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <label class="block">
                                <span class="block text-sm font-medium text-gray-700">Voornaam</span>
                                <input type="text" wire:model="guardian_first_name" class="mt-1 block w-full rounded border-gray-300">
                                @error('guardian_first_name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="block">
                                <span class="block text-sm font-medium text-gray-700">Tussenvoegsel</span>
                                <input type="text" wire:model="guardian_last_name_prefix" class="mt-1 block w-full rounded border-gray-300">
                            </label>
                            <label class="block">
                                <span class="block text-sm font-medium text-gray-700">Achternaam</span>
                                <input type="text" wire:model="guardian_last_name" class="mt-1 block w-full rounded border-gray-300">
                                @error('guardian_last_name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                            </label>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="block text-sm font-medium text-gray-700">E-mail</span>
                                <input type="email" wire:model="guardian_email" class="mt-1 block w-full rounded border-gray-300">
                                @error('guardian_email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="block">
                                <span class="block text-sm font-medium text-gray-700">Telefoon (optioneel)</span>
                                <input type="tel" wire:model="guardian_phone" class="mt-1 block w-full rounded border-gray-300">
                            </label>
                        </div>
                    @endauth
                </fieldset>
            @endif

            <fieldset class="space-y-3 rounded-lg border border-gray-200 bg-white p-6">
                <legend class="px-2 font-display text-lg text-rzvg-600">{{ $this->isMinor ? '4' : '3' }}. Lidmaatschapsvorm</legend>
                <p class="text-sm text-gray-600">Selecteer een vorm. Niet-passende vormen kun je alleen kiezen als je onderaan toelicht waarom je vindt dat deze toch van toepassing is.</p>

                <div class="space-y-2">
                    @foreach ($this->eligibility as $e)
                        @php
                            $isChosen = $membership_type_key === $e->type->key;
                        @endphp
                        <label class="flex gap-3 items-start rounded p-3 cursor-pointer transition {{
                            $e->available
                                ? ($isChosen
                                    ? 'border-2 border-rzvg-600 bg-rzvg-50 shadow-sm'
                                    : 'border-2 border-rzvg-500 bg-white hover:bg-rzvg-50')
                                : 'border border-dashed border-gray-300 bg-gray-50 text-gray-500'
                        }}">
                            <input type="radio" wire:model.live="membership_type_key" value="{{ $e->type->key }}" class="mt-1 accent-rzvg-600">
                            <div>
                                <div class="font-medium {{ $e->available ? 'text-gray-900' : 'text-gray-600' }}">
                                    {{ $e->type->name }}
                                    @if ($e->available)
                                        <span class="ml-2 text-xs uppercase tracking-wide text-rzvg-600">van toepassing</span>
                                    @endif
                                </div>
                                <div class="text-sm {{ $e->available ? 'text-gray-700' : 'text-gray-500' }}">{{ $e->type->description }}</div>
                                @if (! $e->available)
                                    <div class="text-sm text-gray-600 mt-1"><strong>Niet standaard van toepassing:</strong> {{ $e->reason }}</div>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('membership_type_key') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($this->chosenEligibility && ! $this->chosenEligibility->available)
                    <label class="block mt-3">
                        <span class="block text-sm font-medium text-gray-700">Uitleg — waarom is deze vorm volgens jou toch van toepassing?</span>
                        <textarea wire:model="override_reason" rows="3" class="mt-1 block w-full rounded border-gray-300"></textarea>
                        @error('override_reason') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                @endif
            </fieldset>

            <fieldset class="space-y-3 rounded-lg border border-gray-200 bg-white p-6">
                <legend class="px-2 font-display text-lg text-rzvg-600">{{ $this->isMinor ? '5' : '4' }}. Akkoord</legend>
                <label class="flex gap-2 items-start">
                    <input type="checkbox" wire:model="agree_statutes" class="mt-1">
                    <span>Ik ga akkoord met de statuten.</span>
                </label>
                @error('agree_statutes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <label class="flex gap-2 items-start">
                    <input type="checkbox" wire:model="agree_house_rules" class="mt-1">
                    <span>Ik ga akkoord met het huishoudelijk reglement.</span>
                </label>
                @error('agree_house_rules') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <label class="flex gap-2 items-start">
                    <input type="checkbox" wire:model="agree_privacy" class="mt-1">
                    <span>Ik ga akkoord met het privacybeleid.</span>
                </label>
                @error('agree_privacy') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </fieldset>

            <div class="flex items-center justify-between gap-4 pt-2">
                <p class="text-sm text-gray-500">Na verzending ontvang je bericht per e-mail zodra de ledenadministratie beslist.</p>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-rzvg-600 text-white px-8 py-3 text-base font-semibold shadow-lg ring-1 ring-rzvg-700 hover:bg-rzvg-700 focus:outline-none focus:ring-4 focus:ring-rzvg-300">
                    Aanvraag versturen
                    <span aria-hidden="true">→</span>
                </button>
            </div>
        </form>
    @endif
</div>
