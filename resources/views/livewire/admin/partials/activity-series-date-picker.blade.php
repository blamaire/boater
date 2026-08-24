<div class="space-y-4">
    <div>
        <h3 class="text-sm font-medium text-gray-900 mb-2">Datum toevoegen</h3>
        <div class="flex flex-wrap gap-2">
            @foreach (['specific' => 'Specifieke datum', 'weekly' => 'Wekelijks', 'monthly' => 'Maandelijks', 'quarterly' => 'Per kwartaal'] as $value => $label)
                <button type="button" wire:click="selectGenMode('{{ $value }}')"
                    @class([
                        'px-3 py-1.5 rounded-full border text-xs font-medium',
                        'border-rzvg-600 bg-rzvg-50 text-rzvg-700' => $genMode === $value,
                        'border-gray-300 text-gray-600 hover:border-gray-400' => $genMode !== $value,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($genMode === 'specific')
        <div class="space-y-4 border border-gray-200 rounded-md p-3">
            <div>
                <x-input-label value="Tijdstip" />
                <div class="flex items-center gap-2 mt-1">
                    <input type="time" wire:model="manualStartTime" class="border-gray-300 rounded shadow-sm text-sm" />
                    <span class="text-xs text-gray-500">tot</span>
                    <input type="time" wire:model="manualEndTime" class="border-gray-300 rounded shadow-sm text-sm" />
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-8">
                <div>
                    <x-input-label for="manual-date" value="Eén datum intypen" />
                    <div class="flex items-center gap-2 mt-1">
                        <input type="date" id="manual-date" wire:model="manualDate" class="border-gray-300 rounded shadow-sm text-sm" />
                        <button type="button" wire:click="addManualDate" class="text-xs px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                            Toevoegen
                        </button>
                    </div>
                    @error('manualDate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="sm:border-l sm:border-gray-200 sm:pl-8" x-data="{
                    viewYear: new Date().getFullYear(),
                    viewMonth: new Date().getMonth(),
                    selected: [],
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
                    toggle(date) {
                        const idx = this.selected.indexOf(date);
                        if (idx === -1) { this.selected.push(date); } else { this.selected.splice(idx, 1); }
                    },
                }">
                    <x-input-label value="Of kies op de kalender" />
                    <div class="max-w-xs mt-1">
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
                                    @click="toggle(cell)"
                                    x-text="cell ? cell.split('-')[2].replace(/^0/, '') : ''"
                                    :class="cell && selected.includes(cell) ? 'bg-rzvg-600 text-white' : 'text-gray-700 hover:bg-gray-100'"
                                    class="h-8 w-8 mx-auto rounded text-xs flex items-center justify-center">
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 mt-2 text-xs">
                        <span class="text-gray-600" x-show="selected.length > 0" x-text="selected.length + ' datum' + (selected.length === 1 ? '' : 's') + ' geselecteerd'"></span>
                        <button type="button" x-show="selected.length > 0" @click="selected = []" class="text-gray-500 hover:text-gray-800 underline">
                            Selectie wissen
                        </button>
                    </div>
                    <button type="button" x-show="selected.length > 0"
                        @click="$wire.addManualDates([...selected].sort()); selected = []"
                        class="mt-2 text-xs px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                        Geselecteerde datums toevoegen
                    </button>
                </div>
            </div>
        </div>
    @else
        <div class="space-y-4 border border-gray-200 rounded-md p-3">
            @if ($genMode === 'weekly')
                <div class="max-w-xs">
                    <x-input-label for="gen-weekday" value="Dag van de week" />
                    <select id="gen-weekday" wire:model="genWeekday" class="w-full border-gray-300 rounded shadow-sm text-sm">
                        @foreach ($weekdays as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div>
                    <x-input-label value="Welke dag van de maand" />
                    <div class="flex flex-wrap gap-2 mt-1">
                        <button type="button" wire:click="selectMonthlyDayMode('fixed')"
                            @class([
                                'px-3 py-1.5 rounded-full border text-xs font-medium',
                                'border-rzvg-600 bg-rzvg-50 text-rzvg-700' => $genMonthlyDayMode === 'fixed',
                                'border-gray-300 text-gray-600 hover:border-gray-400' => $genMonthlyDayMode !== 'fixed',
                            ])>Vaste datum van de maand</button>
                        <button type="button" wire:click="selectMonthlyDayMode('weekday')"
                            @class([
                                'px-3 py-1.5 rounded-full border text-xs font-medium',
                                'border-rzvg-600 bg-rzvg-50 text-rzvg-700' => $genMonthlyDayMode === 'weekday',
                                'border-gray-300 text-gray-600 hover:border-gray-400' => $genMonthlyDayMode !== 'weekday',
                            ])>Weekdag</button>
                    </div>
                </div>
                @if ($genMonthlyDayMode === 'fixed')
                    <div class="max-w-[10rem]">
                        <x-input-label for="gen-day-of-month" value="Dag (1-31)" />
                        <input type="number" id="gen-day-of-month" min="1" max="31" wire:model="genDayOfMonth" class="w-full border-gray-300 rounded shadow-sm text-sm" />
                        @error('genDayOfMonth') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-2 max-w-sm">
                        <div>
                            <x-input-label for="gen-ordinal" value="Welke" />
                            <select id="gen-ordinal" wire:model="genOrdinal" class="w-full border-gray-300 rounded shadow-sm text-sm">
                                <option value="1">Eerste</option>
                                <option value="2">Tweede</option>
                                <option value="3">Derde</option>
                                <option value="4">Vierde</option>
                                <option value="-2">Voorlaatste</option>
                                <option value="-1">Laatste</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="gen-weekday-ordinal" value="Weekdag" />
                            <select id="gen-weekday-ordinal" wire:model="genWeekday" class="w-full border-gray-300 rounded shadow-sm text-sm">
                                @foreach ($weekdays as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
            @endif

            <div>
                <x-input-label value="Tijdstip" />
                <div class="flex items-center gap-2 mt-1">
                    <input type="time" wire:model="genStartTime" class="border-gray-300 rounded shadow-sm text-sm" />
                    <span class="text-xs text-gray-500">tot</span>
                    <input type="time" wire:model="genEndTime" class="border-gray-300 rounded shadow-sm text-sm" />
                </div>
            </div>

            <div class="flex flex-wrap items-start gap-4">
                <div>
                    <x-input-label for="gen-start-date" value="Startdatum" />
                    <input type="date" id="gen-start-date" wire:model="genStartDate" class="mt-1 max-w-[10rem] border-gray-300 rounded shadow-sm text-sm" />
                    @error('genStartDate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-input-label value="Einde van de reeks" />
                    <div class="mt-1 space-y-2">
                        <label class="flex items-center gap-2">
                            <input type="radio" wire:click="selectGenBoundMode('until')" @checked($genBoundMode === 'until') class="text-rzvg-600 focus:ring-rzvg-600" />
                            <span class="text-gray-700 w-24 shrink-0">Tot en met</span>
                            <input type="date" wire:model="genEndDate" wire:click="selectGenBoundMode('until')" aria-label="Laatste datum"
                                @class(['max-w-[10rem] rounded shadow-sm text-sm', 'border-gray-300' => $genBoundMode === 'until', 'border-gray-200 bg-gray-100 text-gray-400' => $genBoundMode !== 'until']) />
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" wire:click="selectGenBoundMode('count')" @checked($genBoundMode === 'count') class="text-rzvg-600 focus:ring-rzvg-600" />
                            <span class="text-gray-700 w-24 shrink-0">Aantal keer</span>
                            <input type="number" min="1" wire:model="genCount" wire:click="selectGenBoundMode('count')" aria-label="Aantal keer" placeholder="Aantal keer"
                                @class(['max-w-[10rem] rounded shadow-sm text-sm', 'border-gray-300' => $genBoundMode === 'count', 'border-gray-200 bg-gray-100 text-gray-400' => $genBoundMode !== 'count']) />
                        </label>
                    </div>
                    @error('genEndDate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    @error('genCount') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="button" wire:click="generateDates" class="text-xs px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                Data genereren
            </button>
        </div>
    @endif

    @error('pendingDates') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

    @if (count($pendingDates) > 0)
        <div class="space-y-1">
            <h3 class="text-sm font-medium text-gray-900">Datums ({{ count($pendingDates) }})</h3>
            <ul class="divide-y divide-gray-100 text-sm border border-gray-200 rounded-md">
                @foreach ($pendingDates as $i => $date)
                    @php($start = \Illuminate\Support\Carbon::parse($date['starts_at']))
                    <li class="flex items-center justify-between px-3 py-1.5" wire:key="pending-{{ $i }}">
                        <span>
                            <span class="text-gray-500">{{ \Illuminate\Support\Str::ucfirst($start->translatedFormat('l')) }}</span>
                            {{ $start->format('d-m-Y H:i') }}
                            @if ($date['ends_at'])
                                – {{ \Illuminate\Support\Carbon::parse($date['ends_at'])->format('H:i') }}
                            @endif
                        </span>
                        <button type="button" wire:click="removePendingDate({{ $i }})" class="text-red-600 hover:text-red-800 text-xs">
                            Verwijderen
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
