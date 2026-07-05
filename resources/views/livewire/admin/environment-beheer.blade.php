<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8">
    <p class="text-sm text-gray-500">
        Beheer de externe omgevingen (test, acceptatie, productie) waarnaar CMS-pagina's kunnen worden gepusht. De API-token is versleuteld opgeslagen en wordt niet meer getoond na opslaan.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">Bekende omgevingen</h2>

        @if ($environments->isEmpty())
            <p class="text-sm text-gray-500 italic">Nog geen omgevingen ingesteld.</p>
        @else
            <ul class="divide-y divide-gray-100 border border-gray-100 rounded">
                @foreach ($environments as $env)
                    <li wire:key="env-{{ $env->id }}" class="px-4 py-3 flex items-start justify-between gap-3">
                        <div class="text-sm flex-1">
                            <div class="font-medium text-gray-900">{{ $env->name }}</div>
                            <div class="text-gray-500 text-xs">{{ $env->url }}</div>
                            @if (! $env->is_active)
                                <div class="text-xs text-amber-700 mt-0.5">Inactief</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <button type="button" wire:click="edit({{ $env->id }})"
                                class="px-2 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                                Wijzigen
                            </button>
                            <button type="button" wire:click="delete({{ $env->id }})"
                                onclick="return confirm('Deze omgeving verwijderen?')"
                                class="px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50">
                                Verwijderen
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">
            {{ $editingId ? 'Omgeving wijzigen' : 'Omgeving toevoegen' }}
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <x-input-label for="env-name" value="Naam" />
                <x-text-input id="env-name" wire:model="name" class="mt-1 w-full" placeholder="test / acceptatie / productie" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="env-url" value="URL" />
                <x-text-input id="env-url" wire:model="url" class="mt-1 w-full" placeholder="https://rzvg-tst.lamaire.nl" />
                @error('url') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="env-token" value="API-token" />
                <x-text-input id="env-token" type="password" wire:model="apiToken" class="mt-1 w-full"
                    placeholder="{{ $editingId ? 'Leeg laten om de huidige token te bewaren' : '' }}" />
                <p class="text-xs text-gray-500 mt-1">
                    Minimaal 16 tekens. Wordt versleuteld opgeslagen en is daarna niet meer leesbaar.
                </p>
                @error('apiToken') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="isActive"
                        class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                    Actief (uitgeschakeld = niet meer als push-doel gebruiken)
                </label>
            </div>
        </div>

        <div class="flex justify-between">
            @if ($editingId)
                <button type="button" wire:click="resetForm"
                    class="text-sm px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                    Annuleren
                </button>
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
