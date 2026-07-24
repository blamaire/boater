<x-app-layout>
    <x-slot name="header">{{ $actionLabel }} — {{ $page->title }}</x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <div>
            <a href="{{ route('admin.pages.editor', $page) }}" class="text-sm text-gray-600 hover:text-gray-800">&larr; Terug naar de bewerker</a>
        </div>

        @if ($rebaseNotice)
            <div class="rounded-md bg-blue-50 border border-blue-200 p-3 text-sm text-blue-800">{{ $rebaseNotice }}</div>
        @endif

        <form method="POST" action="{{ route($submitRouteName, [$page, $version]) }}" class="space-y-4">
            @csrf

            <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-3">
                <label for="note" class="block text-sm font-medium text-gray-700">Omschrijving van de wijziging</label>
                <textarea id="note" name="note" rows="4" required
                    class="mt-1 w-full border-gray-300 rounded-md text-sm">{{ old('note') }}</textarea>
                @error('note')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="inline-block px-4 py-2 bg-rzvg-500 text-white text-sm rounded-md hover:bg-rzvg-600">
                    {{ $actionLabel }}
                </button>
            </div>

            @if ($published !== null)
                <section class="bg-white border border-gray-200 rounded-lg p-4 text-sm text-gray-700">
                    <p>Toont per blok hoe deze versie (v{{ $version->version_no }}) zich verhoudt tot de huidige gepubliceerde inhoud (v{{ $published->version_no }}).</p>
                </section>

                @include('cms.blocks.diff', ['report' => $report, 'a' => $published, 'b' => $version])
            @else
                <section class="bg-white border border-gray-200 rounded-lg p-4 text-sm text-gray-700">
                    <p>Deze pagina is nog nooit gepubliceerd — er is dus nog geen eerdere versie om mee te vergelijken.</p>
                </section>
            @endif
        </form>
    </div>
</x-app-layout>
