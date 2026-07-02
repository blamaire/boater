<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-gray-900">
            {{ trim(($person->first_name ?? '').' '.($person->last_name_prefix ? $person->last_name_prefix.' ' : '').($person->last_name ?? '')) }}
        </h1>
        <p class="text-sm text-gray-500">Ledenadministratie — detail en beheer</p>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <a href="{{ route('admin.leden.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">&larr; Terug naar overzicht</a>

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        @livewire('admin.leden-beheer', ['personId' => $person->id])
    </div>
</x-app-layout>
