<div>
    <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="sm:col-span-2">
                <label for="q" class="block text-sm font-medium text-gray-700">Zoeken (naam, e-mail)</label>
                <input type="search" id="q" wire:model.live.debounce.300ms="zoekterm"
                       placeholder="Bijv. Jansen of jan@..."
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
            </div>
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Lidmaatschapsvorm</label>
                <select id="type" wire:model.live="membershipTypeFilter"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm">
                    <option value="">— Alle vormen —</option>
                    @foreach ($membershipTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                <select id="status" wire:model.live="statusFilter"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm">
                    <option value="">— Alle statussen —</option>
                    @foreach ($statussen as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="mt-4 bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">E-mail</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Lidmaatschap(pen)</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($personen as $persoon)
                    <tr>
                        <td class="px-4 py-2 text-sm font-medium text-gray-900">
                            {{ trim(($persoon->first_name ?? '').' '.($persoon->last_name_prefix ? $persoon->last_name_prefix.' ' : '').($persoon->last_name ?? '')) }}
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $persoon->email ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">
                            @forelse ($persoon->memberships as $lidm)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700 mr-1">
                                    {{ $lidm->type?->name ?? '—' }} · {{ $lidm->status->label() }}
                                </span>
                            @empty
                                <span class="text-gray-400">Geen lidmaatschap</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-2 text-sm text-right">
                            <a href="{{ route('admin.leden.show', $persoon) }}" class="text-rzvg-600 hover:text-rzvg-800">Bekijken</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-500 text-sm">Geen leden gevonden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $personen->links() }}
    </div>
</div>
