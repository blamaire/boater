<div class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Kies hier welke redactionele e-mail je van RZVG wilt ontvangen. Belangrijke, persoonlijke berichten (zoals
        over je lidmaatschap of een activiteit waarop je bent ingeschreven) sturen we altijd, ongeacht deze
        instellingen.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 divide-y divide-gray-100">
        @foreach ($categories as $key => $label)
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm text-gray-900">{{ $label }}</span>
                <button type="button" wire:click="toggle('{{ $key }}')"
                    @class([
                        'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                        'bg-rzvg-500' => $preferences[$key] ?? false,
                        'bg-gray-200' => ! ($preferences[$key] ?? false),
                    ])
                    role="switch" aria-checked="{{ ($preferences[$key] ?? false) ? 'true' : 'false' }}">
                    <span @class([
                        'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                        'translate-x-6' => $preferences[$key] ?? false,
                        'translate-x-1' => ! ($preferences[$key] ?? false),
                    ])></span>
                </button>
            </div>
        @endforeach
    </section>
</div>
