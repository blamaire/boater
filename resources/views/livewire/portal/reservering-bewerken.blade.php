<div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('portal.wijzigingsvoorstellen') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Wijzigingsvoorstellen</a>
        <h1 class="font-display text-2xl text-rzvg-600 mt-2">Reservering aanpassen</h1>
        <p class="text-gray-600 mt-1">Het oude voorstel wordt ingetrokken en vervangen door deze bijgewerkte versie.</p>
    </div>

    @if ($errorMessage)
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-2">{{ $errorMessage }}</div>
    @endif

    <form wire:submit="save" class="space-y-4 rounded-lg border border-gray-200 bg-white p-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 text-sm">
            <div>
                <span class="block font-medium text-gray-700">Object</span>
                <span class="text-gray-900">{{ $object?->name ?? 'onbekend' }}</span>
            </div>
            <div>
                <span class="block font-medium text-gray-700">Voor</span>
                <span class="text-gray-900">{{ $beneficiary?->fullName() ?? 'onbekend' }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <label class="block">
                <span class="block text-sm font-medium text-gray-700">Start</span>
                <input type="datetime-local" wire:model="startsAt" class="mt-1 block w-full rounded border-gray-300">
                @error('startsAt') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="block text-sm font-medium text-gray-700">Einde</span>
                <input type="datetime-local" wire:model="endsAt" class="mt-1 block w-full rounded border-gray-300">
                @error('endsAt') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <label class="block">
            <span class="block text-sm font-medium text-gray-700">Notitie (optioneel)</span>
            <textarea wire:model="note" rows="3" class="mt-1 block w-full rounded border-gray-300"></textarea>
            @error('note') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
        </label>

        <div class="flex justify-end pt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-rzvg-600 text-white px-6 py-2.5 font-semibold hover:bg-rzvg-700">
                Opnieuw indienen
            </button>
        </div>
    </form>
</div>
