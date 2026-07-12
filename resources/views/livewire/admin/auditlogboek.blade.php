<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Alleen-lezen logboek van alle betekenisvolle handelingen (§31). Entries worden nooit gewijzigd of verwijderd.
        Klik een regel aan voor de details.
    </p>

    {{-- Filters: persoon, module, periode, vrij zoeken (§31.1). --}}
    <div class="flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">Persoon</span>
            <select wire:model.live="actorPersonId" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Iedereen —</option>
                @foreach ($actors as $actor)
                    <option value="{{ $actor->id }}">{{ $actor->last_name }}, {{ $actor->first_name }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">Module</span>
            <select wire:model.live="module" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                @foreach ($modules as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">Van</span>
            <input type="date" wire:model.live="dateFrom" class="border-gray-300 rounded shadow-sm text-sm" />
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">Tot en met</span>
            <input type="date" wire:model.live="dateTo" class="border-gray-300 rounded shadow-sm text-sm" />
        </label>
        <label class="flex flex-col gap-1 grow min-w-[12rem]">
            <span class="text-gray-600">Zoeken (actie / subject)</span>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="bijv. role.assigned"
                class="border-gray-300 rounded shadow-sm text-sm" />
        </label>
        <button type="button" wire:click="resetFilters"
            class="text-xs text-gray-500 hover:text-gray-700 underline pb-2">Filters wissen</button>
    </div>

    {{-- Detail van de geselecteerde entry: nette veld-diff + ruwe JSON eronder. --}}
    @if ($selected)
        <section class="bg-white rounded-lg shadow-sm border border-rzvg-200 ring-1 ring-rzvg-100 p-4 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="font-mono text-sm text-gray-900">{{ $selected->action }}</div>
                    <div class="text-xs text-gray-500">
                        {{ optional($selected->occurred_at)->format('d-m-Y H:i:s') }}
                        · door
                        @if ($selected->actor)
                            {{ $selected->actor->first_name }} {{ $selected->actor->last_name }}
                        @else
                            <span class="italic">systeem/onbekend</span>
                        @endif
                        @if ($selected->subject_type)
                            ·
                            @php($selectedSubjectUrl = $this->subjectUrl($selected->subject_type, $selected->subject_id))
                            @if ($selectedSubjectUrl)
                                <a href="{{ $selectedSubjectUrl }}" class="text-rzvg-600 hover:text-rzvg-800 hover:underline">
                                    {{ class_basename($selected->subject_type) }}@if ($selected->subject_id) #{{ $selected->subject_id }}@endif
                                </a>
                            @else
                                {{ class_basename($selected->subject_type) }}@if ($selected->subject_id) #{{ $selected->subject_id }}@endif
                            @endif
                        @endif
                        @if ($selected->ip)
                            · {{ $selected->ip }}
                        @endif
                    </div>
                </div>
                <button type="button" wire:click="closeDetail"
                    class="text-gray-400 hover:text-gray-600 text-sm">Sluiten ✕</button>
            </div>

            @if (count($diff) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Veld</th>
                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Was</th>
                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase">Werd</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($diff as $row)
                                <tr>
                                    <td class="px-3 py-1.5 font-medium text-gray-700 align-top">{{ $row['key'] }}</td>
                                    <td class="px-3 py-1.5 text-gray-500 align-top whitespace-pre-wrap break-words">{{ $row['old'] }}</td>
                                    <td class="px-3 py-1.5 text-gray-900 align-top whitespace-pre-wrap break-words">{{ $row['new'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-xs text-gray-400 italic">Geen veldwijzigingen vastgelegd voor deze handeling.</p>
            @endif

            <details class="text-xs" open>
                <summary class="cursor-pointer text-gray-500 hover:text-gray-700">Ruwe gegevens (JSON)</summary>
                <div class="mt-2 grid gap-3 md:grid-cols-3">
                    @foreach (['before' => 'before', 'after' => 'after', 'context' => 'context'] as $label => $attr)
                        <div>
                            <div class="text-gray-400 uppercase tracking-wide mb-1">{{ $label }}</div>
                            <pre class="bg-gray-50 border border-gray-200 rounded p-2 overflow-x-auto text-gray-700">{{ $selected->{$attr} ? json_encode($selected->{$attr}, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
                        </div>
                    @endforeach
                </div>
            </details>
        </section>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Wanneer</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Wie</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actie</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($entries as $entry)
                        <tr wire:key="entry-{{ $entry->id }}" wire:click="show({{ $entry->id }})"
                            @class([
                                'cursor-pointer hover:bg-gray-50',
                                'bg-rzvg-50' => $selected && $selected->id === $entry->id,
                            ])>
                            <td class="px-4 py-2 whitespace-nowrap text-gray-500">
                                {{ optional($entry->occurred_at)->format('d-m-Y H:i:s') }}
                            </td>
                            <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                                @if ($entry->actor)
                                    {{ $entry->actor->first_name }} {{ $entry->actor->last_name }}
                                @else
                                    <span class="text-gray-400 italic">systeem</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 font-mono text-xs text-gray-900">{{ $entry->action }}</td>
                            <td class="px-4 py-2 text-xs whitespace-nowrap">
                                @if ($entry->subject_type)
                                    @php($subjectUrl = $this->subjectUrl($entry->subject_type, $entry->subject_id))
                                    @if ($subjectUrl)
                                        {{-- stopPropagation: het aanklikken van de link mag niet ook de detailregel openen. --}}
                                        <a href="{{ $subjectUrl }}" onclick="event.stopPropagation()"
                                            class="text-rzvg-600 hover:text-rzvg-800 hover:underline">
                                            {{ class_basename($entry->subject_type) }}@if ($entry->subject_id) #{{ $entry->subject_id }}@endif
                                        </a>
                                    @else
                                        <span class="text-gray-500">{{ class_basename($entry->subject_type) }}@if ($entry->subject_id) #{{ $entry->subject_id }}@endif</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500">Geen auditregels met de huidige filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($entries->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $entries->links() }}
            </div>
        @endif
    </section>
</div>
