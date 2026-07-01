@props(['bandId', 'column', 'blockTypes'])

<div class="rounded-md border border-dashed border-gray-300 bg-white p-2 space-y-1">
    <div class="text-xs text-gray-500 px-1">Blok toevoegen:</div>
    <div class="flex flex-wrap gap-1">
        @foreach ($blockTypes as $type)
            <button type="button" @click="$wire.addBlock({{ $bandId }}, {{ $column }}, '{{ $type->value }}')" class="px-2 py-1 text-xs bg-gray-50 hover:bg-rzvg-50 hover:text-rzvg-700 border border-gray-200 rounded transition">
                {{ $type->label() }}
            </button>
        @endforeach
    </div>
</div>
