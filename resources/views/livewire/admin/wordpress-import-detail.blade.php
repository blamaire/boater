<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <a href="{{ route('admin.wordpress-import.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">
        &larr; Terug naar overzicht
    </a>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif
    @if ($errorMessage)
        <div class="rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-2" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="font-medium text-gray-900 text-lg">{{ $item->title }}</h1>
                @if ($position !== null)
                    <p class="text-xs text-gray-400">Item {{ $position }} van {{ $total }}</p>
                @endif
            </div>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border shrink-0
                @class([
                    'bg-yellow-50 text-yellow-800 border-yellow-200' => $item->status === \App\Enums\WordpressImportStatus::New,
                    'bg-green-50 text-green-800 border-green-200' => $item->status === \App\Enums\WordpressImportStatus::Imported,
                    'bg-gray-100 text-gray-600 border-gray-200' => $item->status === \App\Enums\WordpressImportStatus::Archived,
                ])">
                {{ $item->status->label() }}
            </span>
        </div>

        <dl class="grid gap-2 sm:grid-cols-4 text-sm">
            <div>
                <dt class="text-gray-500 text-xs uppercase">WP-ID</dt>
                <dd class="text-gray-900">{{ $item->wordpress_id }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 text-xs uppercase">Type</dt>
                <dd class="text-gray-900">{{ $item->wordpress_type->label() }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 text-xs uppercase">WP-publicatiedatum</dt>
                <dd class="text-gray-900">{{ $item->wordpress_published_at?->format('d-m-Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 text-xs uppercase">Laatst aangepast</dt>
                <dd class="text-gray-900">{{ $item->updated_at?->format('d-m-Y H:i') ?? '—' }}</dd>
            </div>
        </dl>

        @if ($item->excerpt)
            <div>
                <div class="text-xs font-semibold text-gray-500 uppercase">Samenvatting</div>
                <div class="text-sm text-gray-800 whitespace-pre-line">{{ $item->excerpt }}</div>
            </div>
        @endif

        <div>
            <div class="flex items-center justify-between mb-1">
                <div class="text-xs font-semibold text-gray-500 uppercase">Media in dit item</div>
                @if ($item->status === \App\Enums\WordpressImportStatus::New && $item->mediaItems->isNotEmpty())
                    <div class="flex gap-3 text-xs">
                        <button type="button" wire:click="acceptAllMedia" class="text-rzvg-600 hover:text-rzvg-800">Alles overnemen</button>
                        <button type="button" wire:click="rejectAllMedia" class="text-gray-500 hover:text-gray-700">Alles niet overnemen</button>
                    </div>
                @endif
            </div>
            @if ($item->mediaItems->isNotEmpty())
                <ul class="divide-y divide-gray-100 border border-gray-200 rounded-md">
                    @foreach ($item->mediaItems as $mediaItem)
                        <li class="flex items-center gap-3 px-3 py-2 text-sm" wire:key="wordpress-import-media-{{ $mediaItem->id }}">
                            @if (str_starts_with($mediaItem->mime_type ?? '', 'image/'))
                                <a href="{{ $mediaItem->url }}" target="_blank" rel="noopener" class="shrink-0">
                                    <img src="{{ $mediaItem->url }}" loading="lazy" alt="" class="h-10 w-10 object-cover rounded border border-gray-200">
                                </a>
                            @else
                                <a href="{{ $mediaItem->url }}" target="_blank" rel="noopener"
                                    class="shrink-0 flex h-10 w-10 items-center justify-center rounded border border-gray-200 text-gray-400 hover:text-gray-600">
                                    <x-action-icon name="eye" />
                                </a>
                            @endif
                            <div class="min-w-0 flex-1">
                                <a href="{{ $mediaItem->url }}" target="_blank" rel="noopener" class="truncate text-gray-700 hover:text-rzvg-700 block">{{ $mediaItem->title }}</a>
                                <p class="truncate text-xs text-gray-400">{{ $mediaItem->url }}</p>
                                @if ($mediaItem->download_error !== null)
                                    <p class="text-xs text-red-600">{{ $mediaItem->download_error }}</p>
                                @endif
                            </div>
                            @if ($item->status === \App\Enums\WordpressImportStatus::New)
                                <div class="flex gap-1 shrink-0">
                                    <button type="button" wire:click="decideMedia({{ $mediaItem->id }}, true)"
                                        class="px-2 py-1 rounded text-xs @if ($mediaItem->selected === true) bg-green-600 text-white @else border border-gray-300 text-gray-700 hover:bg-gray-50 @endif">Overnemen</button>
                                    <button type="button" wire:click="decideMedia({{ $mediaItem->id }}, false)"
                                        class="px-2 py-1 rounded text-xs @if ($mediaItem->selected === false) bg-gray-600 text-white @else border border-gray-300 text-gray-700 hover:bg-gray-50 @endif">Niet overnemen</button>
                                </div>
                            @elseif ($mediaItem->media_asset_id !== null)
                                <x-action-icon name="check" class="text-green-600 shrink-0" />
                            @elseif ($mediaItem->download_error !== null)
                                <x-action-icon name="xmark" class="text-red-600 shrink-0" />
                            @else
                                <span class="w-4 shrink-0"></span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-400">Geen bijlagen gevonden voor dit item.</p>
            @endif
        </div>

        <div>
            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Voorvertoning</div>
            <div class="prose max-w-none border border-gray-200 rounded-md p-4 max-h-96 overflow-y-auto">
                {!! $previewHtml !!}
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-2 pt-2 border-t border-gray-100">
            @if ($item->status === \App\Enums\WordpressImportStatus::New)
                <button type="button" wire:click="archive(false)" onclick="return confirm('Archiveren (geen pagina)?');"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Archiveren</button>
                <button type="button" wire:click="archive(true)" onclick="return confirm('Archiveren (geen pagina)? Je gaat daarna direct naar het volgende item.');"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Archiveren en volgende</button>
                <button type="button" wire:click="accept(false)" onclick="return confirm('Overnemen als CMS-pagina?');"
                    class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">Accepteren</button>
                <button type="button" wire:click="accept(true)" onclick="return confirm('Overnemen als CMS-pagina? Je gaat daarna direct naar het volgende item.');"
                    class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">Accepteren en volgende</button>
            @elseif ($item->status === \App\Enums\WordpressImportStatus::Imported && $item->page_id !== null)
                <button type="button" wire:click="restoreToNew" onclick="return confirm('Terugzetten naar nieuw?');"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Terugzetten naar nieuw</button>
                <a href="{{ route('admin.pages.editor', $item->page_id) }}"
                    class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 text-sm">Naar pagina</a>
            @else
                <button type="button" wire:click="restoreToNew" onclick="return confirm('Terugzetten naar nieuw?');"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Terugzetten naar nieuw</button>
            @endif
        </div>
    </section>
</div>
