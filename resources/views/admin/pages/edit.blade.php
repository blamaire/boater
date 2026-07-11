<x-app-layout>
    <x-slot name="header">Pagina-instellingen — {{ $page->title }}</x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <div class="flex justify-between text-sm">
            <a href="{{ route('admin.pages.index') }}" class="text-gray-600 hover:text-gray-800">← Alle pagina's</a>
            <a href="{{ route('admin.pages.editor', $page) }}" class="text-rzvg-600 hover:text-rzvg-800">Naar bewerker →</a>
        </div>
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            @include('admin.pages._form', [
                'page' => $page,
                'templates' => $templates,
                'parents' => $parents,
                'visibilities' => $visibilities,
                'types' => $types,
                'action' => route('admin.pages.update', $page),
                'method' => 'PATCH',
            ])
        </div>
    </div>
</x-app-layout>
