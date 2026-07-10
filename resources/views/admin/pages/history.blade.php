<x-app-layout>
    <x-slot name="header">Versiehistorie — {{ $page->title }}</x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4"
        x-data="{
            a: null,
            b: null,
            get ready() { return this.a && this.b && this.a !== this.b; },
            compare() { if (this.ready) window.location = '{{ url()->current() }}/' + this.a + '/diff/' + this.b; }
        }">
        <div class="flex justify-end">
            <a href="{{ route('admin.pages.editor', $page) }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">Naar bewerker →</a>
        </div>
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Versie</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aangemaakt door</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vergelijken</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($versions as $v)
                        <tr>
                            <td class="px-4 py-2 font-mono">v{{ $v->version_no }}</td>
                            <td class="px-4 py-2 text-sm">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-yellow-50 text-yellow-700 border border-yellow-200' => $v->status->value === 'concept',
                                    'bg-blue-50 text-blue-700 border border-blue-200' => $v->status->value === 'in_review',
                                    'bg-green-50 text-green-700 border border-green-200' => $v->status->value === 'gepubliceerd',
                                    'bg-gray-100 text-gray-600' => $v->status->value === 'gearchiveerd',
                                ])>{{ ucfirst(str_replace('_', ' ', $v->status->value)) }}</span>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600">
                                @if ($v->createdBy)
                                    {{ $v->createdBy->first_name }} {{ $v->createdBy->last_name }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">{{ $v->created_at?->translatedFormat('j M Y H:i') }}</td>
                            <td class="px-4 py-2 text-sm">
                                <label class="inline-flex items-center gap-1"><input type="radio" name="diff-a" x-model="a" :disabled="b === '{{ $v->id }}'" value="{{ $v->id }}"> A</label>
                                <label class="ms-2 inline-flex items-center gap-1"><input type="radio" name="diff-b" x-model="b" :disabled="a === '{{ $v->id }}'" value="{{ $v->id }}"> B</label>
                            </td>
                            <td class="px-4 py-2 text-sm text-right">
                                @can('pages.update')
                                    <form method="POST" action="{{ route('admin.pages.history.restore', ['page' => $page, 'version' => $v]) }}" class="inline" onsubmit="return confirm('Nieuwe conceptversie op basis van v{{ $v->version_no }}?');">
                                        @csrf
                                        <button class="text-rzvg-600 hover:text-rzvg-800">Herstellen</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Sticky compare-bar. Klikken op de knop navigeert naar de diff-pagina; de
             knop is enabled zodra A én B gekozen zijn en verschillend zijn. --}}
        <div class="sticky bottom-4 flex justify-end">
            <div class="inline-flex items-center gap-3 rounded-full border border-gray-200 bg-white shadow px-4 py-2 text-sm">
                <span class="text-gray-500">
                    <template x-if="a && b && a === b">
                        <span class="text-red-600">Kies twee verschillende versies</span>
                    </template>
                    <template x-if="!a || !b">
                        <span>Kies een versie voor A en één voor B</span>
                    </template>
                    <template x-if="ready">
                        <span>Klaar om te vergelijken</span>
                    </template>
                </span>
                <button type="button" @click="compare()" :disabled="!ready"
                    :class="ready ? 'bg-rzvg-600 hover:bg-rzvg-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                    class="text-sm font-medium rounded-full px-4 py-1.5">
                    Vergelijken
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
