<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <a href="{{ route('admin.wordpress-import.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">
        &larr; Terug naar overzicht
    </a>

    <p class="text-sm text-gray-500">
        Status van alle bijlagen uit de WordPress-import, ongeacht bij welk item ze horen. Beslissen over een
        bijlage kan alleen op de detailpagina van het bijbehorende item — daar zie je de content waarin ze
        (mogelijk) gebruikt wordt.
    </p>

    <div class="flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">Status</span>
            <select wire:model.live="filterStatus" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                <option value="overgenomen">Overgenomen</option>
                <option value="mislukt">Mislukt</option>
                <option value="niet_overgenomen">Niet overgenomen</option>
                <option value="nieuw">Nog geen besluit</option>
            </select>
        </label>
    </div>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col>
                    <col class="w-48">
                    <col class="w-40">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bijlage</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bovenliggend item</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Origineel</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($mediaItems as $mediaItem)
                        <tr wire:key="wordpress-import-media-overzicht-{{ $mediaItem->id }}">
                            <td class="px-4 py-2 font-medium text-gray-900 truncate">{{ $mediaItem->title }}</td>
                            <td class="px-4 py-2 text-gray-700 truncate">
                                @if ($mediaItem->importItem)
                                    <a href="{{ route('admin.wordpress-import.show', ['item' => $mediaItem->importItem->id]) }}"
                                        class="text-rzvg-600 hover:text-rzvg-800">{{ $mediaItem->importItem->title }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($mediaItem->media_asset_id !== null)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border bg-green-50 text-green-800 border-green-200">Overgenomen</span>
                                @elseif ($mediaItem->download_error !== null)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border bg-red-50 text-red-800 border-red-200" title="{{ $mediaItem->download_error }}">Mislukt</span>
                                @elseif ($mediaItem->selected === false)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border bg-gray-100 text-gray-600 border-gray-200">Niet overgenomen</span>
                                @else
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border bg-yellow-50 text-yellow-800 border-yellow-200">Nog geen besluit</span>
                                @endif
                            </td>
                            <td class="w-8 py-2 text-center">
                                <a href="{{ $mediaItem->url }}" target="_blank" rel="noopener" title="Origineel bekijken" class="text-gray-600 hover:text-gray-900">
                                    <x-action-icon name="eye" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500">Geen bijlagen met de huidige filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($mediaItems->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $mediaItems->links() }}
            </div>
        @endif
    </section>
</div>
