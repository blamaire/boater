<div class="space-y-6">
    <div class="flex items-center justify-between bg-white border border-gray-200 rounded-lg p-4">
        <div class="flex items-center gap-3">
            <span @class([
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                'bg-yellow-50 text-yellow-700 border border-yellow-200' => $version->status->value === 'concept',
                'bg-blue-50 text-blue-700 border border-blue-200' => $version->status->value === 'in_review',
                'bg-green-50 text-green-700 border border-green-200' => $version->status->value === 'gepubliceerd',
                'bg-gray-100 text-gray-600' => $version->status->value === 'gearchiveerd',
            ])>
                {{ ucfirst(str_replace('_', ' ', $version->status->value)) }} · v{{ $version->version_no }}
            </span>
        </div>
        <div class="flex items-center gap-2">
            @if ($version->status->isEditable())
                <form method="POST" action="{{ route('admin.pages.versions.submit', [$version->page, $version]) }}">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 bg-rzvg-500 text-white text-sm rounded-md hover:bg-rzvg-600">Indienen ter publicatie</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.pages.versions.store', $version->page) }}">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 bg-white border border-gray-300 text-sm rounded-md hover:bg-gray-50">Nieuwe conceptversie</button>
                </form>
            @endif
        </div>
    </div>

    @if ($version->status->isEditable())
        <div class="flex justify-center">
            <x-cms.add-band-button :position="0" />
        </div>
    @endif

    @forelse ($version->bands as $band)
        <div wire:key="band-{{ $band->id }}" class="bg-white border border-gray-200 rounded-lg p-4 space-y-3">
            @if ($version->status->isEditable())
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">Band {{ $loop->iteration }}</span>
                        <select wire:change="setBandLayout({{ $band->id }}, $event.target.value)" class="text-xs border-gray-300 rounded">
                            <option value="1" @selected($band->layout->value === 1)>1 kolom</option>
                            <option value="2" @selected($band->layout->value === 2)>2 kolommen</option>
                            <option value="3" @selected($band->layout->value === 3)>3 kolommen</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="moveBand({{ $band->id }}, 'up')" class="text-gray-400 hover:text-gray-700" aria-label="Omhoog">↑</button>
                        <button wire:click="moveBand({{ $band->id }}, 'down')" class="text-gray-400 hover:text-gray-700" aria-label="Omlaag">↓</button>
                        <button wire:click="removeBand({{ $band->id }})" wire:confirm="Band verwijderen?" class="text-red-600 hover:text-red-800">Verwijderen</button>
                    </div>
                </div>
            @endif

            <div @class([
                'grid gap-3',
                'grid-cols-1' => $band->layout->value === 1,
                'md:grid-cols-2' => $band->layout->value === 2,
                'md:grid-cols-3' => $band->layout->value === 3,
            ])>
                @for ($col = 0; $col < $band->layout->columnCount(); $col++)
                    <div class="space-y-2 min-h-[80px] border border-dashed border-gray-200 rounded p-2" wire:key="band-{{ $band->id }}-col-{{ $col }}">
                        @foreach ($band->blocks->where('column_index', $col)->sortBy('sort_order') as $block)
                            <div wire:key="block-{{ $block->id }}" class="bg-gray-50 border border-gray-200 rounded p-3 space-y-2">
                                <div class="flex items-center justify-between text-xs text-gray-500">
                                    <span class="font-medium">{{ $block->type->label() }}</span>
                                    @if ($version->status->isEditable())
                                        <div class="flex items-center gap-2">
                                            <button wire:click="moveBlock({{ $block->id }}, 'up')" class="hover:text-gray-700" aria-label="Omhoog">↑</button>
                                            <button wire:click="moveBlock({{ $block->id }}, 'down')" class="hover:text-gray-700" aria-label="Omlaag">↓</button>
                                            <button wire:click="startEditBlock({{ $block->id }})" class="text-rzvg-600 hover:text-rzvg-800">Bewerken</button>
                                            <button wire:click="removeBlock({{ $block->id }})" wire:confirm="Blok verwijderen?" class="text-red-600 hover:text-red-800">Verwijderen</button>
                                        </div>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-700">
                                    @include('cms.blocks.preview', ['block' => $block])
                                </div>
                            </div>
                        @endforeach

                        @if ($version->status->isEditable())
                            <x-cms.add-block-button :band-id="$band->id" :column="$col" :block-types="$blockTypes" />
                        @endif
                    </div>
                @endfor
            </div>
        </div>

        @if ($version->status->isEditable())
            <div class="flex justify-center">
                <x-cms.add-band-button :position="$band->sort_order + 1" />
            </div>
        @endif
    @empty
        <div class="text-center text-gray-500 text-sm py-8 bg-white border border-dashed border-gray-300 rounded-lg">
            Nog geen banden. Voeg er een toe om te beginnen.
        </div>
    @endforelse

    @if ($editingBlock !== null)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50" wire:click.self="cancelEditBlock">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-baseline justify-between">
                    <h2 class="font-display text-xl">{{ $editingBlock->type->label() }} bewerken</h2>
                    <button wire:click="cancelEditBlock" class="text-gray-400 hover:text-gray-700">✕</button>
                </div>

                @include('cms.blocks.edit', ['type' => $editingBlock->type])

                <div class="flex items-center gap-3 pt-2 border-t">
                    <button wire:click="saveBlock" class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600">Opslaan</button>
                    <button wire:click="cancelEditBlock" class="text-sm text-gray-600 hover:text-gray-800">Annuleren</button>
                </div>
            </div>
        </div>
    @endif
</div>
