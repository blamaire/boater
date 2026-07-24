<div class="space-y-6">
    @if ($saveError)
        <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ $saveError }}</div>
    @endif

    <section class="bg-white border border-gray-200 rounded-lg p-4 text-sm text-gray-700 space-y-1">
        <p>
            Jouw concept (v{{ $mine->version_no }}) vertakt vanaf
            @if ($base)
                v{{ $base->version_no }}
            @else
                een niet-vindbare basisversie
            @endif
            , maar de gepubliceerde versie is nu v{{ $theirs->version_no }}.
            Los per conflict-blok hieronder op wat er moet gebeuren; blokken zonder conflict worden automatisch samengevoegd.
        </p>
    </section>

    <div class="grid gap-3 md:grid-cols-3 text-sm">
        <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
            <div class="text-xs uppercase text-gray-500 font-semibold">Basis</div>
            @if ($base)
                <p class="mt-1">v{{ $base->version_no }} — {{ $base->createdBy?->fullName() ?: 'Systeem' }}</p>
            @else
                <p class="mt-1 text-gray-400 italic">niet vindbaar</p>
            @endif
        </div>
        <div class="border border-gray-200 rounded-lg p-3 bg-white">
            <div class="text-xs uppercase text-gray-500 font-semibold">Jouw versie</div>
            <p class="mt-1">v{{ $mine->version_no }} — {{ $mine->createdBy?->fullName() ?: 'Systeem' }}</p>
        </div>
        <div class="border border-gray-200 rounded-lg p-3 bg-white">
            <div class="text-xs uppercase text-gray-500 font-semibold">Gepubliceerde versie</div>
            <p class="mt-1">v{{ $theirs->version_no }} — {{ $theirs->createdBy?->fullName() ?: 'Systeem' }}</p>
        </div>
    </div>

    @php
        $conflicts = $report->conflicts();
        $autoMerges = $report->autoMerges();
    @endphp

    @if ($conflicts->isEmpty())
        <section class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-800">
            Geen echte conflicten — alle wijzigingen kunnen automatisch worden samengevoegd. Klik "Resolutie opslaan" om een nieuwe conceptversie aan te maken.
        </section>
    @else
        <div class="space-y-4">
            @foreach ($conflicts as $diff)
                <div class="bg-white border border-red-200 rounded-lg p-4 space-y-3" wire:key="conflict-{{ $diff->originBlockId }}">
                    <header class="flex items-center justify-between">
                        <div>
                            <h3 class="font-medium">Conflict op blok #{{ $diff->originBlockId }}
                                @if ($diff->mine)
                                    <span class="text-xs text-gray-500">({{ $diff->mine->type->label() }})</span>
                                @endif
                            </h3>
                            <p class="text-xs text-gray-500">
                                Type: {{ $diff->label() }}
                                @if ($diff->conflictingKeys)
                                    · botsende velden: {{ implode(', ', $diff->conflictingKeys) }}
                                @endif
                            </p>
                        </div>
                    </header>

                    @php
                        $currentChoice = $choices[$diff->originBlockId] ?? null;
                    @endphp

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="border border-gray-200 rounded p-3 bg-gray-50 space-y-2">
                            <div class="text-xs uppercase text-gray-500 font-semibold">Basis</div>
                            @if ($diff->base)
                                @include('cms.blocks.preview', ['block' => $diff->base, 'fullBleed' => false])
                            @else
                                <p class="text-xs text-gray-400 italic">— bestond niet —</p>
                            @endif
                        </div>

                        <div @class([
                            'border rounded p-3 space-y-2',
                            'border-rzvg-500 bg-rzvg-50' => $currentChoice === 'mine',
                            'border-red-400 bg-white' => $currentChoice === null,
                            'border-gray-200 bg-white' => $currentChoice !== null && $currentChoice !== 'mine',
                        ])>
                            <label class="flex items-center gap-2 text-xs uppercase text-gray-700 font-semibold">
                                <input type="radio" wire:model.live="choices.{{ $diff->originBlockId }}" value="mine"
                                    @class(['border border-rzvg-500' => $currentChoice === null])>
                                Jouw versie
                            </label>
                            @if ($diff->mine)
                                @include('cms.blocks.preview', ['block' => $diff->mine, 'fullBleed' => false])
                            @else
                                <p class="text-xs text-gray-400 italic">— je hebt dit blok verwijderd —</p>
                            @endif
                        </div>

                        <div @class([
                            'border rounded p-3 space-y-2',
                            'border-rzvg-500 bg-rzvg-50' => $currentChoice === 'theirs',
                            'border-red-400 bg-white' => $currentChoice === null,
                            'border-gray-200 bg-white' => $currentChoice !== null && $currentChoice !== 'theirs',
                        ])>
                            <label class="flex items-center gap-2 text-xs uppercase text-gray-700 font-semibold">
                                <input type="radio" wire:model.live="choices.{{ $diff->originBlockId }}" value="theirs"
                                    @class(['border border-rzvg-500' => $currentChoice === null])>
                                Gepubliceerde versie
                            </label>
                            @if ($diff->theirs)
                                @include('cms.blocks.preview', ['block' => $diff->theirs, 'fullBleed' => false])
                            @else
                                <p class="text-xs text-gray-400 italic">— dit blok is verwijderd —</p>
                            @endif
                        </div>
                    </div>

                    @php
                        // null (niet ''): een blok dat niet bestaat aan die kant heeft
                        // GEEN regels om te vergelijken — anders wordt het als "één lege
                        // regel" gezien, die dan onterecht overneembaar lijkt.
                        $baseJsonPretty = $diff->base ? json_encode($diff->base->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;
                        $mineJsonPretty = $diff->mine ? json_encode($diff->mine->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '';
                        $theirsJsonPretty = $diff->theirs ? json_encode($diff->theirs->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;

                        $differ = app(\App\Services\Cms\TextDiffer::class);
                        $baseVsMineRows = $baseJsonPretty !== null
                            ? array_values(array_filter(
                                $differ->diffLines($baseJsonPretty, $mineJsonPretty),
                                fn ($row) => $row['right'] !== null
                            ))
                            : array_map(fn ($line) => ['type' => 'added', 'left' => null, 'right' => $line], explode("\n", $mineJsonPretty));
                        $mineVsTheirsRows = $theirsJsonPretty !== null
                            ? array_values(array_filter(
                                $differ->diffLines($mineJsonPretty, $theirsJsonPretty),
                                fn ($row) => $row['left'] !== null
                            ))
                            : [];

                        $mergeRows = [];
                        foreach ($baseVsMineRows as $i => $row) {
                            $theirsRow = $mineVsTheirsRows[$i] ?? null;
                            $mergeRows[] = [
                                'mine' => $row['right'],
                                'base' => $row['left'],
                                'baseDiffers' => $row['type'] !== 'same',
                                'theirs' => $theirsRow['right'] ?? null,
                                'theirsDiffers' => $theirsRow !== null && $theirsRow['type'] !== 'same',
                            ];
                        }
                        $mineLineTexts = array_column($mergeRows, 'mine');

                        // Groepeer aaneengesloten afwijkende regels tot één chunk (één
                        // pijltje/kruisje voor het hele blok), i.p.v. per regel apart.
                        $buildSegments = function (array $mergeRows, string $differsKey, string $valueKey): array {
                            $segments = [];
                            $chunk = null;
                            foreach ($mergeRows as $i => $row) {
                                $differs = $row[$differsKey] && $row[$valueKey] !== null;
                                if ($differs) {
                                    $chunk ??= ['type' => 'chunk', 'indexes' => [], 'texts' => []];
                                    $chunk['indexes'][] = $i;
                                    $chunk['texts'][] = $row[$valueKey];

                                    continue;
                                }
                                if ($chunk !== null) {
                                    $segments[] = $chunk;
                                    $chunk = null;
                                }
                                $segments[] = ['type' => 'same', 'text' => $row[$valueKey] ?? '—'];
                            }
                            if ($chunk !== null) {
                                $segments[] = $chunk;
                            }

                            return $segments;
                        };

                        $baseSegments = $buildSegments($mergeRows, 'baseDiffers', 'base');
                        $theirsSegments = $buildSegments($mergeRows, 'theirsDiffers', 'theirs');
                    @endphp

                    <details class="border border-gray-200 rounded p-2" x-data="{
                        lines: {{ \Illuminate\Support\Js::from($mineLineTexts) }},
                        original: {{ \Illuminate\Support\Js::from($mineLineTexts) }},
                        dismissed: {},
                        manualSelected: {{ $currentChoice === 'manual' ? 'true' : 'false' }},
                        markManual() {
                            if (! this.manualSelected) {
                                this.manualSelected = true;
                                $wire.set('choices.{{ $diff->originBlockId }}', 'manual');
                            }
                        },
                        setLine(i, value) {
                            this.lines[i] = value;
                            const el = this.$refs['line' + i];
                            if (el) { el.innerText = value; }
                        },
                        acceptChunk(indexes, texts, key) {
                            indexes.forEach((idx, k) => this.setLine(idx, texts[k]));
                            this.dismissed[key] = true;
                            this.markManual();
                            this.sync();
                        },
                        revert(i) {
                            this.setLine(i, this.original[i]);
                            this.markManual();
                            this.sync();
                        },
                        editLine(i, event) {
                            this.lines[i] = event.target.innerText;
                            this.markManual();
                            this.sync();
                        },
                        dismiss(key) { this.dismissed[key] = true; },
                        useWhole(json) {
                            this.lines = json.split('\n');
                            this.lines.forEach((value, idx) => this.setLine(idx, value));
                            this.markManual();
                            this.sync();
                        },
                        sync() {
                            $refs.manualJson.value = this.lines.join('\n');
                            $refs.manualJson.dispatchEvent(new Event('input'));
                        },
                        init() { this.sync(); },
                    }">
                        <summary class="text-xs cursor-pointer text-gray-600">Handmatig samenvoegen (JSON)</summary>
                        <div class="pt-2 space-y-3">
                            <label class="flex items-center gap-2 text-xs">
                                <input type="radio" wire:model.live="choices.{{ $diff->originBlockId }}" value="manual">
                                Gebruik handmatige JSON
                            </label>

                            <p class="text-xs text-gray-500">"Jouw versie" is het uitgangspunt en is direct te bewerken. Bij een afwijkend blok in Basis of Gepubliceerd: → neemt het over, ✕ wijst het af; "Alles" vervangt de hele kolom. Is een regel gewijzigd, dan kun je die met ↺ terugdraaien.</p>

                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <div class="border border-gray-200 rounded overflow-hidden">
                                    <div class="flex items-center justify-between bg-gray-50 border-b border-gray-200 px-2 py-1">
                                        <span class="font-semibold text-gray-600">Basis</span>
                                        @if ($baseJsonPretty !== null)
                                            <button type="button" @click="useWhole({{ \Illuminate\Support\Js::from($baseJsonPretty) }})" class="text-gray-500 hover:text-rzvg-600">Alles</button>
                                        @endif
                                    </div>
                                    @if ($baseJsonPretty === null)
                                        <p class="p-2 text-gray-400 italic">— bestond niet —</p>
                                    @else
                                    <div class="font-mono overflow-auto max-h-56">
                                        @foreach ($baseSegments as $s => $seg)
                                            @if ($seg['type'] === 'same')
                                                <div class="px-2 py-0.5"><span class="whitespace-pre">{{ $seg['text'] }}</span></div>
                                            @else
                                                @php
                                                    $chunkKey = 'base-'.$s;
                                                @endphp
                                                <div class="px-2 py-0.5 bg-yellow-50 flex items-start gap-1">
                                                    <div class="flex-1">
                                                        @foreach ($seg['texts'] as $t)
                                                            <div class="whitespace-pre">{{ $t }}</div>
                                                        @endforeach
                                                    </div>
                                                    <div class="flex flex-col shrink-0" x-show="!dismissed['{{ $chunkKey }}']">
                                                        <button type="button" title="Overnemen in jouw versie" @click="acceptChunk({{ \Illuminate\Support\Js::from($seg['indexes']) }}, {{ \Illuminate\Support\Js::from($seg['texts']) }}, '{{ $chunkKey }}')" class="text-green-600 hover:text-green-800">→</button>
                                                        <button type="button" title="Afwijzen" @click="dismiss('{{ $chunkKey }}')" class="text-gray-400 hover:text-red-600">✕</button>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                    @endif
                                </div>

                                <div class="border border-rzvg-300 rounded overflow-hidden">
                                    <div class="bg-rzvg-50 border-b border-rzvg-200 px-2 py-1">
                                        <span class="font-semibold text-gray-600">Jouw versie (wordt bewerkt)</span>
                                    </div>
                                    <div class="font-mono overflow-auto max-h-56">
                                        @foreach ($mergeRows as $i => $row)
                                            <div class="px-2 py-0.5 flex items-start gap-1 {{ ($row['baseDiffers'] || $row['theirsDiffers']) ? 'bg-yellow-50' : '' }}">
                                                <span class="whitespace-pre flex-1 outline-none focus:bg-white" contenteditable="true" spellcheck="false"
                                                    x-ref="line{{ $i }}" @input="editLine({{ $i }}, $event)">{{ $row['mine'] }}</span>
                                                <button type="button" title="Wijziging terugdraaien" x-show="lines[{{ $i }}] !== {{ \Illuminate\Support\Js::from($row['mine']) }}"
                                                    @click="revert({{ $i }})" class="shrink-0 text-gray-400 hover:text-rzvg-600">↺</button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="border border-gray-200 rounded overflow-hidden">
                                    <div class="flex items-center justify-between bg-white border-b border-gray-200 px-2 py-1">
                                        <span class="font-semibold text-gray-600">Gepubliceerde versie</span>
                                        @if ($theirsJsonPretty !== null)
                                            <button type="button" @click="useWhole({{ \Illuminate\Support\Js::from($theirsJsonPretty) }})" class="text-gray-500 hover:text-rzvg-600">Alles</button>
                                        @endif
                                    </div>
                                    @if ($theirsJsonPretty === null)
                                        <div class="p-2 flex items-center gap-1">
                                            <button type="button" title="Verwijdering afwijzen (jouw versie blijft staan)" wire:click="$set('choices.{{ $diff->originBlockId }}', 'mine')" class="text-gray-400 hover:text-red-600">✕</button>
                                            <button type="button" title="Verwijdering overnemen (blok komt te vervallen)" wire:click="$set('choices.{{ $diff->originBlockId }}', 'theirs')" class="text-green-600 hover:text-green-800">←</button>
                                            <span class="text-gray-400 italic">— dit blok is verwijderd —</span>
                                        </div>
                                    @else
                                    <div class="font-mono overflow-auto max-h-56">
                                        @foreach ($theirsSegments as $s => $seg)
                                            @if ($seg['type'] === 'same')
                                                <div class="px-2 py-0.5"><span class="whitespace-pre">{{ $seg['text'] }}</span></div>
                                            @else
                                                @php
                                                    $chunkKey = 'theirs-'.$s;
                                                @endphp
                                                <div class="px-2 py-0.5 bg-yellow-50 flex items-start gap-1">
                                                    <div class="flex flex-col shrink-0" x-show="!dismissed['{{ $chunkKey }}']">
                                                        <button type="button" title="Afwijzen" @click="dismiss('{{ $chunkKey }}')" class="text-gray-400 hover:text-red-600">✕</button>
                                                        <button type="button" title="Overnemen in jouw versie" @click="acceptChunk({{ \Illuminate\Support\Js::from($seg['indexes']) }}, {{ \Illuminate\Support\Js::from($seg['texts']) }}, '{{ $chunkKey }}')" class="text-green-600 hover:text-green-800">←</button>
                                                    </div>
                                                    <div class="flex-1">
                                                        @foreach ($seg['texts'] as $t)
                                                            <div class="whitespace-pre">{{ $t }}</div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Technisch nodig voor de wire:model-koppeling; "Jouw versie" hierboven is het echte invoerveld. --}}
                            <textarea wire:model="manualJson.{{ $diff->originBlockId }}" x-ref="manualJson" class="hidden" aria-hidden="true" tabindex="-1"></textarea>
                        </div>
                    </details>
                </div>
            @endforeach
        </div>
    @endif

    @if ($autoMerges->isNotEmpty())
        <section class="space-y-2">
            <h3 class="font-medium text-sm text-gray-700">Automatisch samengevoegd ({{ $autoMerges->count() }} blok(ken))</h3>

            @foreach ($autoMerges as $diff)
                @php
                    $winner = match ($diff->type) {
                        'added_by_me', 'edited_by_me' => $diff->mine,
                        'added_by_theirs', 'edited_by_theirs' => $diff->theirs,
                        default => $diff->mine ?? $diff->theirs,
                    };
                @endphp
                <details class="group bg-white border border-gray-200 rounded-lg p-4" wire:key="automerge-{{ $diff->originBlockId }}">
                    <summary class="flex items-center justify-between text-sm cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
                        <span class="flex items-center gap-2">
                            <svg width="16" height="16" class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open:rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                            <span class="font-medium">Blok #{{ $diff->originBlockId }}</span>
                        </span>
                        <span class="text-xs text-gray-500">{{ $diff->label('jouw versie', 'de gepubliceerde versie') }}</span>
                    </summary>

                    <div class="mt-2 border border-gray-200 rounded p-2 overflow-hidden">
                        @if ($winner)
                            @include('cms.blocks.preview', ['block' => $winner, 'fullBleed' => false])
                        @else
                            <p class="text-xs text-gray-400 italic">— geen inhoud —</p>
                        @endif
                    </div>
                </details>
            @endforeach
        </section>
    @endif

    <div class="flex items-center gap-3">
        <button wire:click="resolve" wire:loading.attr="disabled" class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 disabled:opacity-50">
            Resolutie opslaan als nieuwe conceptversie
        </button>
        <a href="{{ route('admin.pages.editor', $mine->page) }}" class="text-sm text-gray-600 hover:text-gray-800">Annuleren</a>
    </div>
</div>
