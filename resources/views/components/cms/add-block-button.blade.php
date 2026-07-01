@props(['bandId', 'column', 'blockTypes'])

<div x-data="{ open: false }" class="relative">
    <button type="button" @click="open = !open" class="w-full text-xs text-gray-500 hover:text-rzvg-600 py-2 border border-dashed border-gray-300 rounded hover:border-rzvg-500">
        + Blok toevoegen
    </button>
    <div x-show="open" @click.outside="open = false" x-transition class="absolute z-20 mt-1 right-0 bg-white border border-gray-200 rounded-md shadow-lg p-1 grid grid-cols-2 gap-1 w-64 max-h-72 overflow-y-auto">
        @foreach ($blockTypes as $type)
            <button type="button" @click="$wire.addBlock({{ $bandId }}, {{ $column }}, '{{ $type->value }}'); open = false" class="text-left text-xs px-2 py-1.5 hover:bg-gray-50 rounded">
                {{ $type->label() }}
            </button>
        @endforeach
    </div>
</div>
