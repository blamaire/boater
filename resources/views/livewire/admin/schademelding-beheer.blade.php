<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    <p class="text-sm text-gray-500">
        Meldingen doorlopen de workflow gemeld → in behandeling → opgelost/afgewezen. Bij "niet bruikbaar" is het object al automatisch buiten gebruik gezet — je kunt dat hier terugdraaien zodra de schade hersteld is.
    </p>

    @if ($statusMessage)
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-4 text-sm">
        <label class="flex items-center gap-2">
            Categorie:
            <select wire:model.live="filterCategoryId" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Alle —</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2">
            Status:
            <select wire:model.live="filterStatus" class="border-gray-300 rounded shadow-sm text-sm">
                <option value="open">Open (gemeld + in behandeling)</option>
                <option value="all">Alle</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Wanneer</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Object</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Melder</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ernst</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Toegewezen</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($reports as $r)
                    <tr @class(['bg-yellow-50/40' => $r->reporter_marked_unusable && $r->status->isOpen()])>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $r->reported_at->format('d-m-Y H:i') }}</td>
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-900">{{ $r->object->name }}</div>
                            <div class="text-xs text-gray-500">{{ $r->object->category->name }}</div>
                            @if ($r->reporter_marked_unusable)
                                <div class="text-xs text-yellow-800 mt-1">Melder meldde: niet bruikbaar</div>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-700 text-xs">
                            {{ $r->reportedBy->first_name }} {{ $r->reportedBy->last_name }}
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $r->severity->label() }}</td>
                        <td class="px-4 py-2 text-gray-700 text-xs">
                            @if ($r->assignedTo)
                                {{ $r->assignedTo->first_name }} {{ $r->assignedTo->last_name }}
                            @else
                                <span class="text-gray-400 italic">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border
                                @class([
                                    'bg-yellow-50 text-yellow-800 border-yellow-200' => $r->status === \App\Enums\DamageReportStatus::Reported,
                                    'bg-blue-50 text-blue-800 border-blue-200' => $r->status === \App\Enums\DamageReportStatus::InProgress,
                                    'bg-green-50 text-green-800 border-green-200' => $r->status === \App\Enums\DamageReportStatus::Resolved,
                                    'bg-gray-100 text-gray-600 border-gray-200' => $r->status === \App\Enums\DamageReportStatus::Rejected,
                                ])">
                                {{ $r->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button type="button" wire:click="toggle({{ $r->id }})" class="text-rzvg-700 hover:text-rzvg-800 text-xs">
                                {{ $expandedReportId === $r->id ? 'Verbergen' : 'Openen' }}
                            </button>
                        </td>
                    </tr>
                    @if ($expandedReportId === $r->id)
                        <tr>
                            <td colspan="7" class="px-4 py-4 bg-gray-50">
                                <div class="space-y-4">
                                    <div>
                                        <div class="text-xs font-semibold text-gray-500 uppercase">Omschrijving</div>
                                        <div class="text-sm text-gray-800 whitespace-pre-line">{{ $r->description }}</div>
                                    </div>

                                    @if ($r->photos->isNotEmpty())
                                        <div>
                                            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Foto's</div>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($r->photos as $p)
                                                    <a href="{{ $p->displayUrl() }}" target="_blank" class="block">
                                                        @if ($p->thumbnailUrl())
                                                            <img src="{{ $p->thumbnailUrl() }}" alt="" class="h-24 w-24 object-cover rounded border border-gray-200" />
                                                        @else
                                                            <span class="text-xs underline">{{ $p->original_name }}</span>
                                                        @endif
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if ($r->status->isOpen())
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <label class="block text-xs font-semibold text-gray-500 uppercase">Toewijzen aan</label>
                                                <select wire:model="assigneeInput" class="block w-full border-gray-300 rounded shadow-sm text-sm">
                                                    <option value="">— Kies persoon —</option>
                                                    @foreach ($personsForAssignment as $p)
                                                        <option value="{{ $p->id }}">{{ $p->first_name }} {{ $p->last_name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button" wire:click="assign({{ $r->id }})"
                                                    class="text-xs bg-white border border-gray-300 rounded px-3 py-1 hover:bg-gray-50">Toewijzen</button>
                                            </div>
                                            <div class="space-y-2">
                                                <label class="block text-xs font-semibold text-gray-500 uppercase">Oplossing / notitie</label>
                                                <textarea wire:model="resolutionInput" rows="2" class="block w-full border-gray-300 rounded shadow-sm text-sm"></textarea>
                                                <div class="flex flex-wrap gap-2">
                                                    @if ($r->status === \App\Enums\DamageReportStatus::Reported)
                                                        <button type="button" wire:click="markInProgress({{ $r->id }})"
                                                            class="text-xs bg-blue-600 text-white rounded px-3 py-1 hover:bg-blue-700">In behandeling</button>
                                                    @endif
                                                    <button type="button" wire:click="markResolved({{ $r->id }})"
                                                        class="text-xs bg-green-600 text-white rounded px-3 py-1 hover:bg-green-700">Opgelost</button>
                                                    <button type="button" wire:click="markRejected({{ $r->id }})"
                                                        class="text-xs bg-gray-600 text-white rounded px-3 py-1 hover:bg-gray-700">Afwijzen</button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($r->object->status === \App\Enums\ReservableObjectStatus::OutOfService)
                                        <div class="rounded-md bg-yellow-50 border border-yellow-200 text-yellow-800 text-xs px-3 py-2 flex items-center justify-between gap-4">
                                            <span>Object staat op buiten gebruik. Zet weer op beschikbaar zodra de schade hersteld is.</span>
                                            <button type="button" wire:click="restoreObject({{ $r->id }})"
                                                onclick="return confirm('Object weer op beschikbaar zetten?');"
                                                class="text-xs bg-white border border-yellow-300 rounded px-3 py-1 hover:bg-yellow-100">Object weer beschikbaar</button>
                                        </div>
                                    @endif

                                    @if ($r->resolution)
                                        <div>
                                            <div class="text-xs font-semibold text-gray-500 uppercase">Oplossing</div>
                                            <div class="text-sm text-gray-800 whitespace-pre-line">{{ $r->resolution }}</div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">Geen meldingen met de huidige filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
