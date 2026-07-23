@php
    /** @var \App\Services\Cms\ConflictReport $report */
    /** @var \App\Models\PageVersion $a */
    /** @var \App\Models\PageVersion $b */
@endphp

<div class="space-y-3" x-data="{ expanded: false }">
    <div class="flex justify-end">
        <button type="button"
            @click="expanded = !expanded; $root.querySelectorAll('details').forEach(d => d.open = expanded)"
            class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">
            <span x-text="expanded ? 'Alles inklappen' : 'Alles uitklappen'">Alles uitklappen</span>
        </button>
    </div>

    @foreach ($report->entries as $diff)
        @if ($diff->isNoop())
            <details class="group bg-white border border-gray-200 rounded-lg p-4" wire:key="diff-{{ $diff->originBlockId }}">
                <summary class="flex items-center justify-between text-sm cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
                    <span class="flex items-center gap-2">
                        <svg width="16" height="16" class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open:rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                        <span class="font-medium">Blok #{{ $diff->originBlockId }}</span>
                    </span>
                    <span class="text-xs text-gray-500">{{ $diff->label() }}</span>
                </summary>

                @if ($diff->mine || $diff->theirs)
                    <div class="grid gap-3 md:grid-cols-2 mt-2">
                        <div class="border border-gray-200 rounded p-2 overflow-hidden">
                            <div class="text-xs uppercase text-gray-500 font-semibold mb-1">v{{ $a->version_no }}</div>
                            @if ($diff->mine)
                                @include('cms.blocks.preview', ['block' => $diff->mine, 'fullBleed' => false])
                            @else
                                <p class="text-xs text-gray-400 italic">— bestaat niet in deze versie —</p>
                            @endif
                        </div>
                        <div class="border border-gray-200 rounded p-2 overflow-hidden">
                            <div class="text-xs uppercase text-gray-500 font-semibold mb-1">v{{ $b->version_no }}</div>
                            @if ($diff->theirs)
                                @include('cms.blocks.preview', ['block' => $diff->theirs, 'fullBleed' => false])
                            @else
                                <p class="text-xs text-gray-400 italic">— bestaat niet in deze versie —</p>
                            @endif
                        </div>
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic mt-2">Verwijderd in beide versies — geen inhoud om te tonen.</p>
                @endif
            </details>
        @else
            <details class="group bg-white border border-gray-200 rounded-lg p-4 space-y-2" open wire:key="diff-{{ $diff->originBlockId }}">
                <summary class="flex items-center justify-between text-sm cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
                    <span class="flex items-center gap-2">
                        <svg width="16" height="16" class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open:rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                        <span class="font-medium">Blok #{{ $diff->originBlockId }}</span>
                    </span>
                    <span class="text-xs text-gray-500">{{ $diff->label() }}</span>
                </summary>

                <div class="grid gap-3 md:grid-cols-2 mt-2">
                    <div class="border border-gray-200 rounded p-2 overflow-hidden">
                        <div class="text-xs uppercase text-gray-500 font-semibold mb-1">v{{ $a->version_no }}</div>
                        @if ($diff->mine)
                            @include('cms.blocks.preview', ['block' => $diff->mine, 'fullBleed' => false])
                        @else
                            <p class="text-xs text-gray-400 italic">— bestaat niet in deze versie —</p>
                        @endif
                    </div>
                    <div class="border border-gray-200 rounded p-2 overflow-hidden">
                        <div class="text-xs uppercase text-gray-500 font-semibold mb-1">v{{ $b->version_no }}</div>
                        @if ($diff->theirs)
                            @include('cms.blocks.preview', ['block' => $diff->theirs, 'fullBleed' => false])
                        @else
                            <p class="text-xs text-gray-400 italic">— bestaat niet in deze versie —</p>
                        @endif
                    </div>
                </div>
            </details>
        @endif
    @endforeach
</div>
