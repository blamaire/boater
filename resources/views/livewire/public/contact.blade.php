<div class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    @if ($submitted)
        <div class="rounded-lg border border-green-200 bg-green-50 p-6 text-green-900">
            <h1 class="font-display text-2xl mb-2">Bedankt!</h1>
            <p>We hebben je verzoek ontvangen en nemen zo snel mogelijk contact met je op.</p>
        </div>
    @else
        <div class="mb-6">
            <h1 class="font-display text-3xl text-rzvg-600">Contact</h1>
            <p class="text-gray-600 mt-2">Liever dat wij contact met jou opnemen? Vul onderstaand formulier in, dan bellen of mailen we je terug.</p>
        </div>

        @error('form') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror

        <form wire:submit="submit" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6">
            {{-- Honeypot: off-screen via inline style (geen Tailwind-arbitrary-value —
                 die wordt pas zichtbaar ná een Tailwind-scan van deze nieuwe class,
                 en tot die tijd staat een spambescherming er zomaar zichtbaar bij),
                 geen display:none (sommige bots negeren dat specifiek). --}}
            <div style="position:absolute; left:-9999px; top:-9999px;" aria-hidden="true">
                <label>
                    Website
                    <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </label>
            </div>

            <div>
                <span class="block text-sm font-medium text-gray-700 mb-1">Onderwerp</span>
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Onderwerp">
                    @foreach ($topics as $topic)
                        <button type="button" wire:click="$set('contact_topic_id', {{ $topic->id }})"
                            role="radio" aria-checked="{{ $contact_topic_id === $topic->id ? 'true' : 'false' }}"
                            @class([
                                'px-4 py-2 rounded-full text-sm font-medium border transition',
                                'bg-rzvg-600 text-white border-rzvg-600' => $contact_topic_id === $topic->id,
                                'bg-white text-gray-700 border-gray-300 hover:bg-rzvg-50' => $contact_topic_id !== $topic->id,
                            ])>
                            {{ $topic->name }}
                        </button>
                    @endforeach
                </div>
                @error('contact_topic_id') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <label class="block">
                <span class="block text-sm font-medium text-gray-700">Naam</span>
                <input type="text" wire:model="name" class="mt-1 block w-full rounded border-gray-300">
                @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>

            <fieldset class="block">
                <legend class="block text-sm font-medium text-gray-700">Hoe nemen we contact op?</legend>
                <div class="mt-1 flex flex-col gap-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="contact_by_phone" class="rounded border-gray-300 text-rzvg-600">
                        <span>Bel me terug</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="contact_by_email" class="rounded border-gray-300 text-rzvg-600">
                        <span>Mail me terug</span>
                    </label>
                </div>
            </fieldset>

            @if ($contact_by_phone || $contact_by_email)
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @if ($contact_by_phone)
                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700">Telefoon</span>
                            <input type="tel" wire:model="phone" class="mt-1 block w-full rounded border-gray-300">
                            @error('phone') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @endif
                    @if ($contact_by_email)
                        <label class="block">
                            <span class="block text-sm font-medium text-gray-700">E-mail</span>
                            <input type="email" wire:model="email" class="mt-1 block w-full rounded border-gray-300">
                            @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                        </label>
                    @endif
                </div>
            @endif

            <label class="block">
                <span class="block text-sm font-medium text-gray-700">Bericht</span>
                <textarea wire:model="message" rows="5" class="mt-1 block w-full rounded border-gray-300"></textarea>
                @error('message') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>

            @if (config('services.turnstile.site_key'))
                <div wire:ignore
                    class="cf-turnstile"
                    data-sitekey="{{ config('services.turnstile.site_key') }}"
                    data-callback="rzvgTurnstileCallback"></div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                <script>
                    function rzvgTurnstileCallback(token) {
                        Livewire.find('{{ $this->getId() }}').set('turnstileToken', token);
                    }
                </script>
            @endif

            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-rzvg-600 text-white px-8 py-3 text-base font-semibold shadow-lg ring-1 ring-rzvg-700 hover:bg-rzvg-700 focus:outline-none focus:ring-4 focus:ring-rzvg-300">
                    Versturen
                    <span aria-hidden="true">→</span>
                </button>
            </div>
        </form>
    @endif
</div>
