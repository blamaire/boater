<x-app-layout>
    <x-slot name="header">Vergelijken — v{{ $a->version_no }} vs v{{ $b->version_no }}</x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <div>
            <a href="{{ route('admin.pages.history', $page) }}" class="text-sm text-gray-600 hover:text-gray-800">← Historie</a>
        </div>
        @php($report = app(\App\Services\Cms\ConflictDetector::class)->detect($a, $b, null))

        <section class="bg-white border border-gray-200 rounded-lg p-4 text-sm text-gray-700">
            <p>Toont per blok hoe v{{ $a->version_no }} zich verhoudt tot v{{ $b->version_no }}. Deze weergave is puur informatief — herstellen doe je vanuit de historie-lijst.</p>
        </section>

        <div class="space-y-3">
            @foreach ($report->entries as $diff)
                <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-2" wire:key="diff-{{ $diff->originBlockId }}">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium">Blok #{{ $diff->originBlockId }}</span>
                        <span class="text-xs text-gray-500">{{ $diff->type }}</span>
                    </div>

                    @unless ($diff->isNoop())
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="border border-gray-200 rounded p-2">
                                <div class="text-xs uppercase text-gray-500 font-semibold mb-1">v{{ $a->version_no }}</div>
                                @if ($diff->mine)
                                    @include('cms.blocks.preview', ['block' => $diff->mine])
                                @else
                                    <p class="text-xs text-gray-400 italic">— bestaat niet in deze versie —</p>
                                @endif
                            </div>
                            <div class="border border-gray-200 rounded p-2">
                                <div class="text-xs uppercase text-gray-500 font-semibold mb-1">v{{ $b->version_no }}</div>
                                @if ($diff->theirs)
                                    @include('cms.blocks.preview', ['block' => $diff->theirs])
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
    </div>
</x-app-layout>
