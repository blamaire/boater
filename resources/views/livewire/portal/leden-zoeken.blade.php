<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-gray-900">Leden zoeken</h1>
        <p class="text-sm text-gray-500">Andere leden opzoeken in de besloten gids.</p>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <label for="q" class="block text-sm font-medium text-gray-700">Zoeken op naam</label>
            <input type="search" id="q" wire:model.live.debounce.300ms="zoekterm"
                   placeholder="Bijv. Jansen"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rzvg-500 focus:ring-rzvg-500 sm:text-sm" />
        </div>

        <div class="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100">
            @forelse ($resultaten as $lid)
                @php($visible = $visibleFieldsByPerson[$lid->id] ?? ['first_name', 'last_name_prefix', 'last_name'])
                <a href="{{ route('portal.leden.show', $lid) }}"
                   class="block px-4 py-3 hover:bg-gray-50 transition">
                    <div class="text-sm font-medium text-gray-900">
                        {{ trim(($lid->first_name ?? '').' '.($lid->last_name_prefix ? $lid->last_name_prefix.' ' : '').($lid->last_name ?? '')) }}
                    </div>
                    <div class="text-xs text-gray-500 space-x-3">
                        @if (in_array('email', $visible, true) && $lid->email)
                            <span>{{ $lid->email }}</span>
                        @endif
                        @if (in_array('phone', $visible, true) && $lid->phone)
                            <span>{{ $lid->phone }}</span>
                        @endif
                        @if (in_array('membership_type', $visible, true))
                            @foreach ($lid->memberships as $lidm)
                                <span>{{ $lidm->type?->name }}</span>
                            @endforeach
                        @endif
                    </div>
                </a>
            @empty
                <div class="px-4 py-8 text-center text-gray-500 text-sm">Geen leden gevonden.</div>
            @endforelse
        </div>

        <div>
            {{ $resultaten->links() }}
        </div>
    </div>
</div>
