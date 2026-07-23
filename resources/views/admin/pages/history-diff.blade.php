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

        <div x-show="tab === 'visual'">
            @include('cms.blocks.diff', ['report' => $report, 'a' => $a, 'b' => $b])
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

        <div x-show="tab === 'json_raw'" x-cloak class="space-y-2">
            <div class="flex items-center justify-between">
                <p class="text-xs text-gray-500">
                    Regels op dezelfde hoogte horen bij elkaar. <span class="inline-block w-3 h-3 align-middle bg-red-50 border border-red-200"></span> alleen in v{{ $a->version_no }},
                    <span class="inline-block w-3 h-3 align-middle bg-green-50 border border-green-200"></span> alleen in v{{ $b->version_no }}.
                </p>
                <div class="flex items-center gap-2">
                    <button type="button"
                        @click="navigator.clipboard.writeText($refs.rawA.textContent)"
                        class="text-xs px-2 py-1 rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">Kopieer v{{ $a->version_no }}</button>
                    <button type="button"
                        @click="navigator.clipboard.writeText($refs.rawB.textContent)"
                        class="text-xs px-2 py-1 rounded border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">Kopieer v{{ $b->version_no }}</button>
                </div>
            </div>
            <pre x-ref="rawA" class="hidden">{{ $rawAJson }}</pre>
            <pre x-ref="rawB" class="hidden">{{ $rawBJson }}</pre>

            <div class="border border-gray-200 rounded overflow-x-auto">
                <div class="grid text-xs font-mono min-w-[640px]" style="grid-template-columns: 1fr 1fr;">
                    <div class="bg-gray-50 border-b border-r border-gray-200 px-3 py-1 font-sans font-semibold text-gray-600">v{{ $a->version_no }}</div>
                    <div class="bg-gray-50 border-b border-gray-200 px-3 py-1 font-sans font-semibold text-gray-600">v{{ $b->version_no }}</div>
                    @foreach ($textDiff as $row)
                        <div @class([
                            'px-3 py-0.5 whitespace-pre-wrap break-all border-r border-gray-100',
                            'bg-red-50 text-red-900' => in_array($row['type'], ['removed', 'changed'], true),
                        ])>{{ $row['left'] ?? '' }}</div>
                        <div @class([
                            'px-3 py-0.5 whitespace-pre-wrap break-all',
                            'bg-green-50 text-green-900' => in_array($row['type'], ['added', 'changed'], true),
                        ])>{{ $row['right'] ?? '' }}</div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
