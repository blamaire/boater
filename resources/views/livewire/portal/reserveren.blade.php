<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8">
    <p class="text-sm text-gray-500">
        Kies een object en tijdvak (kwartier-precisie). Reserveren voor een ander kan alleen voor iemand waarvoor je gemachtigd bent — anders gaat je aanvraag via goedkeuring, net als aanvragen die een reserveringsregel overschrijden.
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

    {{-- Mijn reserveringen --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-3">
        <h2 class="font-display text-xl text-gray-900">Mijn reserveringen</h2>
        @if ($myReservations->isEmpty())
            <p class="text-sm text-gray-500 italic">Je hebt op dit moment geen komende reserveringen.</p>
        @else
            <ul class="divide-y divide-gray-100 border border-gray-100 rounded">
                @foreach ($myReservations as $r)
                    <li wire:key="mine-{{ $r->id }}" class="p-3 flex items-center justify-between text-sm gap-3">
                        <div>
                            <div class="font-medium text-gray-900">{{ $r->object->name }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $r->starts_at->translatedFormat('D j M H:i') }} – {{ $r->ends_at->translatedFormat('H:i') }}
                                @if ($r->person_id !== auth()->user()->person->id)
                                    · voor {{ $r->person->first_name }} {{ $r->person->last_name }}
                                @endif
                            </div>
                            @if ($r->object->status === \App\Enums\ReservableObjectStatus::OutOfService)
                                <div class="mt-1 inline-flex items-center rounded-full bg-yellow-50 border border-yellow-200 px-2 py-0.5 text-xs text-yellow-800">
                                    Let op: object is nu buiten gebruik — controleer voor je gaat
                                </div>
                            @endif
                        </div>
                        <button type="button" wire:click="cancel({{ $r->id }})"
                            onclick="return confirm('Reservering intrekken?');"
                            class="text-xs px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50">
                            Intrekken
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Dag-kalender --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-xl text-gray-900">Kalender</h2>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <label class="flex items-center gap-2">
                    Categorie:
                    <select wire:model.live="filterCategoryId" class="border-gray-300 rounded shadow-sm text-sm">
                        <option value="">— Alle —</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-center gap-1">
                    <button type="button" wire:click="shiftDay(-1)" class="px-2 py-1 text-sm rounded border border-gray-300 hover:bg-gray-50">←</button>
                    <input type="date" wire:model.live="viewDate" class="border-gray-300 rounded shadow-sm text-sm" />
                    <button type="button" wire:click="shiftDay(1)" class="px-2 py-1 text-sm rounded border border-gray-300 hover:bg-gray-50">→</button>
                    <button type="button" wire:click="today" class="ml-2 text-xs px-2 py-1 rounded border border-gray-300 hover:bg-gray-50">Vandaag</button>
                </div>
            </div>
        </div>

        <div class="text-sm text-gray-600">{{ $day->translatedFormat('l j F Y') }}</div>

        @if ($objects->isEmpty())
            <p class="text-sm text-gray-500 italic">Geen objecten voor deze filter.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border-separate" style="border-spacing:0">
                    <thead>
                        <tr>
                            <th class="sticky left-0 bg-white px-2 py-1 text-left font-medium text-gray-500 border-b border-gray-200 w-40">Object</th>
                            @foreach ($hours as $h)
                                <th class="px-1 py-1 text-center font-medium text-gray-500 border-b border-gray-200 w-16">{{ sprintf('%02d:00', $h) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($objects as $o)
                            <tr wire:key="row-{{ $o->id }}">
                                <td class="sticky left-0 bg-white px-2 py-1 border-b border-gray-100">
                                    <div class="font-medium text-gray-900">{{ $o->name }}</div>
                                    <div class="text-gray-400">{{ $o->category->name }}</div>
                                    @if ($o->status === \App\Enums\ReservableObjectStatus::OutOfService)
                                        <div class="inline-flex items-center rounded-full bg-yellow-50 border border-yellow-200 px-1 py-0 text-[10px] text-yellow-800 mt-1">buiten gebruik</div>
                                    @endif
                                </td>
                                @php
                                    $reservationsForObject = $dayReservationsByObject->get($o->id, collect());
                                @endphp
                                @foreach ($hours as $h)
                                    @php
                                        $cellStart = $day->copy()->setTime($h, 0);
                                        $cellEnd = $day->copy()->setTime($h, 59, 59);
                                        $overlap = $reservationsForObject->first(fn ($r) => $r->starts_at < $cellEnd && $r->ends_at > $cellStart);
                                    @endphp
                                    <td class="border-b border-l border-gray-100 h-10 relative
                                        @if ($overlap) bg-rzvg-100 @endif
                                        @if (!$overlap && $o->status === \App\Enums\ReservableObjectStatus::Available) hover:bg-rzvg-50 cursor-pointer @endif"
                                        @if (!$overlap && $o->status === \App\Enums\ReservableObjectStatus::Available)
                                            wire:click="pickSlot({{ $o->id }}, '{{ $cellStart->toIso8601String() }}')"
                                        @endif
                                        title="{{ $overlap ? ($overlap->person->first_name.' '.$overlap->person->last_name.' · '.$overlap->starts_at->format('H:i').'–'.$overlap->ends_at->format('H:i')) : sprintf('Klik om te reserveren om %02d:00', $h) }}">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Reserveerformulier --}}
    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="font-display text-xl text-gray-900">Nieuwe reservering</h2>

        <div class="flex gap-4 text-sm">
            <label class="inline-flex items-center gap-2">
                <input type="radio" wire:model.live="mode" value="object"
                    class="text-rzvg-600 focus:ring-rzvg-600" />
                Specifiek object
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="radio" wire:model.live="mode" value="category"
                    class="text-rzvg-600 focus:ring-rzvg-600" />
                Beschikbaar object in categorie (systeem kiest)
            </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            @if ($mode === 'object')
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Object</label>
                    <select wire:model.live="selectedObjectId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                        <option value="">— Kies een object —</option>
                        @foreach ($availableObjects as $o)
                            <option value="{{ $o->id }}">{{ $o->name }} ({{ $o->category->name }})</option>
                        @endforeach
                    </select>
                    @error('selectedObjectId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @else
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Categorie</label>
                    <select wire:model.live="selectedCategoryId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                        <option value="">— Kies een categorie —</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('selectedCategoryId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700">Van</label>
                <input type="datetime-local" step="900" wire:model.live.debounce.500ms="startsAt"
                    class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('startsAt') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tot</label>
                <input type="datetime-local" step="900" wire:model.live.debounce.500ms="endsAt"
                    class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
                @error('endsAt') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            @if ($eligible->count() > 1)
                <div>
                    <label class="block text-sm font-medium text-gray-700">Voor</label>
                    <select wire:model.live="selectedPersonId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                        @foreach ($eligible as $p)
                            <option value="{{ $p->id }}">{{ $p->first_name }} {{ $p->last_name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="{{ $eligible->count() > 1 ? '' : 'sm:col-span-2' }}">
                <label class="block text-sm font-medium text-gray-700">Notitie (optioneel)</label>
                <input type="text" wire:model="note" maxlength="1000"
                    class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
            </div>
        </div>

        @if ($liveViolations !== [])
            <div class="rounded-md bg-yellow-50 border border-yellow-200 text-yellow-900 text-sm px-4 py-3 space-y-1" role="status">
                <div class="font-medium">Deze aanvraag overschrijdt {{ count($liveViolations) }} regel(s). Je kunt hem alsnog indienen — dan gaat 'ie via goedkeuring:</div>
                <ul class="list-disc pl-5">
                    @foreach ($liveViolations as $v)
                        <li><span class="font-medium">{{ $v['rule_name'] }}:</span> {{ $v['message'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex justify-end">
            <button type="button" wire:click="reserve"
                class="inline-flex items-center px-4 py-2 bg-rzvg-600 text-white text-sm font-medium rounded hover:bg-rzvg-700">
                {{ $liveViolations !== [] ? 'Indienen voor goedkeuring' : 'Reserveren' }}
            </button>
        </div>
    </section>
</div>
