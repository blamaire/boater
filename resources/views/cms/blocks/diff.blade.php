@php
    /** @var \App\Services\Cms\ConflictReport $report */
    /** @var \App\Models\PageVersion $a */
    /** @var \App\Models\PageVersion $b */
@endphp

<div class="space-y-3">
    @foreach ($report->entries as $diff)
        <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-2" wire:key="diff-{{ $diff->originBlockId }}">
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium">Blok #{{ $diff->originBlockId }}</span>
                <span class="text-xs text-gray-500">{{ $diff->type }}</span>
            </div>

            @unless ($diff->isNoop())
                <div class="grid gap-3 md:grid-cols-2">
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
                <p class="text-xs text-gray-400 italic">Ongewijzigd tussen beide versies.</p>
            @endunless
        </div>
    @endforeach
</div>
