@props(['position'])

<div x-data="{ open: false }" class="inline-block">
    <button type="button" @click="open = !open" class="px-3 py-1.5 border border-dashed border-gray-300 text-sm text-gray-500 rounded hover:border-rzvg-500 hover:text-rzvg-600 transition">
        + Band toevoegen
    </button>
    <div x-show="open" @click.outside="open = false" x-transition class="absolute z-10 mt-2 bg-white border border-gray-200 rounded-md shadow-lg p-2 space-x-2">
        <button type="button" wire:click="addBand({{ $position }}, 1)" @click="open = false" class="px-2 py-1 text-sm hover:bg-gray-50 rounded">1 kol</button>
        <button type="button" wire:click="addBand({{ $position }}, 2)" @click="open = false" class="px-2 py-1 text-sm hover:bg-gray-50 rounded">2 kol</button>
        <button type="button" wire:click="addBand({{ $position }}, 3)" @click="open = false" class="px-2 py-1 text-sm hover:bg-gray-50 rounded">3 kol</button>
    </div>
</div>
