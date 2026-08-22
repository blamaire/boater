<x-app-layout>
    <x-slot name="header">{{ $page->title }}</x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-baseline justify-between">
            <div class="flex items-baseline gap-3 text-sm">
                @can('pages.view')
                    <a href="{{ route('admin.pages.index') }}" class="text-gray-600 hover:text-gray-800">← Alle pagina's</a>
                @else
                    <a href="{{ $page->publicUrl() }}" class="text-gray-600 hover:text-gray-800">← Terug naar pagina</a>
                @endcan
                <p class="text-gray-500">Concept · v{{ $version->version_no }}</p>
                @if ($hasUnpublishedChanges)
                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Niet gepubliceerd</span>
                @endif
            </div>
            <div class="flex items-center gap-3 text-sm">
                @can('pages.view')
                    <a href="{{ route('admin.pages.history', $page) }}" class="text-gray-600 hover:text-gray-800">Historie</a>
                @endcan
                @can('pages.update')
                    <a href="{{ route('admin.pages.edit', $page) }}" class="text-gray-600 hover:text-gray-800">Instellingen</a>
                @endcan
            </div>
        </div>
        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if ($page->type->value === 'systeem')
            <div class="mb-4 rounded-md bg-blue-50 border border-blue-200 p-3 text-sm text-blue-800">
                Dit is een systeempagina — bewerken mag, verwijderen niet.
            </div>
        @endif

        <livewire:admin.page-editor :version-id="$version->id" />
        <livewire:admin.media-library />
    </div>
</x-app-layout>
