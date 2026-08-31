<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Een nieuwe activiteit maak je aan met minimaal één datum — meerdere is optioneel (aaneensluitend of los). Bij
        meerdere data kies je expliciet: een <strong>bundel</strong> (elke activiteit apart aanmelden, wel
        gezamenlijk te bewerken) of een <strong>reeks</strong> (in één keer aanmelden voor alles). Categorieën beheer
        je in <a href="{{ route('admin.activity-categories.index') }}" class="text-rzvg-600 hover:text-rzvg-800 underline">categorieën</a>,
        activiteitenpagina's in <a href="{{ route('admin.activity-pages.index') }}" class="text-rzvg-600 hover:text-rzvg-800 underline">activiteitenpagina's</a>.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4 text-sm">
            <label class="flex items-center gap-2">
                Status:
                <select wire:model.live="filterStatus" class="border-gray-300 rounded shadow-sm text-sm">
                    <option value="all">Alle</option>
                    <option value="concept">Concept</option>
                    <option value="gepubliceerd">Gepubliceerd</option>
                    <option value="afgelast">Afgelast</option>
                </select>
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model.live="hideHistory" class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                Historie verbergen
            </label>
        </div>
        @unless ($creatingGroup || $editingGroupId || $addingOccurrencesToId || $editingActivityId)
            <button type="button" wire:click="startCreateGroup" class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                Activiteit toevoegen
            </button>
        @endunless
    </div>

    {{-- Groep aanmaken of groep-breed bewerken --}}
    @if ($creatingGroup || $editingGroupId)
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
            <h2 class="font-display text-xl text-gray-900">{{ $editingGroupId ? 'Activiteit wijzigen' : 'Nieuwe activiteit' }}</h2>

            {{-- 1. Basisgegevens --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <x-input-label for="g-title" value="Titel" />
                    <x-text-input id="g-title" wire:model="title" class="mt-1 w-full" />
                    @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="g-category" value="Categorie" />
                    <select id="g-category" wire:model="categoryId" class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                        <option value="">— Kies —</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="g-description" value="Omschrijving" />
                    @include('livewire.admin.partials.trix-editor', ['prefix' => 'g'])
                </div>
                <div>
                    <x-input-label for="g-location" value="Locatie (optioneel, standaard per voorkomen)" />
                    <x-text-input id="g-location" wire:model="location" class="mt-1 w-full" />
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4 space-y-3">
                <h3 class="font-medium text-gray-900 text-sm">Tijdlijn</h3>
                <x-activity-timeline
                    :dates="$timelineDates"
                    :publish-from="$timelinePublishFrom"
                    :publish-until="$timelinePublishUntil"
                    :enrollment-opens-at="$timelineEnrollmentOpensAt"
                    :enrollment-closes-at="$timelineEnrollmentClosesAt"
                    :cancellation-deadline="$timelineCancellationDeadline"
                />
            </div>

            {{-- 2. Losse activiteit, reeks of bundel --}}
            <div class="border-t border-gray-200 pt-4 space-y-3">
                <h3 class="font-medium text-gray-900 text-sm">Hoe vaak vindt dit plaats?</h3>
                @if ($creatingGroup)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <button type="button" wire:click="selectCreationMode('los')"
                            @class(['text-left p-4 rounded-lg border-2', 'border-rzvg-600 bg-rzvg-50' => $creationMode === 'los', 'border-gray-200 hover:border-gray-300' => $creationMode !== 'los'])>
                            <div class="font-medium text-gray-900">Losse activiteit</div>
                            <div class="text-xs text-gray-500 mt-1">Vindt op één moment plaats, eventueel over meerdere dagen (bv. een weekend).</div>
                        </button>
                        <button type="button" wire:click="selectCreationMode('reeks')"
                            @class(['text-left p-4 rounded-lg border-2', 'border-rzvg-600 bg-rzvg-50' => $creationMode === 'reeks', 'border-gray-200 hover:border-gray-300' => $creationMode !== 'reeks'])>
                            <div class="font-medium text-gray-900">Reeks</div>
                            <div class="text-xs text-gray-500 mt-1">Meerdere data die bij elkaar horen — in één keer aanmelden voor alles.</div>
                        </button>
                        <button type="button" wire:click="selectCreationMode('bundel')"
                            @class(['text-left p-4 rounded-lg border-2', 'border-rzvg-600 bg-rzvg-50' => $creationMode === 'bundel', 'border-gray-200 hover:border-gray-300' => $creationMode !== 'bundel'])>
                            <div class="font-medium text-gray-900">Bundel</div>
                            <div class="text-xs text-gray-500 mt-1">Meerdere data die bij elkaar horen — per keer apart aanmelden, wel gezamenlijk te bewerken.</div>
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm max-w-lg">
                        <button type="button" wire:click="selectEnrollmentLevel('reeks')"
                            @class(['text-left p-4 rounded-lg border-2', 'border-rzvg-600 bg-rzvg-50' => $enrollmentLevel === 'reeks', 'border-gray-200 hover:border-gray-300' => $enrollmentLevel !== 'reeks'])>
                            <div class="font-medium text-gray-900">Reeks</div>
                            <div class="text-xs text-gray-500 mt-1">In één keer aanmelden voor alle voorkomens.</div>
                        </button>
                        <button type="button" wire:click="selectEnrollmentLevel('bundel')"
                            @class(['text-left p-4 rounded-lg border-2', 'border-rzvg-600 bg-rzvg-50' => $enrollmentLevel === 'bundel', 'border-gray-200 hover:border-gray-300' => $enrollmentLevel !== 'bundel'])>
                            <div class="font-medium text-gray-900">Bundel</div>
                            <div class="text-xs text-gray-500 mt-1">Per voorkomen apart aanmelden, wel gezamenlijk bewerkt.</div>
                        </button>
                    </div>
                @endif
            </div>

            {{-- 3. Data toevoegen/verwijderen/genereren --}}
            <div class="border-t border-gray-200 pt-4 space-y-3">
                @if ($editingGroupId)
                    <div class="space-y-3">
                        <h3 class="font-medium text-gray-900 text-sm">Toepassen op</h3>
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" wire:model="editScope" value="hele_reeks" class="text-rzvg-600 focus:ring-rzvg-600" />
                                Hele groep
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" wire:model="editScope" value="dit_en_volgende" class="text-rzvg-600 focus:ring-rzvg-600" />
                                Dit en volgende voorkomens (splitst af)
                            </label>
                        </div>
                        @if ($editScope === 'dit_en_volgende')
                            <div class="max-w-sm">
                                <x-input-label for="g-split-from" value="Vanaf voorkomen" />
                                <select id="g-split-from" wire:model="splitFromActivityId" class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                                    <option value="">— Kies —</option>
                                    @foreach ($occurrences as $occ)
                                        <option value="{{ $occ->id }}">{{ $occ->starts_at->format('d-m-Y H:i') }}@if ($occ->is_exception) (uitzondering) @endif</option>
                                    @endforeach
                                </select>
                                @error('splitFromActivityId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <p class="text-xs text-gray-500">Voorkomens die al los zijn aangepast (uitzonderingen) blijven altijd buiten deze wijziging.</p>
                    </div>

                    <div class="pt-2 space-y-2">
                        <h3 class="font-medium text-gray-900 text-sm">Voorkomens ({{ $occurrences->count() }})</h3>
                        <ul class="divide-y divide-gray-100 text-sm">
                            @foreach ($occurrences as $occ)
                                <li class="flex items-center justify-between py-1.5">
                                    <span>
                                        {{ $occ->starts_at->format('d-m-Y H:i') }}
                                        <span class="text-xs text-gray-500">— {{ $occ->status->label() }}, {{ $occ->enrollments_count }} inschrijving(en)</span>
                                        @if ($occ->is_exception)
                                            <span class="text-xs text-amber-700">(uitzondering)</span>
                                        @endif
                                    </span>
                                    <button type="button" wire:click="deleteOccurrence({{ $occ->id }})"
                                        onclick="return confirm('Dit voorkomen verwijderen? Inschrijvingen worden ook gewist.');"
                                        class="text-red-600 hover:text-red-800 text-xs">Verwijderen</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @elseif ($creationMode === 'los')
                    <h3 class="font-medium text-gray-900 text-sm">Datum</h3>
                    <div class="flex flex-col sm:flex-row gap-8 text-sm" x-data="{
                        viewYear: new Date().getFullYear(),
                        viewMonth: new Date().getMonth(),
                        rangeStart: @js($startsAt !== '' ? substr($startsAt, 0, 10) : null),
                        rangeEnd: @js($endsAt !== '' ? substr($endsAt, 0, 10) : null),
                        startTime: @js($startsAt !== '' ? substr($startsAt, 11, 5) : '10:00'),
                        endTime: @js($endsAt !== '' ? substr($endsAt, 11, 5) : '10:00'),
                        monthNames: ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
                        weekdayLabels: ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'],
                        get monthLabel() { return this.monthNames[this.viewMonth] + ' ' + this.viewYear; },
                        get cells() {
                            const first = new Date(this.viewYear, this.viewMonth, 1);
                            const startOffset = (first.getDay() + 6) % 7;
                            const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
                            const cells = [];
                            for (let i = 0; i < startOffset; i++) cells.push(null);
                            for (let d = 1; d <= daysInMonth; d++) {
                                const mm = String(this.viewMonth + 1).padStart(2, '0');
                                const dd = String(d).padStart(2, '0');
                                cells.push(this.viewYear + '-' + mm + '-' + dd);
                            }
                            return cells;
                        },
                        prevMonth() { this.viewMonth--; if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; } },
                        nextMonth() { this.viewMonth++; if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; } },
                        sync() {
                            $wire.set('startsAt', this.rangeStart ? this.rangeStart + 'T' + (this.startTime || '00:00') : '');
                            $wire.set('endsAt', this.rangeEnd ? this.rangeEnd + 'T' + (this.endTime || '23:59') : '');
                        },
                        pick(date) {
                            if (! date) return;
                            if (! this.rangeStart || this.rangeEnd) {
                                this.rangeStart = date;
                                this.rangeEnd = null;
                                this.sync();
                                return;
                            }
                            if (date < this.rangeStart) {
                                this.rangeStart = date;
                                this.sync();
                                return;
                            }
                            this.rangeEnd = date;
                            this.sync();
                        },
                    }" x-init="
                        $watch('rangeStart', () => sync());
                        $watch('startTime', () => sync());
                        $watch('rangeEnd', () => sync());
                        $watch('endTime', () => sync());
                    ">
                        <div class="max-w-xs">
                            <div class="flex items-center justify-between mb-1">
                                <button type="button" @click="prevMonth" class="p-1 text-gray-500 hover:text-gray-800">&laquo;</button>
                                <span class="text-sm font-medium text-gray-900" x-text="monthLabel"></span>
                                <button type="button" @click="nextMonth" class="p-1 text-gray-500 hover:text-gray-800">&raquo;</button>
                            </div>
                            <div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-500 mb-1">
                                <template x-for="d in weekdayLabels" :key="d">
                                    <span x-text="d"></span>
                                </template>
                            </div>
                            <div class="grid grid-cols-7 gap-1">
                                <template x-for="(cell, idx) in cells" :key="idx">
                                    <button type="button"
                                        x-show="cell !== null"
                                        @click="pick(cell)"
                                        x-text="cell ? cell.split('-')[2].replace(/^0/, '') : ''"
                                        :class="cell && (cell === rangeStart || cell === rangeEnd)
                                            ? 'bg-rzvg-600 text-white'
                                            : (cell && rangeStart && rangeEnd && cell > rangeStart && cell < rangeEnd
                                                ? 'bg-rzvg-100 text-rzvg-800'
                                                : 'text-gray-700 hover:bg-gray-100')"
                                        class="h-8 w-8 mx-auto rounded text-xs flex items-center justify-center">
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 flex-1 sm:border-l sm:border-gray-200 sm:pl-8">
                            <div>
                                <x-input-label for="g-starts-at" value="Begint op" />
                                <div class="flex items-center gap-2 mt-1">
                                    <input id="g-starts-at" type="date" x-model="rangeStart" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                                    <input type="time" x-model="startTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                                </div>
                                @error('startsAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="g-ends-at" value="Eindigt op (optioneel)" />
                                <div class="flex items-center gap-2 mt-1">
                                    <input id="g-ends-at" type="date" x-model="rangeEnd" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                                    <input type="time" x-model="endTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                                </div>
                                @error('endsAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @else
                    @include('livewire.admin.partials.activity-series-date-picker')
                @endif
            </div>

            {{-- 4. Deelnemers --}}
            <div class="border-t border-gray-200 pt-4 space-y-3">
                <h3 class="font-medium text-gray-900 text-sm">Deelnemers</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <x-input-label for="g-min-capacity" value="Minimum aantal deelnemers (optioneel)" />
                        <x-text-input id="g-min-capacity" type="number" min="0" wire:model="minCapacity" class="mt-1 w-full" />
                        @error('minCapacity') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label for="g-capacity" value="Maximum aantal deelnemers (optioneel; leeg = onbeperkt)" />
                        <x-text-input id="g-capacity" type="number" min="1" wire:model="capacity" class="mt-1 w-full" />
                        @error('capacity') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label for="g-min-age" value="Minimumleeftijd (optioneel)" />
                        <x-text-input id="g-min-age" type="number" min="0" wire:model="minAge" class="mt-1 w-full" />
                        @error('minAge') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label for="g-max-age" value="Maximumleeftijd (optioneel)" />
                        <x-text-input id="g-max-age" type="number" min="0" wire:model="maxAge" class="mt-1 w-full" />
                        @error('maxAge') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- 5. Inschrijven en annuleren --}}
            <div class="border-t border-gray-200 pt-4 space-y-3">
                <h3 class="font-medium text-gray-900 text-sm">Inschrijven en annuleren</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm" x-data="{
                    opensDate: @js($enrollmentOpensAt !== '' ? substr($enrollmentOpensAt, 0, 10) : ''),
                    opensTime: @js($enrollmentOpensAt !== '' ? substr($enrollmentOpensAt, 11, 5) : ''),
                    closesDate: @js($enrollmentClosesAt !== '' ? substr($enrollmentClosesAt, 0, 10) : ''),
                    closesTime: @js($enrollmentClosesAt !== '' ? substr($enrollmentClosesAt, 11, 5) : ''),
                    deadlineDate: @js($cancellationDeadline !== '' ? substr($cancellationDeadline, 0, 10) : ''),
                    deadlineTime: @js($cancellationDeadline !== '' ? substr($cancellationDeadline, 11, 5) : ''),
                    syncOpens() { $wire.set('enrollmentOpensAt', this.opensDate ? this.opensDate + 'T' + (this.opensTime || '00:00') : ''); },
                    syncCloses() { $wire.set('enrollmentClosesAt', this.closesDate ? this.closesDate + 'T' + (this.closesTime || '23:59') : ''); },
                    syncDeadline() { $wire.set('cancellationDeadline', this.deadlineDate ? this.deadlineDate + 'T' + (this.deadlineTime || '23:59') : ''); },
                }" x-init="
                    $watch('opensDate', () => syncOpens());
                    $watch('opensTime', () => syncOpens());
                    $watch('closesDate', () => syncCloses());
                    $watch('closesTime', () => syncCloses());
                    $watch('deadlineDate', () => syncDeadline());
                    $watch('deadlineTime', () => syncDeadline());
                ">
                    <div>
                        <x-input-label value="Inschrijven mogelijk vanaf (optioneel)" />
                        <div class="flex items-center gap-2 mt-1">
                            <input type="date" x-model="opensDate" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            <input type="time" x-model="opensTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                        </div>
                        @error('enrollmentOpensAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label value="Inschrijven mogelijk tot (optioneel)" />
                        <div class="flex items-center gap-2 mt-1">
                            <input type="date" x-model="closesDate" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            <input type="time" x-model="closesTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                        </div>
                        @error('enrollmentClosesAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label value="Laatste moment om te annuleren (optioneel)" />
                        <div class="flex items-center gap-2 mt-1">
                            <input type="date" x-model="deadlineDate" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            <input type="time" x-model="deadlineTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                        </div>
                        @error('cancellationDeadline') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- 6. Gedelegeerde beheerders --}}
            @if ($creatingGroup)
                <div class="border-t border-gray-200 pt-4 space-y-3">
                    <h3 class="font-medium text-gray-900 text-sm">Gedelegeerde beheerders (optioneel)</h3>
                    <div class="flex flex-wrap gap-1">
                        @forelse ($pendingManagers as $pm)
                            @php
                                $person = $personsForAssignment->firstWhere('id', $pm['person_id']);
                            @endphp
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-xs">
                                {{ $person?->first_name }} {{ $person?->last_name }}
                                <label class="inline-flex items-center gap-1 ml-1" title="Mailnotificatie bij wijzigingen/in- en uitschrijvingen">
                                    <input type="checkbox" wire:click="togglePendingManagerNotify({{ $pm['person_id'] }})"
                                        @checked($pm['notify']) class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600 h-3 w-3" />
                                    <span class="text-gray-500">mail</span>
                                </label>
                                <button type="button" wire:click="removePendingManager({{ $pm['person_id'] }})"
                                    class="text-red-600 hover:text-red-800" title="Verwijderen">×</button>
                            </span>
                        @empty
                            @if (count($pendingManagerGroups) === 0)
                                <span class="text-xs text-gray-400 italic">Nog geen gedelegeerde beheerders.</span>
                            @endif
                        @endforelse
                        @foreach ($pendingManagerGroups as $pmg)
                            @php
                                $group = $groupsForAssignment->firstWhere('id', $pmg['approver_group_id']);
                            @endphp
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-xs">
                                {{ $group?->name }} (groep)
                                <label class="inline-flex items-center gap-1 ml-1" title="Mailnotificatie bij wijzigingen/in- en uitschrijvingen">
                                    <input type="checkbox" wire:click="togglePendingManagerGroupNotify({{ $pmg['approver_group_id'] }})"
                                        @checked($pmg['notify']) class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600 h-3 w-3" />
                                    <span class="text-gray-500">mail</span>
                                </label>
                                <button type="button" wire:click="removePendingManagerGroup({{ $pmg['approver_group_id'] }})"
                                    class="text-red-600 hover:text-red-800" title="Verwijderen">×</button>
                            </span>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <select wire:model="pendingManagerPersonId" class="border-gray-300 rounded shadow-sm text-xs">
                            <option value="">— Kies persoon —</option>
                            @foreach ($personsForAssignment as $p)
                                <option value="{{ $p->id }}">{{ $p->first_name }} {{ $p->last_name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="addPendingManager" class="text-xs px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                            Toevoegen
                        </button>
                        <select wire:model="pendingManagerGroupId" class="border-gray-300 rounded shadow-sm text-xs">
                            <option value="">— Kies groep —</option>
                            @foreach ($groupsForAssignment as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="addPendingManagerGroup" class="text-xs px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                            Toevoegen
                        </button>
                    </div>
                </div>
            @endif

            {{-- 7. Standaard- en annuleringskosten --}}
            @include('livewire.admin.partials.activity-cost-products', ['prefix' => 'g'])

            {{-- 8. Extra inschrijfvelden --}}
            @if ($creatingGroup)
                @include('livewire.admin.partials.activity-registration-fields', ['mode' => 'pending'])
            @endif

            {{-- 9. Zichtbaarheid en publicatie --}}
            <div class="border-t border-gray-200 pt-4 space-y-3">
                <h3 class="font-medium text-gray-900 text-sm">Zichtbaarheid en publicatie</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <x-input-label for="g-visibility" value="Zichtbaarheid" />
                        <select id="g-visibility" wire:model="visibility" class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                            @foreach ($visibilities as $vis)
                                <option value="{{ $vis->value }}">{{ $vis->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="g-status" value="Status" />
                        <select id="g-status" wire:model="status" class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                            @foreach ($statuses as $stat)
                                <option value="{{ $stat->value }}">{{ $stat->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="contents" x-data="{
                        fromDate: @js($publishFrom !== '' ? substr($publishFrom, 0, 10) : ''),
                        fromTime: @js($publishFrom !== '' ? substr($publishFrom, 11, 5) : ''),
                        untilDate: @js($publishUntil !== '' ? substr($publishUntil, 0, 10) : ''),
                        untilTime: @js($publishUntil !== '' ? substr($publishUntil, 11, 5) : ''),
                        syncFrom() { $wire.set('publishFrom', this.fromDate ? this.fromDate + 'T' + (this.fromTime || '00:00') : ''); },
                        syncUntil() { $wire.set('publishUntil', this.untilDate ? this.untilDate + 'T' + (this.untilTime || '23:59') : ''); },
                    }" x-init="
                        $watch('fromDate', () => syncFrom());
                        $watch('fromTime', () => syncFrom());
                        $watch('untilDate', () => syncUntil());
                        $watch('untilTime', () => syncUntil());
                    ">
                        <div>
                            <x-input-label for="g-publish-from" value="Gepubliceerd vanaf (optioneel)" />
                            <div class="flex items-center gap-2 mt-1">
                                <input id="g-publish-from" type="date" x-model="fromDate" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                                <input type="time" x-model="fromTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="g-publish-until" value="Gepubliceerd tot en met (optioneel)" />
                            <div class="flex items-center gap-2 mt-1">
                                <input id="g-publish-until" type="date" x-model="untilDate" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                                <input type="time" x-model="untilTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            </div>
                            @error('publishUntil') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between border-t border-gray-200 pt-4">
                <button type="button" wire:click="cancelForm" class="text-sm px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Annuleren</button>
                @if ($editingGroupId)
                    <button type="button" wire:click="applyGroupEdit" class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">Wijzigingen opslaan</button>
                @else
                    <button type="button" wire:click="createGroup" class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">Activiteit aanmaken</button>
                @endif
            </div>
        </section>
    @endif

    {{-- Extra voorkomens toevoegen aan een bestaande groep --}}
    @if ($addingOccurrencesToId)
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
            <h2 class="font-display text-xl text-gray-900">Voorkomens toevoegen</h2>
            @include('livewire.admin.partials.activity-series-date-picker')
            <div class="flex justify-between border-t border-gray-200 pt-4">
                <button type="button" wire:click="cancelForm" class="text-sm px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Annuleren</button>
                <button type="button" wire:click="addOccurrences" class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">Voorkomens toevoegen</button>
            </div>
        </section>
    @endif

    {{-- Eén los voorkomen bewerken --}}
    @if ($editingActivityId)
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
            <h2 class="font-display text-xl text-gray-900">Voorkomen wijzigen</h2>

            {{-- 1. Basisgegevens --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <x-input-label for="a-title" value="Titel" />
                    <x-text-input id="a-title" wire:model="title" class="mt-1 w-full" />
                    @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="a-category" value="Categorie" />
                    <select id="a-category" wire:model="categoryId"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                        <option value="">— Kies —</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('categoryId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="a-description" value="Omschrijving" />
                    @include('livewire.admin.partials.trix-editor', ['prefix' => 'a'])
                </div>
                <div>
                    <x-input-label for="a-location" value="Locatie (optioneel)" />
                    <x-text-input id="a-location" wire:model="location" class="mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="a-event" value="Activiteitenpagina (optioneel)" />
                    <select id="a-event" wire:model="activityPageId"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                        <option value="">— Geen —</option>
                        @foreach ($activityPages as $event)
                            <option value="{{ $event->id }}">{{ $event->page->title }}</option>
                        @endforeach
                    </select>
                    @error('activityPageId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4 space-y-3">
                <h3 class="font-medium text-gray-900 text-sm">Tijdlijn</h3>
                <x-activity-timeline
                    :dates="$timelineDates"
                    :publish-from="$timelinePublishFrom"
                    :publish-until="$timelinePublishUntil"
                    :enrollment-opens-at="$timelineEnrollmentOpensAt"
                    :enrollment-closes-at="$timelineEnrollmentClosesAt"
                    :cancellation-deadline="$timelineCancellationDeadline"
                />
            </div>

            {{-- 2. Datum --}}
            <div class="border-t border-gray-200 pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <x-input-label for="a-start" value="Begint op" />
                    <input id="a-start" type="datetime-local" wire:model.live="startsAt"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                    @error('startsAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label for="a-end" value="Eindigt op (optioneel)" />
                    <input id="a-end" type="datetime-local" wire:model.live="endsAt"
                        class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                    @error('endsAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- 3. Deelnemers --}}
            <div class="border-t border-gray-200 pt-4 space-y-3">
                <h3 class="font-medium text-gray-900 text-sm">Deelnemers</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <x-input-label for="a-min-capacity" value="Minimum aantal deelnemers (optioneel)" />
                        <x-text-input id="a-min-capacity" type="number" min="0" wire:model="minCapacity" class="mt-1 w-full" />
                        @error('minCapacity') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label for="a-capacity" value="Maximum aantal deelnemers (optioneel; leeg = onbeperkt)" />
                        <x-text-input id="a-capacity" type="number" min="1" wire:model="capacity" class="mt-1 w-full" />
                        @error('capacity') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label for="a-min-age" value="Minimumleeftijd (optioneel)" />
                        <x-text-input id="a-min-age" type="number" min="0" wire:model="minAge" class="mt-1 w-full" />
                        @error('minAge') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label for="a-max-age" value="Maximumleeftijd (optioneel)" />
                        <x-text-input id="a-max-age" type="number" min="0" wire:model="maxAge" class="mt-1 w-full" />
                        @error('maxAge') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- 4. Inschrijven en annuleren --}}
            <div class="border-t border-gray-200 pt-4 space-y-3">
                <h3 class="font-medium text-gray-900 text-sm">Inschrijven en annuleren</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm" x-data="{
                    opensDate: @js($enrollmentOpensAt !== '' ? substr($enrollmentOpensAt, 0, 10) : ''),
                    opensTime: @js($enrollmentOpensAt !== '' ? substr($enrollmentOpensAt, 11, 5) : ''),
                    closesDate: @js($enrollmentClosesAt !== '' ? substr($enrollmentClosesAt, 0, 10) : ''),
                    closesTime: @js($enrollmentClosesAt !== '' ? substr($enrollmentClosesAt, 11, 5) : ''),
                    deadlineDate: @js($cancellationDeadline !== '' ? substr($cancellationDeadline, 0, 10) : ''),
                    deadlineTime: @js($cancellationDeadline !== '' ? substr($cancellationDeadline, 11, 5) : ''),
                    syncOpens() { $wire.set('enrollmentOpensAt', this.opensDate ? this.opensDate + 'T' + (this.opensTime || '00:00') : ''); },
                    syncCloses() { $wire.set('enrollmentClosesAt', this.closesDate ? this.closesDate + 'T' + (this.closesTime || '23:59') : ''); },
                    syncDeadline() { $wire.set('cancellationDeadline', this.deadlineDate ? this.deadlineDate + 'T' + (this.deadlineTime || '23:59') : ''); },
                }" x-init="
                    $watch('opensDate', () => syncOpens());
                    $watch('opensTime', () => syncOpens());
                    $watch('closesDate', () => syncCloses());
                    $watch('closesTime', () => syncCloses());
                    $watch('deadlineDate', () => syncDeadline());
                    $watch('deadlineTime', () => syncDeadline());
                ">
                    <div>
                        <x-input-label value="Inschrijven mogelijk vanaf (optioneel)" />
                        <div class="flex items-center gap-2 mt-1">
                            <input type="date" x-model="opensDate" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            <input type="time" x-model="opensTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                        </div>
                        @error('enrollmentOpensAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label value="Inschrijven mogelijk tot (optioneel)" />
                        <div class="flex items-center gap-2 mt-1">
                            <input type="date" x-model="closesDate" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            <input type="time" x-model="closesTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                        </div>
                        @error('enrollmentClosesAt') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label value="Laatste moment om te annuleren (optioneel)" />
                        <div class="flex items-center gap-2 mt-1">
                            <input type="date" x-model="deadlineDate" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            <input type="time" x-model="deadlineTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                        </div>
                        @error('cancellationDeadline') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- 5. Standaard- en annuleringskosten --}}
            @include('livewire.admin.partials.activity-cost-products', ['prefix' => 'a'])

            {{-- 6. Zichtbaarheid en publicatie --}}
            <div class="border-t border-gray-200 pt-4 space-y-3">
                <h3 class="font-medium text-gray-900 text-sm">Zichtbaarheid en publicatie</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <x-input-label for="a-visibility" value="Zichtbaarheid" />
                        <select id="a-visibility" wire:model="visibility"
                            class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                            @foreach ($visibilities as $vis)
                                <option value="{{ $vis->value }}">{{ $vis->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="a-status" value="Status" />
                        <select id="a-status" wire:model="status"
                            class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                            @foreach ($statuses as $stat)
                                <option value="{{ $stat->value }}">{{ $stat->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="contents" x-data="{
                        fromDate: @js($publishFrom !== '' ? substr($publishFrom, 0, 10) : ''),
                        fromTime: @js($publishFrom !== '' ? substr($publishFrom, 11, 5) : ''),
                        untilDate: @js($publishUntil !== '' ? substr($publishUntil, 0, 10) : ''),
                        untilTime: @js($publishUntil !== '' ? substr($publishUntil, 11, 5) : ''),
                        syncFrom() { $wire.set('publishFrom', this.fromDate ? this.fromDate + 'T' + (this.fromTime || '00:00') : ''); },
                        syncUntil() { $wire.set('publishUntil', this.untilDate ? this.untilDate + 'T' + (this.untilTime || '23:59') : ''); },
                    }" x-init="
                        $watch('fromDate', () => syncFrom());
                        $watch('fromTime', () => syncFrom());
                        $watch('untilDate', () => syncUntil());
                        $watch('untilTime', () => syncUntil());
                    ">
                        <div>
                            <x-input-label for="a-publish-from" value="Gepubliceerd vanaf (optioneel)" />
                            <div class="flex items-center gap-2 mt-1">
                                <input id="a-publish-from" type="date" x-model="fromDate" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                                <input type="time" x-model="fromTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="a-publish-until" value="Gepubliceerd tot en met (optioneel)" />
                            <div class="flex items-center gap-2 mt-1">
                                <input id="a-publish-until" type="date" x-model="untilDate" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                                <input type="time" x-model="untilTime" class="border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
                            </div>
                            @error('publishUntil') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between border-t border-gray-200 pt-4">
                <button type="button" wire:click="cancelForm" class="text-sm px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Annuleren</button>
                <button type="button" wire:click="saveActivity"
                    class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                    Opslaan
                </button>
            </div>
        </section>
    @endif

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Wanneer</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Categorie</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Inschrijvingen</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($activities as $activity)
                    <tr>
                        <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                            {{ $activity->starts_at->format('d-m-Y H:i') }}
                        </td>
                        <td class="px-4 py-2">
                            <a href="{{ route('activiteit.show', $activity) }}" class="font-medium text-rzvg-600 hover:text-rzvg-800">
                                {{ $activity->title }}
                            </a>
                            @if ($activity->location)
                                <div class="text-xs text-gray-500">{{ $activity->location }}</div>
                            @endif
                            @if ($activity->activityPage)
                                <div class="text-xs text-gray-500">Event: {{ $activity->activityPage->page->title }}</div>
                            @endif
                            @if ($activity->series)
                                <div class="text-xs text-gray-500">
                                    Groep:
                                    <button type="button" wire:click="editGroup({{ $activity->series_id }})" class="underline hover:text-rzvg-700">{{ $activity->series->title }}</button>
                                    @if ($activity->is_exception)
                                        <span class="text-amber-700">(uitzondering)</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $activity->category->name }}</td>
                        <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                            {{ $activity->enrolledCount() }}@if ($activity->capacity) / {{ $activity->capacity }}@endif
                        </td>
                        <td class="px-4 py-2">
                            @php $badge = ['concept' => 'yellow', 'gepubliceerd' => 'green', 'afgelast' => 'red'][$activity->status->value] ?? 'gray'; @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border
                                bg-{{ $badge }}-50 text-{{ $badge }}-700 border-{{ $badge }}-200">
                                {{ $activity->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            <button type="button" wire:click="editActivity({{ $activity->id }})" class="text-rzvg-600 hover:text-rzvg-800">Wijzigen</button>
                            <button type="button" wire:click="toggleManagers({{ $activity->id }})" class="text-rzvg-600 hover:text-rzvg-800">
                                Beheerders ({{ $activity->managers->count() + $activity->managerGroups->count() }})
                            </button>
                            <button type="button" wire:click="toggleFiles({{ $activity->id }})" class="text-rzvg-600 hover:text-rzvg-800">
                                Bestanden ({{ $activity->files->count() }})
                            </button>
                            <button type="button" wire:click="toggleRegistrationFields({{ $activity->id }})" class="text-rzvg-600 hover:text-rzvg-800">
                                Velden ({{ $activity->registrationFields->count() }})
                            </button>
                            @if ($activity->status !== \App\Enums\ActivityStatus::Cancelled)
                                <button type="button" wire:click="cancelActivity({{ $activity->id }})"
                                    onclick="return confirm('Activiteit afgelasten?');"
                                    class="text-amber-700 hover:text-amber-900">Afgelasten</button>
                            @endif
                            <button type="button" wire:click="deleteActivity({{ $activity->id }})"
                                onclick="return confirm('Activiteit definitief verwijderen? Inschrijvingen worden ook gewist.');"
                                class="text-red-600 hover:text-red-800">Verwijderen</button>
                        </td>
                    </tr>
                    @if ($expandedManagersId === $activity->id)
                        <tr wire:key="managers-{{ $activity->id }}">
                            <td colspan="6" class="px-4 py-3 bg-gray-50 border-t border-gray-100">
                                <div class="text-xs font-semibold text-gray-500 uppercase mb-2">
                                    Gedelegeerde beheerders — zien inschrijvingen en mogen wijzigen zonder het globale activiteitenrecht
                                </div>
                                <div class="flex flex-wrap gap-1 mb-2">
                                    @forelse ($activity->managers as $manager)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-xs">
                                            {{ $manager->first_name }} {{ $manager->last_name }}
                                            <label class="inline-flex items-center gap-1 ml-1" title="Mailnotificatie bij wijzigingen/in- en uitschrijvingen">
                                                <input type="checkbox" wire:click="toggleManagerNotify({{ $activity->id }}, {{ $manager->id }})"
                                                    @checked($manager->pivot->notify) class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600 h-3 w-3" />
                                                <span class="text-gray-500">mail</span>
                                            </label>
                                            <button type="button" wire:click="removeManager({{ $activity->id }}, {{ $manager->id }})"
                                                class="text-red-600 hover:text-red-800" title="Verwijderen">×</button>
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400 italic">Nog geen gedelegeerde beheerders.</span>
                                    @endforelse
                                </div>
                                <div class="flex items-center gap-2 mb-2">
                                    <select wire:model="addManagerPersonId" class="border-gray-300 rounded shadow-sm text-xs">
                                        <option value="">— Kies persoon —</option>
                                        @foreach ($personsForAssignment as $p)
                                            <option value="{{ $p->id }}">{{ $p->first_name }} {{ $p->last_name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="addManager({{ $activity->id }})"
                                        class="text-xs px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-50">Toevoegen</button>
                                </div>

                                <div class="flex flex-wrap gap-1 mb-2">
                                    @foreach ($activity->managerGroups as $group)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-xs">
                                            {{ $group->name }} (groep)
                                            <label class="inline-flex items-center gap-1 ml-1" title="Mailnotificatie bij wijzigingen/in- en uitschrijvingen">
                                                <input type="checkbox" wire:click="toggleManagerGroupNotify({{ $activity->id }}, {{ $group->id }})"
                                                    @checked($group->pivot->notify) class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600 h-3 w-3" />
                                                <span class="text-gray-500">mail</span>
                                            </label>
                                            <button type="button" wire:click="removeManagerGroup({{ $activity->id }}, {{ $group->id }})"
                                                class="text-red-600 hover:text-red-800" title="Verwijderen">×</button>
                                        </span>
                                    @endforeach
                                </div>
                                <div class="flex items-center gap-2">
                                    <select wire:model="addManagerGroupId" class="border-gray-300 rounded shadow-sm text-xs">
                                        <option value="">— Kies groep —</option>
                                        @foreach ($groupsForAssignment as $g)
                                            <option value="{{ $g->id }}">{{ $g->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="addManagerGroup({{ $activity->id }})"
                                        class="text-xs px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-50">Toevoegen</button>
                                </div>
                            </td>
                        </tr>
                    @endif
                    @if ($expandedFilesId === $activity->id)
                        <tr wire:key="files-{{ $activity->id }}">
                            <td colspan="6" class="px-4 py-3 bg-gray-50 border-t border-gray-100">
                                <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Bestanden</div>
                                <ul class="divide-y divide-gray-100 text-sm mb-2">
                                    @forelse ($activity->files as $file)
                                        <li class="flex items-center justify-between py-1">
                                            <a href="{{ $file->displayUrl() }}" target="_blank" class="text-rzvg-600 hover:text-rzvg-800 underline">{{ $file->original_name }}</a>
                                            <button type="button" wire:click="removeFile({{ $activity->id }}, {{ $file->id }})"
                                                onclick="return confirm('Bestand verwijderen?');"
                                                class="text-red-600 hover:text-red-800 text-xs">Verwijderen</button>
                                        </li>
                                    @empty
                                        <li class="text-xs text-gray-400 italic py-1">Nog geen bestanden.</li>
                                    @endforelse
                                </ul>
                                <div class="flex items-center gap-2">
                                    <input type="file" wire:model="newFiles" multiple class="text-xs" />
                                    <button type="button" wire:click="uploadFiles({{ $activity->id }})"
                                        class="text-xs px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-50">Uploaden</button>
                                </div>
                                @error('newFiles.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @endif
                    @if ($expandedFieldsId === $activity->id)
                        <tr wire:key="fields-{{ $activity->id }}">
                            <td colspan="6" class="px-4 py-3 bg-gray-50 border-t border-gray-100">
                                <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Extra inschrijfvelden</div>
                                @include('livewire.admin.partials.activity-registration-fields', ['mode' => 'existing', 'activityId' => $activity->id, 'existingFields' => $activity->registrationFields])
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                            Geen activiteiten gevonden met de huidige filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
