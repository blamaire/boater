<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Pagina's en berichten uit de oude WordPress-site, geïmporteerd via <code>rzvg:import-wordpress</code>. Bekijk
        een item en beslis: <strong>overnemen</strong> maakt er een echte CMS-pagina van (als concept — die controleer
        en publiceer je zelf), of <strong>archiveren</strong> laat het item alleen in deze staging staan. Let op:
        afbeeldingen in de overgenomen HTML blijven verwijzen naar de oude site.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif
    @if ($errorMessage)
        <div class="rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-2" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="flex flex-wrap items-end gap-4 text-sm">
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">Status</span>
            <select wire:model.live="filterStatus" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-1">
            <span class="text-gray-600">Type</span>
            <select wire:model.live="filterType" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                @foreach ($types as $t)
                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200 text-sm">
                <colgroup>
                    <col>
                    <col class="w-28">
                    <col class="w-32">
                    <col class="w-32">
                    <col class="w-8">
                    <col class="w-8">
                    <col class="w-8">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Titel</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">WP-publicatiedatum</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="3">Acties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($items as $item)
                        <tr wire:key="wordpress-import-{{ $item->id }}">
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $item->title }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border bg-gray-50 text-gray-700 border-gray-200">
                                    {{ $item->wordpress_type->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-700 whitespace-nowrap">
                                {{ $item->wordpress_published_at?->format('d-m-Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border
                                    @class([
                                        'bg-yellow-50 text-yellow-800 border-yellow-200' => $item->status === \App\Enums\WordpressImportStatus::New,
                                        'bg-green-50 text-green-800 border-green-200' => $item->status === \App\Enums\WordpressImportStatus::Imported,
                                        'bg-gray-100 text-gray-600 border-gray-200' => $item->status === \App\Enums\WordpressImportStatus::Archived,
                                    ])">
                                    {{ $item->status->label() }}
                                </span>
                            </td>
                            <x-action-cell click="view({{ $item->id }})" icon="eye" title="Bekijken" />
                            @if ($item->status === \App\Enums\WordpressImportStatus::New)
                                <x-action-cell click="takeOver({{ $item->id }})" icon="check" title="Overnemen" variant="success" confirm="Overnemen als CMS-pagina?" />
                                <x-action-cell click="archive({{ $item->id }})" icon="archive" title="Archiveren" confirm="Archiveren (geen pagina)?" />
                            @elseif ($item->status === \App\Enums\WordpressImportStatus::Imported && $item->page_id !== null)
                                <x-action-cell href="{{ route('admin.pages.editor', $item->page_id) }}" icon="pencil" title="Naar pagina" variant="primary" />
                                <td class="w-8"></td>
                            @else
                                <x-action-cell click="restoreToNew({{ $item->id }})" icon="undo" title="Terugzetten naar nieuw" confirm="Terugzetten naar nieuw?" />
                                <td class="w-8"></td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">Geen geïmporteerde items met de huidige filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $items->links() }}
            </div>
        @endif
    </section>

    <x-modal name="wordpress-import-preview" maxWidth="3xl">
        <div class="p-6 space-y-4">
            <h2 class="font-medium text-gray-900 text-lg">{{ $viewingItem?->title }}</h2>

            <dl class="grid gap-2 sm:grid-cols-3 text-sm">
                <div>
                    <dt class="text-gray-500 text-xs uppercase">WP-ID</dt>
                    <dd class="text-gray-900">{{ $viewingItem?->wordpress_id }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 text-xs uppercase">Publicatiedatum</dt>
                    <dd class="text-gray-900">{{ $viewingItem?->wordpress_published_at?->format('d-m-Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 text-xs uppercase">Type</dt>
                    <dd class="text-gray-900">{{ $viewingItem?->wordpress_type?->label() }}</dd>
                </div>
            </dl>

            @if ($viewingItem?->excerpt)
                <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase">Samenvatting</div>
                    <div class="text-sm text-gray-800 whitespace-pre-line">{{ $viewingItem->excerpt }}</div>
                </div>
            @endif

            <div>
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Inhoud (ruwe HTML)</div>
                {{-- Bewust {{ }} i.p.v. {!! !!}: content_html is ongesaneerde WordPress-HTML uit een
                     geüpload bestand; direct renderen zou een kwaadwillig geprepareerd exportbestand
                     JS kunnen laten uitvoeren in de sessie van de beoordelende beheerder. --}}
                <pre class="whitespace-pre-wrap break-words text-xs bg-gray-50 border border-gray-200 rounded p-3 max-h-96 overflow-y-auto">{{ $viewingItem?->content_html }}</pre>
            </div>

            <div class="flex justify-end pt-2">
                <button type="button" x-on:click="$dispatch('close')"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm">Sluiten</button>
            </div>
        </div>
    </x-modal>
</div>
