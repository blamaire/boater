<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8">
    <p class="text-sm text-gray-500">
        Beheer het contactblok in de footer, sociale-media-links en verwijzingen naar disclaimer- en privacypagina's.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">Contactblok</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <x-input-label for="contact-name" value="Verenigingsnaam" />
                <x-text-input id="contact-name" wire:model="contact_name" class="mt-1 w-full" placeholder="Roei- en Zeilvereniging Gouda" />
                @error('contact_name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="contact-email" value="E-mail" />
                <x-text-input id="contact-email" type="email" wire:model="contact_email" class="mt-1 w-full" placeholder="info@rzvg.nl" />
                @error('contact_email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="contact-phone" value="Telefoon" />
                <x-text-input id="contact-phone" wire:model="contact_phone" class="mt-1 w-full" placeholder="0182 - 12 34 56" />
                @error('contact_phone') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="contact-address" value="Adres (meerdere regels)" />
                <textarea id="contact-address" wire:model="contact_address" rows="3"
                    class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600"></textarea>
                @error('contact_address') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">Sociale media</h2>
        <p class="text-sm text-gray-500">Laat een veld leeg om het icoon niet te tonen.</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <x-input-label for="facebook" value="Facebook-URL" />
                <x-text-input id="facebook" type="url" wire:model="facebook_url" class="mt-1 w-full" placeholder="https://facebook.com/…" />
                @error('facebook_url') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="instagram" value="Instagram-URL" />
                <x-text-input id="instagram" type="url" wire:model="instagram_url" class="mt-1 w-full" placeholder="https://instagram.com/…" />
                @error('instagram_url') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="youtube" value="YouTube-URL" />
                <x-text-input id="youtube" type="url" wire:model="youtube_url" class="mt-1 w-full" placeholder="https://youtube.com/…" />
                @error('youtube_url') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">Juridische pagina's</h2>
        <p class="text-sm text-gray-500">Kies bestaande CMS-pagina's voor de "Privacy"- en "Voorwaarden/disclaimer"-links in de footer.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <x-input-label for="privacy-page" value="Privacypagina" />
                <select id="privacy-page" wire:model="privacy_page_id"
                    class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                    <option value="">— Geen —</option>
                    @foreach ($pages as $page)
                        <option value="{{ $page->id }}">{{ $page->title }}</option>
                    @endforeach
                </select>
                @error('privacy_page_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-input-label for="terms-page" value="Voorwaarden/disclaimer" />
                <select id="terms-page" wire:model="terms_page_id"
                    class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                    <option value="">— Geen —</option>
                    @foreach ($pages as $page)
                        <option value="{{ $page->id }}">{{ $page->title }}</option>
                    @endforeach
                </select>
                @error('terms_page_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <div class="flex justify-end">
        <button type="button" wire:click="save"
            class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
            Opslaan
        </button>
    </div>
</div>
