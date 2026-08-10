<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <a href="{{ route('admin.dagboeken.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Dagboeken</a>

    <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        <div>
            <div class="text-lg font-medium text-gray-900">{{ $dagboek->number }} · {{ $dagboek->name }}</div>
            <div class="text-sm text-gray-500">{{ $dagboek->type->label() }}</div>
        </div>

        @forelse ($entries as $entry)
            <div class="border border-gray-100 rounded-md overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-gray-50 border-b border-gray-100 text-sm">
                    <div>
                        <span class="font-medium text-gray-800">{{ $entry->description }}</span>
                        @if ($entry->reference)
                            <span class="text-xs text-gray-400">({{ $entry->reference }})</span>
                        @endif
                    </div>
                    <span class="text-gray-500">{{ $entry->date->format('d-m-Y') }}</span>
                </div>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-4 py-1.5">Rekening</th>
                            <th class="px-4 py-1.5 text-right">Debet</th>
                            <th class="px-4 py-1.5 text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($entry->lines as $line)
                            <tr>
                                <td class="px-4 py-1.5 text-gray-700">{{ $line->account->code }} · {{ $line->account->name }}</td>
                                <td class="px-4 py-1.5 text-right text-gray-700 whitespace-nowrap">
                                    {{ (float) $line->debit > 0 ? '€ '.number_format((float) $line->debit, 2, ',', '.') : '' }}
                                </td>
                                <td class="px-4 py-1.5 text-right text-gray-700 whitespace-nowrap">
                                    {{ (float) $line->credit > 0 ? '€ '.number_format((float) $line->credit, 2, ',', '.') : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="text-sm text-gray-500">Nog geen journaalposten in dit dagboek.</p>
        @endforelse
    </div>
</div>
