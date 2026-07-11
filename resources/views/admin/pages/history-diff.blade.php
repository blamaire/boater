<x-app-layout>
    <x-slot name="header">Vergelijken — v{{ $a->version_no }} vs v{{ $b->version_no }}</x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4" x-data="{ tab: 'visual' }">
        <div>
            <a href="{{ route('admin.pages.history', $page) }}" class="text-sm text-gray-600 hover:text-gray-800">← Historie</a>
        </div>

        <section class="bg-white border border-gray-200 rounded-lg p-4 text-sm text-gray-700">
            <p>Toont per blok hoe v{{ $a->version_no }} zich verhoudt tot v{{ $b->version_no }}. Deze weergave is puur informatief — herstellen doe je vanuit de historie-lijst.</p>
        </section>

        <div class="flex gap-1 border-b border-gray-200 text-sm">
            <button type="button" @click="tab = 'visual'"
                :class="tab === 'visual' ? 'border-rzvg-600 text-rzvg-700 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-3 py-2 border-b-2 -mb-px">Visueel</button>
            <button type="button" @click="tab = 'json_diff'"
                :class="tab === 'json_diff' ? 'border-rzvg-600 text-rzvg-700 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-3 py-2 border-b-2 -mb-px">JSON — gestructureerde diff</button>
            <button type="button" @click="tab = 'json_raw'"
                :class="tab === 'json_raw' ? 'border-rzvg-600 text-rzvg-700 font-medium' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-3 py-2 border-b-2 -mb-px">JSON — beide versies rauw</button>
        </div>

        <div x-show="tab === 'visual'" class="space-y-3">
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

        <div x-show="tab === 'json_diff'" x-cloak class="space-y-2">
            <p class="text-xs text-gray-500">Per blok het type verschil, de sleutels die botsen, en de content in v-A en v-B. Blokken met type <code>unchanged</code> zijn identiek in beide versies.</p>
            <div class="relative">
                <button type="button"
                    @click="navigator.clipboard.writeText($refs.jsonDiff.innerText)"
                    class="absolute top-2 right-2 text-xs px-2 py-1 rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">Kopieer</button>
                <pre x-ref="jsonDiff" class="bg-gray-900 text-gray-100 text-xs rounded p-4 overflow-x-auto">{{ json_encode($structuredDiff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>

        <div x-show="tab === 'json_raw'" x-cloak class="grid gap-3 md:grid-cols-2">
            <div class="relative">
                <div class="text-xs uppercase text-gray-500 font-semibold mb-1">v{{ $a->version_no }}</div>
                <button type="button"
                    @click="navigator.clipboard.writeText($refs.rawA.innerText)"
                    class="absolute top-6 right-2 text-xs px-2 py-1 rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">Kopieer</button>
                <pre x-ref="rawA" class="bg-gray-900 text-gray-100 text-xs rounded p-4 overflow-x-auto">{{ json_encode($rawA, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            <div class="relative">
                <div class="text-xs uppercase text-gray-500 font-semibold mb-1">v{{ $b->version_no }}</div>
                <button type="button"
                    @click="navigator.clipboard.writeText($refs.rawB.innerText)"
                    class="absolute top-6 right-2 text-xs px-2 py-1 rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">Kopieer</button>
                <pre x-ref="rawB" class="bg-gray-900 text-gray-100 text-xs rounded p-4 overflow-x-auto">{{ json_encode($rawB, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
</x-app-layout>
