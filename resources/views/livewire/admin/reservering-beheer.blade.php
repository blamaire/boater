<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Alle reserveringen. Beheerders kunnen elke reservering intrekken (bevestigd → geannuleerd).
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-4 text-sm">
        <label class="flex items-center gap-2">
            Categorie:
            <select wire:model.live="filterCategoryId" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2">
            Status:
            <select wire:model.live="filterStatus" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="all">Alle</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" wire:model.live="hideHistory" class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
            Historie verbergen
        </label>
    </div>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Wanneer</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Object</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Voor</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aangevraagd door</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($reservations as $r)
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-700">
                            {{ $r->starts_at->format('d-m-Y H:i') }} – {{ $r->ends_at->format('H:i') }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-900">{{ $r->object->name }}</div>
                            <div class="text-xs text-gray-500">{{ $r->object->category->name }}</div>
                            @if ($r->object->status === \App\Enums\ReservableObjectStatus::OutOfService && $r->status === \App\Enums\ReservationStatus::Confirmed && $r->ends_at >= now())
                                {{-- §22.4 signaal: object staat buiten gebruik terwijl de reservering nog loopt. Niet auto-annuleren; alleen tonen. --}}
                                <div class="mt-1 inline-flex items-center rounded-full bg-yellow-50 border border-yellow-200 px-2 py-0.5 text-xs text-yellow-800">
                                    Object staat buiten gebruik — opvolgen
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                            {{ $r->person->first_name }} {{ $r->person->last_name }}
                        </td>
                        <td class="px-4 py-2 text-gray-700 text-xs">
                            @if ($r->requestedBy && $r->requested_by_person_id !== $r->person_id)
                                {{ $r->requestedBy->first_name }} {{ $r->requestedBy->last_name }}
                            @else
                                <span class="text-gray-400">zelf</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if ($r->status === \App\Enums\ReservationStatus::Confirmed)
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 border border-green-200">Bevestigd</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Geannuleerd</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            @if ($r->status === \App\Enums\ReservationStatus::Confirmed)
                                <button type="button" wire:click="cancel({{ $r->id }})"
                                    onclick="return confirm('Reservering intrekken?');"
                                    class="text-red-600 hover:text-red-800 text-xs">Intrekken</button>
                            @else
                                <span class="text-xs text-gray-400 italic">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Geen reserveringen met de huidige filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
