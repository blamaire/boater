<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-gray-900">Nieuwe pagina</h1>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            @include('admin.pages._form', [
                'page' => null,
                'templates' => $templates,
                'parents' => $parents,
                'visibilities' => $visibilities,
                'types' => $types,
                'action' => route('admin.pages.store'),
                'method' => 'POST',
            ])
        </div>
    </div>
</x-app-layout>
