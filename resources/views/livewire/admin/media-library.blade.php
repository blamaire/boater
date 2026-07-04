<div>
    @if ($open)
        <div @class([
            'fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50' => ! $standalone,
        ])
            @if (! $standalone) wire:click.self="close" @endif
            wire:key="media-lib-modal">
            <div @class([
                'bg-white rounded-lg space-y-4 max-h-[90vh] overflow-y-auto',
                'shadow-xl max-w-5xl w-full p-6' => ! $standalone,
                'w-full p-6 border border-gray-200 shadow-sm' => $standalone,
            ])>
                <div class="flex items-baseline justify-between">
                    <h2 class="font-display text-xl">Mediabibliotheek</h2>
                    @if (! $standalone)
                        <button wire:click="close" class="text-gray-400 hover:text-gray-700">✕</button>
                    @endif
                </div>

                @can('media.upload')
                    <section class="border border-dashed border-gray-300 rounded p-3 space-y-3 bg-gray-50">
                        <h3 class="font-medium text-sm text-gray-700">Nieuw bestand uploaden</h3>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <x-input-label for="upload-file" value="Bestand (max 10 MB)" />
                                <input id="upload-file" type="file" wire:model="uploadFile"
                                    class="mt-1 block w-full text-sm text-gray-500 file:me-3 file:py-1.5 file:px-3 file:border file:border-gray-300 file:rounded file:bg-white file:text-sm hover:file:bg-gray-50">
                            </div>
                            <div>
                                <x-input-label for="upload-alt" value="Alt-tekst (afbeeldingen)" />
                                <x-text-input id="upload-alt" wire:model="uploadAlt" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label for="upload-tags" value="Tags (komma-gescheiden)" />
                                <x-text-input id="upload-tags" wire:model="uploadTagsInput" class="mt-1 block w-full" placeholder="bijv. foto, activiteit, 2026" />
                            </div>
                            <div>
                                <x-input-label for="upload-visibility" value="Zichtbaarheid" />
                                <select id="upload-visibility" wire:model="uploadVisibility" class="mt-1 block w-full border-gray-300 rounded-md">
                                    @foreach ($visibilities as $v)
                                        <option value="{{ $v->value }}">{{ ucfirst($v->value) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button wire:click="upload" wire:loading.attr="disabled" class="px-3 py-1.5 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 disabled:opacity-50">Uploaden</button>
                            <span wire:loading wire:target="upload" class="text-xs text-gray-500">Bezig…</span>
                            @if ($uploadError)
                                <span class="text-xs text-red-600">{{ $uploadError }}</span>
                            @endif
                        </div>
                    </section>
                @endcan

                <section class="space-y-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex-1 min-w-[10rem]">
                            <x-text-input wire:model.live.debounce.400ms="search" placeholder="Zoek op naam of alt-tekst" class="w-full" />
                        </div>
                        <select wire:model.live="typeFilter" class="border-gray-300 rounded-md text-sm">
                            <option value="">Alle types</option>
                            @foreach ($types as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
                        @if ($allTags->isNotEmpty())
                            <select wire:model.live="tagFilter" multiple class="border-gray-300 rounded-md text-sm min-w-[10rem]">
                                @foreach ($allTags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 md:grid-cols-4">
                        @forelse ($assets as $asset)
                            <div wire:key="asset-{{ $asset->id }}" class="border border-gray-200 rounded p-2 hover:border-rzvg-500 transition space-y-2 bg-white">
                                <button type="button" wire:click="selectAsset({{ $asset->id }})" class="block w-full">
                                    @if ($asset->type->value === 'afbeelding' && ($asset->thumbnailUrl() || $asset->displayUrl()))
                                        <img src="{{ $asset->thumbnailUrl() ?? $asset->displayUrl() }}" alt="{{ $asset->alt }}" class="w-full h-24 object-cover rounded">
                                    @else
                                        <div class="w-full h-24 flex items-center justify-center bg-gray-100 rounded text-2xl">
                                            {{ $asset->type->value === 'document' ? '📄' : '📁' }}
                                        </div>
                                    @endif
                                </button>
                                <div class="text-xs text-gray-600 truncate" title="{{ $asset->original_name }}">{{ $asset->original_name }}</div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-gray-600">{{ $asset->type->label() }}</span>
                                    @can('media.delete')
                                        <button wire:click="deleteAsset({{ $asset->id }})" wire:confirm="Media verwijderen?" class="text-red-600 hover:text-red-800">✕</button>
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full text-center text-gray-500 text-sm py-8">
                                Nog geen media. Upload iets om te beginnen.
                            </div>
                        @endforelse
                    </div>

                    <div>{{ $assets->links() }}</div>
                </section>
            </div>
        </div>
    @endif
</div>
