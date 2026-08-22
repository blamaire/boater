<x-app-layout>
    <x-slot name="header">Inhoudswijziging — {{ $page->title }}</x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <div>
            <a href="{{ route('portal.wijzigingsvoorstellen') }}" class="text-sm text-gray-600 hover:text-gray-800">&larr; Wijzigingsvoorstellen</a>
        </div>

        <section class="bg-white border border-gray-200 rounded-lg p-4 text-sm text-gray-700">
            <p>Toont per blok hoe het voorstel (v{{ $b->version_no }}) zich verhoudt tot de huidige inhoud (v{{ $a->version_no }}).</p>
        </section>

        @include('cms.blocks.diff', ['report' => $report, 'a' => $a, 'b' => $b])
    </div>
</x-app-layout>
