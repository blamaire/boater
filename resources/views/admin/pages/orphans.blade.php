<x-app-layout>
    <x-slot name="header">Weespagina's</x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <div>
            <a href="{{ route('admin.pages.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">&larr; Terug naar pagina's</a>
        </div>

        <p class="text-sm text-gray-600">
            Content-pagina's die niet bereikbaar zijn via een zichtbaar menu-item, en ook niet via een keten van
            links vanuit een andere gepubliceerde pagina (tekst-, knop-, kaart-, hero- of feature-sectieblok).
        </p>

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pad</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="w-8 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pages as $page)
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $page->title }}</td>
                            <td class="px-4 py-2 text-sm text-gray-500 font-mono">{{ $page->publicUrl() }}</td>
                            <td class="px-4 py-2 text-sm">
                                @if ($page->publishedVersion)
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 border border-green-200">gepubliceerd (v{{ $page->publishedVersion->version_no }})</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-yellow-50 px-2 py-0.5 text-xs text-yellow-700 border border-yellow-200">concept</span>
                                @endif
                            </td>
                            <x-action-cell
                                href="{{ route('admin.pages.edit', $page) }}"
                                icon="pencil" title="Instellingen" />
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500 text-sm">
                                Geen weespagina's gevonden — alle content-pagina's zijn bereikbaar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
