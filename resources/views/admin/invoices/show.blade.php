<x-app-layout>
    <x-slot name="header">Factuur {{ $invoice->number }}</x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <a href="{{ route('admin.billing.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Facturatie</a>

        <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-lg font-medium text-gray-900">Factuur {{ $invoice->number }}</div>
                    <div class="text-sm text-gray-500">
                        {{ $invoice->debtor->first_name }} {{ $invoice->debtor->last_name }}
                    </div>
                </div>
                <div class="text-right text-sm text-gray-500">
                    <div>Datum: {{ $invoice->issued_at?->format('d-m-Y') ?? '—' }}</div>
                    <div>Vervalt: {{ $invoice->due_at?->format('d-m-Y') ?? '—' }}</div>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-700 mt-1">
                        {{ $invoice->status->label() }}
                    </span>
                </div>
            </div>

            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 uppercase">
                        <th class="py-2">Omschrijving</th>
                        <th class="py-2">Product</th>
                        <th class="py-2 text-right">Bedrag</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($invoice->charges as $charge)
                        <tr>
                            <td class="py-2 text-gray-700">{{ $charge->description }}</td>
                            <td class="py-2 text-gray-500 text-xs">{{ $charge->product->name }}</td>
                            <td class="py-2 text-right text-gray-700 whitespace-nowrap">&euro; {{ number_format((float) $charge->amount, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200">
                        <td colspan="2" class="py-2 text-right font-medium text-gray-800">Totaal</td>
                        <td class="py-2 text-right font-semibold text-gray-900 whitespace-nowrap">&euro; {{ number_format((float) $invoice->total, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
