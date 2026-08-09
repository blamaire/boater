<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <a href="{{ route('admin.billing.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Facturatie</a>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-lg font-medium text-gray-900">Factuur {{ $invoice->number }}</div>
                <div class="text-sm text-gray-500">
                    {{ $invoice->debtor->first_name }} {{ $invoice->debtor->last_name }}
                </div>
                <div class="text-sm text-gray-500 mt-1">
                    Factuurtotaal: <span class="font-medium text-gray-900">&euro; {{ number_format((float) $invoice->total, 2, ',', '.') }}</span>
                    <span class="text-xs text-gray-400">(vastliggend)</span>
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
                    <th class="py-2 px-4">Omschrijving</th>
                    <th class="py-2 px-4">Product</th>
                    <th class="py-2 px-4 text-right">Bedrag</th>
                    <th class="py-2 px-4 text-right">Mutatie</th>
                    <th class="py-2 text-left">Crediteren</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($invoice->charges as $charge)
                    @php($remaining = (float) $charge->remainingCreditable())
                    @php($credited = (float) $charge->creditedAmount())
                    <tr>
                        <td class="py-2 px-4 text-gray-700">{{ $charge->description }}</td>
                        <td class="py-2 px-4 text-gray-500 text-xs">{{ $charge->product->name }}</td>
                        <td class="py-2 px-4 text-right whitespace-nowrap">
                            @if ($credited > 0)
                                <div>
                                    <div class="flex justify-between">
                                        <span class="text-left text-gray-500">Oorspronkelijk:</span>
                                        <span class="text-right text-gray-700">&euro; {{ number_format((float) $charge->amount, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-left text-gray-500">Gecrediteerd:</span>
                                        <span class="text-right text-red-600">-&euro; {{ number_format($credited, 2, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-left text-gray-500">Resterend:</span>
                                        <span class="text-right text-gray-700">&euro; {{ number_format($remaining, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-400">
                                    via
                                    @foreach ($charge->credits as $credit)
                                        <a href="{{ route('admin.invoices.show', $credit->invoice) }}" class="text-rzvg-600 hover:underline">{{ $credit->invoice->number }}</a>{{ ! $loop->last ? ',' : '' }}
                                    @endforeach
                                </div>
                            @else
                                <div class="text-gray-700">&euro; {{ number_format((float) $charge->amount, 2, ',', '.') }}</div>
                            @endif
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 mt-1">
                                {{ $charge->status->label() }}
                            </span>
                        </td>
                        <td class="py-2 px-4 text-right whitespace-nowrap">
                            @if ($credited > 0)
                                <span class="text-red-600">-&euro; {{ number_format($credited, 2, ',', '.') }}</span>
                            @else
                                <span class="text-gray-300">&mdash;</span>
                            @endif
                        </td>
                        <td class="py-2">
                            @if ($remaining > 0)
                                <div class="flex flex-wrap items-start gap-1.5">
                                    <div>
                                        <input type="number" step="0.01" min="0.01" max="{{ $remaining }}"
                                            wire:model="creditAmount.{{ $charge->id }}"
                                            class="w-24 border-gray-300 rounded shadow-sm text-xs" />
                                        @error("creditAmount.{$charge->id}") <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                    </div>
                                    <div>
                                        <input type="text" placeholder="Reden"
                                            wire:model="creditReason.{{ $charge->id }}"
                                            class="w-32 border-gray-300 rounded shadow-sm text-xs" />
                                        @error("creditReason.{$charge->id}") <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                                    </div>
                                    <button type="button" wire:click="creditCharge({{ $charge->id }})"
                                        class="text-xs px-2 py-1.5 rounded border border-gray-300 hover:bg-gray-50">Crediteer</button>
                                </div>
                            @else
                                <span class="text-xs text-gray-400">Volledig gecrediteerd</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            @php($totalCredited = (float) $invoice->charges->sum(fn ($c) => (float) $c->creditedAmount()))
            <tfoot>
                <tr class="border-t-2 border-gray-200">
                    <td colspan="3" class="py-2 px-4 text-right font-medium text-gray-800">Totaal mutaties</td>
                    <td class="py-2 px-4 text-right font-semibold text-red-600 whitespace-nowrap">
                        @if ($totalCredited > 0)
                            -&euro; {{ number_format($totalCredited, 2, ',', '.') }}
                        @endif
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
