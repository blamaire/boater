<x-app-layout>
    <x-slot name="header">{{ $page->title }}</x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-baseline justify-between">
            <p class="text-sm text-gray-500">Concept · v{{ $version->version_no }}</p>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('admin.pages.history', $page) }}" class="text-gray-600 hover:text-gray-800">Historie</a>
                <a href="{{ route('admin.pages.edit', $page) }}" class="text-gray-600 hover:text-gray-800">Instellingen</a>
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
