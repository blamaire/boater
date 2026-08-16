<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Pagina's en berichten uit de oude WordPress-site, geïmporteerd via <code>rzvg:import-wordpress</code>. Open
        een item om te beslissen: <strong>overnemen</strong> maakt er een echte CMS-pagina van (als concept — die
        controleer en publiceer je zelf), of <strong>archiveren</strong> laat het item alleen in deze staging staan.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">Status</span>
            <select wire:model.live="filterStatus" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">Type</span>
            <select wire:model.live="filterType" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                @foreach ($types as $t)
                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @php
        $columns = [
            'title' => 'Titel',
            'wordpress_type' => 'Type',
            'wordpress_published_at' => 'WP-publicatiedatum',
            'updated_at' => 'Laatst aangepast',
            'status' => 'Status',
        ];
    @endphp

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col>
                    <col class="w-28">
                    <col class="w-32">
                    <col class="w-32">
                    <col class="w-32">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        @foreach ($columns as $field => $label)
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                <button type="button" wire:click="sortBy('{{ $field }}')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                    {{ $label }}
                                    @if ($sortField === $field)
                                        <x-action-icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="h-3 w-3" />
                                    @endif
                                </button>
                            </th>
                        @endforeach
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($items as $item)
                        <tr wire:key="wordpress-import-{{ $item->id }}">
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $item->title }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border bg-gray-50 text-gray-700 border-gray-200">
                                    {{ $item->wordpress_type->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                                {{ $item->wordpress_published_at?->format('d-m-Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                                {{ $item->updated_at?->format('d-m-Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border
                                    @class([
                                        'bg-yellow-50 text-yellow-800 border-yellow-200' => $item->status === \App\Enums\WordpressImportStatus::New,
                                        'bg-green-50 text-green-800 border-green-200' => $item->status === \App\Enums\WordpressImportStatus::Imported,
                                        'bg-gray-100 text-gray-600 border-gray-200' => $item->status === \App\Enums\WordpressImportStatus::Archived,
                                    ])">
                                    {{ $item->status->label() }}
                                </span>
                            </td>
                            <x-action-cell
                                href="{{ route('admin.wordpress-import.show', ['item' => $item, 'sort' => $sortField, 'direction' => $sortDirection, 'filterType' => $filterType]) }}"
                                icon="eye" title="Bekijken" />
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">Geen geïmporteerde items met de huidige filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $items->links() }}
            </div>
        @endif
    </section>
</div>
