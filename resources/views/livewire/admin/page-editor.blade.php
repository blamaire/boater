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
            <a href="{{ route('admin.pages.versions.preview', [$version->page, $version]) }}" target="_blank" rel="noopener"
                class="px-3 py-1.5 bg-white border border-gray-300 text-sm rounded-md hover:bg-gray-50 inline-block">
                Voorvertoning
            </a>
            <button type="button" wire:click="toggleJsonPanel"
                class="px-3 py-1.5 bg-white border border-gray-300 text-sm rounded-md hover:bg-gray-50">
                &lt;/&gt; Broncode
            </button>
            <button type="button" wire:click="toggleMetaPanel"
                class="px-3 py-1.5 bg-white border border-gray-300 text-sm rounded-md hover:bg-gray-50">
                SEO &amp; Open Graph
            </button>
            @if ($version->status->isEditable())
                <a href="{{ route('admin.pages.versions.submit.confirm', [$version->page, $version]) }}"
                    class="inline-block px-3 py-1.5 bg-rzvg-500 text-white text-sm rounded-md hover:bg-rzvg-600">Indienen ter publicatie</a>
                @can('pages.publish')
                    <a href="{{ route('admin.pages.versions.publish.confirm', [$version->page, $version]) }}"
                        class="inline-block px-3 py-1.5 bg-white border border-rzvg-500 text-rzvg-700 text-sm rounded-md hover:bg-rzvg-50">Direct publiceren</a>
                @endcan
            @else
                <form method="POST" action="{{ route('admin.pages.versions.store', $version->page) }}">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 bg-white border border-gray-300 text-sm rounded-md hover:bg-gray-50">Nieuwe conceptversie</button>
                </form>
            @endif
        </div>
    </div>

    @if ($showJsonPanel)
        <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg text-gray-900">Broncode van deze versie</h2>
                <button type="button" wire:click="toggleJsonPanel" class="text-sm text-gray-500 hover:text-gray-700">Sluiten</button>
            </div>
            <p class="text-sm text-gray-500">
                De volledige bands- en blokstructuur als JSON. Handig om een pagina naar tekst te exporteren, elders te bewerken, of van een andere pagina te importeren. Toepassen vervangt de complete inhoud van deze conceptversie.
            </p>
            @if ($jsonStatus)
                <div class="rounded-md bg-blue-50 border border-blue-200 text-blue-800 text-sm px-4 py-2">
                    {{ $jsonStatus }}
                </div>
            @endif
            <textarea wire:model="importJsonText" rows="20" spellcheck="false"
                class="w-full font-mono text-xs border border-gray-300 rounded p-3 bg-gray-50"></textarea>
            <div class="flex items-center justify-between">
                <p class="text-xs text-gray-500">
                    Media-referenties (\`media_asset_id\`) blijven zoals in de JSON — bij import verwijzen ze naar assets in de mediabibliotheek van deze omgeving.
                </p>
                <div class="flex items-center gap-2">
                    <a href="data:application/json;charset=utf-8,{{ rawurlencode($this->currentJson()) }}"
                        download="pagina-{{ $version->page->slug }}-v{{ $version->version_no }}.json"
                        class="text-sm px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                        Downloaden
                    </a>
                    @if ($version->status->isEditable())
                        <button type="button" wire:click="applyImportedJson"
                            wire:confirm="De hele conceptversie wordt vervangen door deze JSON. Doorgaan?"
                            class="text-sm px-3 py-1.5 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                            Toepassen op conceptversie
                        </button>
                    @else
                        <span class="text-xs text-gray-500 italic">Alleen conceptversies zijn overschrijfbaar.</span>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($showMetaPanel)
        <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg text-gray-900">SEO &amp; Open Graph</h2>
                <button type="button" wire:click="toggleMetaPanel" class="text-sm text-gray-500 hover:text-gray-700">Sluiten</button>
            </div>
            <p class="text-sm text-gray-500">
                Meta-omschrijving en Open Graph-gegevens voor zoekmachines en social-media-previews van deze pagina.
            </p>
            @if ($metaStatus)
                <div class="rounded-md bg-blue-50 border border-blue-200 text-blue-800 text-sm px-4 py-2">
                    {{ $metaStatus }}
                </div>
            @endif

            <div x-data="{ count: {{ strlen((string) ($editingMeta['meta_description'] ?? '')) }} }">
                <div class="flex items-center justify-between">
                    <x-input-label for="meta-description" value="Meta-omschrijving" />
                    <span class="text-xs text-gray-400" :class="count > 155 ? 'text-amber-600' : ''" x-text="count + ' / ~155 tekens'"></span>
                </div>
                <textarea id="meta-description" wire:model="editingMeta.meta_description" rows="3" @input="count = $event.target.value.length"
                    class="mt-1 w-full border-gray-300 rounded-md text-sm"></textarea>
            </div>

            <div>
                <x-input-label for="og-title" value="OG-titel" />
                <x-text-input id="og-title" wire:model="editingMeta.og_title" class="block mt-1 w-full" />
            </div>

            <div>
                <x-input-label for="og-description" value="OG-omschrijving" />
                <textarea id="og-description" wire:model="editingMeta.og_description" rows="3"
                    class="mt-1 w-full border-gray-300 rounded-md text-sm"></textarea>
            </div>

            <div class="space-y-1">
                <x-input-label value="OG-afbeelding" />
                <p class="text-xs text-gray-500">Valt terug op het RZVG-logo als hier geen afbeelding is gekozen.</p>
                <div class="flex items-center gap-3">
                    <img src="{{ $editingMeta['og_image_url'] ?? asset('img/branding/rzvg-logo.jpg') }}" alt="" class="h-16 w-auto rounded border border-gray-200">
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="$dispatch('open-media-library', { contextId: 'og-image' })"
                            class="px-2 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">Uit bibliotheek</button>
                        @if (! empty($editingMeta['og_image_media_asset_id']))
                            <button type="button" wire:click="clearOgImage"
                                class="px-2 py-1 border border-gray-300 rounded text-sm text-red-600 hover:bg-red-50">Verwijderen</button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="pt-2 border-t">
                @if ($version->status->isEditable())
                    <button type="button" wire:click="saveMeta" class="px-4 py-2 bg-rzvg-500 text-white text-sm rounded-md hover:bg-rzvg-600">Opslaan</button>
                @else
                    <span class="text-xs text-gray-500 italic">Alleen conceptversies zijn bewerkbaar.</span>
                @endif
            </div>
        </div>
    @endif

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
                                <div class="text-sm text-gray-700 overflow-hidden">
                                    @include('cms.blocks.preview', ['block' => $block, 'fullBleed' => false])
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
            <div @class([
                'bg-white rounded-lg shadow-xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto',
                'max-w-4xl' => $editingBlock->type->value === 'tekst',
                'max-w-2xl' => $editingBlock->type->value !== 'tekst',
            ])>
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
