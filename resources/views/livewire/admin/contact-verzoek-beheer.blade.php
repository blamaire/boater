<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Verzoeken die bezoekers hebben ingediend via het publieke contactformulier — de notificatiemail naar de
        verantwoordelijke linkt rechtstreeks naar het detailscherm van een verzoek.
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
            <span class="text-gray-600">Onderwerp</span>
            <select wire:model.live="filterTopicId" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                @foreach ($topics as $topic)
                    <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Onderwerp</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Voorkeur</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Bekijken</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($items as $item)
                    <tr wire:key="contact-request-{{ $item->id }}">
                        <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ $item->created_at?->format('d-m-Y H:i') }}</td>
                        <td class="px-4 py-2 text-gray-900">{{ $item->name }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $item->topic?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $item->preferred_contact_method->label() }}</td>
                        <td class="px-4 py-2">
                            <select wire:change="updateStatus({{ $item->id }}, $event.target.value)" class="border-gray-300 rounded shadow-sm text-xs">
                                @foreach ($statuses as $st)
                                    <option value="{{ $st->value }}" @selected($item->status === $st)>{{ $st->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                        <x-action-cell href="{{ route('admin.contact-requests.show', $item) }}" icon="eye" title="Bekijken" />
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Geen contactverzoeken met de huidige filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-1">
        {{ $items->links() }}
    </div>
</div>
