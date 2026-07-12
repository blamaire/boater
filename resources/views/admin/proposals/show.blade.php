@php
    $statusLabels = [
        'draft' => 'Concept',
        'submitted' => 'Ingediend',
        'in_review' => 'In review',
        'approved' => 'Goedgekeurd',
        'applied' => 'Toegepast',
        'rejected' => 'Afgewezen',
        'returned' => 'Teruggestuurd',
        'withdrawn' => 'Ingetrokken',
        'conflicted' => 'Conflict',
    ];
    $stepLabels = [
        'pending' => 'In afwachting',
        'approved' => 'Goedgekeurd',
        'rejected' => 'Afgewezen',
        'returned' => 'Teruggestuurd',
        'skipped' => 'Overgeslagen',
    ];
    $changeLabels = ['create' => 'Aanmaken', 'update' => 'Wijzigen', 'delete' => 'Verwijderen'];
    $assigneeLabels = ['role' => 'Rol', 'group' => 'Groep', 'person' => 'Persoon'];
@endphp

<x-app-layout>
    <x-slot name="header">Voorstel #{{ $proposal->id }}</x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <a href="{{ url()->previous() }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Terug</a>

        <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-lg font-medium text-gray-900">
                        {{ $changeLabels[$proposal->change_type->value] ?? $proposal->change_type->value }}
                        · <span class="font-mono text-base text-gray-600">{{ class_basename($proposal->subject_type) }}</span>
                        @if ($proposal->subject_id)
                            <span class="text-gray-400">#{{ $proposal->subject_id }}</span>
                        @endif
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        Ingediend door
                        @if ($proposal->proposedBy)
                            {{ $proposal->proposedBy->first_name }} {{ $proposal->proposedBy->last_name }}
                        @else
                            <span class="italic">onbekend</span>
                        @endif
                        @if ($proposal->policy)
                            · beleid: {{ $proposal->policy->name }}
                        @endif
                    </div>
                </div>
                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700 whitespace-nowrap">
                    {{ $statusLabels[$proposal->status->value] ?? $proposal->status->value }}
                </span>
            </div>

            @if ($cms)
                <div class="rounded-md bg-rzvg-50 border border-rzvg-100 px-3 py-2 text-sm text-gray-700">
                    <span class="font-medium text-gray-700">Pagina:</span> {{ $cms['label'] }}
                    @if ($cms['diffUrl'])
                        · <a href="{{ $cms['diffUrl'] }}" class="text-rzvg-600 hover:text-rzvg-800 hover:underline font-medium">Bekijk inhoudswijziging &rarr;</a>
                    @else
                        <span class="text-gray-400 italic">(nieuwe pagina — geen vorige versie om te vergelijken)</span>
                    @endif
                </div>
            @endif

            @if ($proposal->decision_reason)
                <div class="text-sm text-gray-600">
                    <span class="font-medium text-gray-700">Reden:</span> {{ $proposal->decision_reason }}
                </div>
            @endif
            @if ($proposal->applied_at)
                <div class="text-sm text-gray-500">Toegepast op {{ $proposal->applied_at->format('d-m-Y H:i') }}</div>
            @endif
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-2">
            <h3 class="text-sm font-medium text-gray-700">Voorgestelde wijziging (payload)</h3>
            <pre class="bg-gray-50 border border-gray-200 rounded p-3 overflow-x-auto text-xs text-gray-700">{{ json_encode($proposal->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-medium text-gray-700">Reviewstappen</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Toegewezen aan</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Beslist door</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Wanneer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($proposal->steps as $step)
                        <tr>
                            <td class="px-4 py-2 text-gray-700">{{ $step->sequence }}</td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ $assigneeLabels[$step->assignee_type->value] ?? $step->assignee_type->value }} #{{ $step->assignee_id }}
                            </td>
                            <td class="px-4 py-2 text-gray-700">{{ $stepLabels[$step->status->value] ?? $step->status->value }}</td>
                            <td class="px-4 py-2 text-gray-700">
                                @if ($step->decidedBy)
                                    {{ $step->decidedBy->first_name }} {{ $step->decidedBy->last_name }}
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-500 whitespace-nowrap">
                                {{ $step->decided_at?->format('d-m-Y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">Geen reviewstappen (direct doorgevoerd of via bypass).</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
