@props(['position'])

<div class="inline-flex items-center gap-1 rounded-md border border-dashed border-gray-300 bg-white p-1 text-sm text-gray-500">
    <span class="px-2 text-xs">Band toevoegen:</span>
    <button type="button" @click="$wire.addBand({{ $position }}, 1)" class="px-2 py-1 rounded hover:bg-rzvg-50 hover:text-rzvg-700 transition">1 kol</button>
    <button type="button" @click="$wire.addBand({{ $position }}, 2)" class="px-2 py-1 rounded hover:bg-rzvg-50 hover:text-rzvg-700 transition">2 kol</button>
    <button type="button" @click="$wire.addBand({{ $position }}, 3)" class="px-2 py-1 rounded hover:bg-rzvg-50 hover:text-rzvg-700 transition">3 kol</button>
</div>
