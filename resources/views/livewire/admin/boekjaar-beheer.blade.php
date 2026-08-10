<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Boekjaar {{ $fiscalYear->year }}: dertien periodes — periode 0 is de beginbalans, 1 t/m 12 zijn de
        kalendermaanden. Een periode die al voorbij is kan afgesloten (vergrendeld) worden; er kunnen dan geen
        journaalposten meer in geboekt worden. Boekjaren/periodes worden automatisch aangemaakt zodra ze nodig zijn.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif
    @if ($errorMessage)
        <div class="rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-2" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col class="w-16">
                    <col>
                    <col class="w-56">
                    <col class="w-40">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nr.</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datumrange</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($periods as $period)
                        <tr wire:key="period-{{ $period->id }}">
                            <td class="px-4 py-2 text-gray-500">{{ $period->number }}</td>
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $period->label() }}</td>
                            <td class="px-4 py-2 text-gray-700 text-xs whitespace-nowrap">
                                {{ $period->isOpeningBalance() ? '—' : $period->start_date->format('d-m-Y').' — '.$period->end_date->format('d-m-Y') }}
                            </td>
                            <td class="px-4 py-2 text-xs">
                                @if ($period->isClosed())
                                    <span class="inline-flex items-center rounded-full bg-gray-100 text-gray-600 px-2 py-0.5">Gesloten op {{ $period->closed_at->format('d-m-Y') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-green-50 text-green-700 px-2 py-0.5">Open</span>
                                @endif
                            </td>
                            @if (! $period->isClosed() && ($period->isOpeningBalance() || $period->end_date->isPast()))
                                <x-action-cell click="close({{ $period->id }})" icon="lock" title="Periode afsluiten" variant="danger" confirm="Periode afsluiten? Er kunnen dan geen journaalposten meer in geboekt worden." />
                            @else
                                <td class="w-8"></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
