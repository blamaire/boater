<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-gray-900">Ledenadministratie</h1>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        @livewire('admin.leden-overzicht')
    </div>
</x-app-layout>
