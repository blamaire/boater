<div>
    {{-- Positionering (fixed bottom-right) zit op de gedeelde knoppenrij in de
         layout, niet hier — zo staat dit knopje naast het contactknopje
         i.p.v. eroverheen (zie components/public-layout.blade.php en
         layouts/app.blade.php). --}}
    <button type="button" x-data="" x-on:click="$dispatch('open-modal', 'feedback-widget')"
        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-rzvg-600 text-white text-sm font-medium shadow-lg hover:bg-rzvg-700">
        <svg width="18" height="18" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8-1.17 0-2.29-.2-3.31-.566L3 21l1.649-4.396C3.61 15.36 3 13.73 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z" />
        </svg>
        Terugkoppeling
    </button>

    <x-modal name="feedback-widget" maxWidth="md">
        <div class="p-6">
            @if ($submitted)
                <div class="space-y-4">
                    <h2 class="font-display text-xl text-rzvg-600">Bedankt!</h2>
                    <p class="text-sm text-gray-700">We hebben je terugkoppeling ontvangen.</p>
                    <div class="flex justify-end">
                        <button type="button" wire:click="close" x-on:click="$dispatch('close-modal', 'feedback-widget')"
                            class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">Sluiten</button>
                    </div>
                </div>
            @else
                <form wire:submit="submit" class="space-y-4">
                    <h2 class="font-display text-xl text-rzvg-600">Terugkoppeling geven</h2>

                    {{-- Eén knop per categorie: radio-knoppen in knopvorm (toetsenbord en
                         schermlezer werken als bij een gewone keuzelijst); de gekozen knop
                         kleurt via `has-checked:` zonder extra serververzoek. --}}
                    <fieldset>
                        <legend class="block text-xs font-semibold text-gray-500 uppercase mb-1">Categorie</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($categories as $option)
                                <label class="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 has-checked:border-rzvg-600 has-checked:bg-rzvg-600 has-checked:text-white has-focus-visible:ring-2 has-focus-visible:ring-rzvg-500 has-focus-visible:ring-offset-1">
                                    <input type="radio" name="feedback-category" value="{{ $option->value }}" wire:model="category" class="sr-only">
                                    {{ $option->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error('category')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div>
                        <label for="feedback-message" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Je bericht</label>
                        <textarea id="feedback-message" wire:model="message" rows="4"
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                            placeholder="Waar loop je tegenaan, of wat wil je laten weten?"></textarea>
                        @error('message')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" x-on:click="$dispatch('close')"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Annuleren</button>
                        <button type="submit" class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">Versturen</button>
                    </div>
                </form>
            @endif
        </div>
    </x-modal>
</div>
