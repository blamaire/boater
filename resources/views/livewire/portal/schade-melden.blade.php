<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8">
    <p class="text-sm text-gray-500">
        Meld schade aan een boot, roeimateriaal of ander object. Bij "niet bruikbaar" gaat het object direct op buiten gebruik totdat een behandelaar dit terugdraait.
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

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-900">Nieuwe melding</h2>

        <form wire:submit="submit" class="space-y-4" x-on:dragover.prevent x-on:drop.prevent>
            <div>
                <label class="block text-sm font-medium text-gray-700">Object</label>
                <select wire:model="selectedObjectId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    <option value="">— Kies een object —</option>
                    @foreach ($objects as $o)
                        <option value="{{ $o->id }}">{{ $o->name }} ({{ $o->category->name }})</option>
                    @endforeach
                </select>
                @error('selectedObjectId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            @if ($ownReservations->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-gray-700">Optioneel: aan een van jouw reserveringen koppelen</label>
                    <select wire:model="reservationId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                        <option value="">— Geen —</option>
                        @foreach ($ownReservations as $r)
                            <option value="{{ $r->id }}">{{ $r->object->name }} · {{ $r->starts_at->format('d-m-Y H:i') }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700">Omschrijving</label>
                <textarea wire:model="description" rows="4" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm"
                    placeholder="Wat is er gebeurd? Waar zit de schade precies?"></textarea>
                @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Ernst</label>
                <select wire:model="severity" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                    @foreach ($severities as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="reporterMarkedUnusable" class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                <span>Object is niet bruikbaar (zet het direct op buiten gebruik)</span>
            </label>

            <div>
                <label class="block text-sm font-medium text-gray-700">Foto's (optioneel)</label>

                <div
                    x-data="{ dragging: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; $wire.uploadMultiple('photos', $event.dataTransfer.files)"
                    :class="dragging ? 'border-rzvg-400 bg-rzvg-50' : 'border-gray-300'"
                    class="mt-1 flex flex-col items-center gap-1 rounded-md border-2 border-dashed px-4 py-6 text-center transition-colors"
                >
                    <p class="text-sm text-gray-600">
                        Sleep foto's hierheen, of
                        <label for="schade-photos" class="cursor-pointer font-medium text-rzvg-600 underline hover:text-rzvg-700">kies bestanden</label>
                    </p>
                    <input id="schade-photos" type="file" wire:model="photos" multiple accept="image/*" class="sr-only" />
                    @if (count($photos))
                        <p class="text-xs text-gray-500">{{ count($photos) }} foto('s) geselecteerd</p>
                    @endif
                </div>

                @error('photos.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-gray-500 mt-1">Foto's zijn alleen zichtbaar voor de schadebehandelaars, niet in de mediabibliotheek.</p>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-rzvg-600 text-white text-sm font-medium rounded hover:bg-rzvg-700">
                    Melding indienen
                </button>
            </div>
        </form>
    </section>

    <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Mijn meldingen</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Wanneer</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Object</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ernst</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($myReports as $r)
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $r->reported_at->format('d-m-Y H:i') }}</td>
                        <td class="px-4 py-2 text-gray-900">{{ $r->object->name }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $r->severity->label() }}</td>
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">Je hebt nog geen schademeldingen gedaan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
