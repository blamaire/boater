<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Terugkoppeling die gebruikers hebben ingediend via het knopje "Terugkoppeling" — inclusief de pagina,
        applicatieversie en (bij een CMS-pagina) paginaversie op het moment van indienen.
    </p>

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
            <span class="text-gray-600">Categorie</span>
            <select wire:model.live="filterCategory" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Persoon</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Categorie</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bericht</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pagina</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Versie</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($items as $item)
                    <tr wire:key="feedback-{{ $item->id }}">
                        <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ $item->created_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-4 py-2 text-gray-900">{{ $item->person?->fullName() ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border bg-gray-50 text-gray-700 border-gray-200">
                                {{ $item->category->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-700 max-w-md">{{ $item->message }}</td>
                        <td class="px-4 py-2 text-gray-700">
                            @if ($item->page)
                                <a href="{{ $item->page->publicUrl() }}" target="_blank" rel="noopener" class="text-rzvg-600 hover:text-rzvg-800">{{ $item->page->title }}</a>
                            @else
                                <a href="{{ $item->url }}" target="_blank" rel="noopener" class="text-gray-400 hover:text-gray-600 truncate block max-w-[12rem]">{{ $item->url }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $item->app_version ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <select wire:change="updateStatus({{ $item->id }}, $event.target.value)" class="border-gray-300 rounded shadow-sm text-xs">
                                @foreach ($statuses as $st)
                                    <option value="{{ $st->value }}" @selected($item->status === $st)>{{ $st->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">Geen terugkoppeling met de huidige filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-1">
        {{ $items->links() }}
    </div>
</div>
