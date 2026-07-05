<x-app-layout>
    <x-slot name="header">Conflict oplossen — {{ $page->title }}</x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if (session('warning'))
            <div class="mb-4 rounded-md bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-800">{{ session('warning') }}</div>
        @endif

        <livewire:admin.page-conflict-resolver :mine-id="$mine->id" :theirs-id="$theirs->id" />
    </div>
</x-app-layout>
