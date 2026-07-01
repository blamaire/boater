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

    @php($conflicts = $report->conflicts())
    @php($autoMerges = $report->autoMerges())

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
                                Type: {{ $diff->type }}
                                @if ($diff->conflictingKeys)
                                    · botsende velden: {{ implode(', ', $diff->conflictingKeys) }}
                                @endif
                            </p>
                        </div>
                    </header>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="border border-gray-200 rounded p-3 bg-gray-50 space-y-2">
                            <div class="text-xs uppercase text-gray-500 font-semibold">Basis</div>
                            @if ($diff->base)
                                @include('cms.blocks.preview', ['block' => $diff->base])
                                <details class="text-xs text-gray-500 pt-2 border-t">
                                    <summary class="cursor-pointer">Ruwe JSON</summary>
                                    <pre class="mt-1 whitespace-pre-wrap break-all bg-white border border-gray-200 rounded p-2">{{ json_encode($diff->base->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @else
                                <p class="text-xs text-gray-400 italic">— bestond niet —</p>
                            @endif
                        </div>

                        <div @class([
                            'border rounded p-3 space-y-2',
                            'border-rzvg-500 bg-rzvg-50' => ($choices[$diff->originBlockId] ?? '') === 'mine',
                            'border-gray-200 bg-white' => ($choices[$diff->originBlockId] ?? '') !== 'mine',
                        ])>
                            <label class="flex items-center gap-2 text-xs uppercase text-gray-700 font-semibold">
                                <input type="radio" wire:model.live="choices.{{ $diff->originBlockId }}" value="mine">
                                Jouw versie
                            </label>
                            @if ($diff->mine)
                                @include('cms.blocks.preview', ['block' => $diff->mine])
                                <details class="text-xs text-gray-500 pt-2 border-t">
                                    <summary class="cursor-pointer">Ruwe JSON</summary>
                                    <pre class="mt-1 whitespace-pre-wrap break-all bg-white border border-gray-200 rounded p-2">{{ json_encode($diff->mine->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @else
                                <p class="text-xs text-gray-400 italic">— je hebt dit blok verwijderd —</p>
                            @endif
                        </div>

                        <div @class([
                            'border rounded p-3 space-y-2',
                            'border-rzvg-500 bg-rzvg-50' => ($choices[$diff->originBlockId] ?? '') === 'theirs',
                            'border-gray-200 bg-white' => ($choices[$diff->originBlockId] ?? '') !== 'theirs',
                        ])>
                            <label class="flex items-center gap-2 text-xs uppercase text-gray-700 font-semibold">
                                <input type="radio" wire:model.live="choices.{{ $diff->originBlockId }}" value="theirs">
                                Gepubliceerde versie
                            </label>
                            @if ($diff->theirs)
                                @include('cms.blocks.preview', ['block' => $diff->theirs])
                                <details class="text-xs text-gray-500 pt-2 border-t">
                                    <summary class="cursor-pointer">Ruwe JSON</summary>
                                    <pre class="mt-1 whitespace-pre-wrap break-all bg-white border border-gray-200 rounded p-2">{{ json_encode($diff->theirs->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @else
                                <p class="text-xs text-gray-400 italic">— dit blok is verwijderd —</p>
                            @endif
                        </div>
                    </div>

                    <details class="border border-gray-200 rounded p-2">
                        <summary class="text-xs cursor-pointer text-gray-600">Handmatig samenvoegen (JSON)</summary>
                        <div class="pt-2 space-y-2">
                            <label class="flex items-center gap-2 text-xs">
                                <input type="radio" wire:model.live="choices.{{ $diff->originBlockId }}" value="manual">
                                Gebruik handmatige JSON
                            </label>
                            <textarea wire:model="manualJson.{{ $diff->originBlockId }}" rows="6"
                                placeholder='{"title": "…", "body": "…"}'
                                class="block w-full font-mono text-xs border-gray-300 rounded-md"></textarea>
                        </div>
                    </details>
                </div>
            @endforeach
        </div>
    @endif

    @if ($autoMerges->isNotEmpty())
        <section class="bg-white border border-gray-200 rounded-lg p-4">
            <h3 class="font-medium text-sm mb-2 text-gray-700">Automatisch samengevoegd ({{ $autoMerges->count() }} blok(ken))</h3>
            <ul class="text-xs text-gray-500 list-disc ps-4 space-y-0.5">
                @foreach ($autoMerges as $diff)
                    <li>Blok #{{ $diff->originBlockId }} — {{ $diff->type }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="flex items-center gap-3">
        <button wire:click="resolve" wire:loading.attr="disabled" class="px-4 py-2 bg-rzvg-500 text-white rounded-md hover:bg-rzvg-600 disabled:opacity-50">
            Resolutie opslaan als nieuwe conceptversie
        </button>
        <a href="{{ route('admin.pages.editor', $mine->page) }}" class="text-sm text-gray-600 hover:text-gray-800">Annuleren</a>
    </div>
</div>
