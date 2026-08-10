{{-- Gedeelde velden voor zowel de "Nieuw product"-modal als het bewerk-scherm.
     Bindt rechtstreeks aan de Livewire-properties van het ouder-component (geen props nodig). --}}
<div class="grid gap-4 sm:grid-cols-2">
    <label class="block text-sm">
        <span class="text-gray-600">Naam</span>
        <input type="text" wire:model="name" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm" />
        @error('name') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
    </label>

    <label class="block text-sm">
        <span class="text-gray-600">Soort</span>
        <select wire:model="type" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
            @foreach ($types as $t)
                <option value="{{ $t->value }}">{{ $t->label() }}</option>
            @endforeach
        </select>
        @error('type') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
    </label>

    <label class="block text-sm">
        {{-- h-6 lijnt het label uit met de checkbox-regel hiernaast, zodat
             dit dropdown op één lijn staat met het herhaalschema-dropdown. --}}
        <span class="text-gray-600 flex items-center h-6">Opbrengstrekening</span>
        <select wire:model="ledgerAccountId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
            <option value="">— Geen —</option>
            @foreach ($ledgerAccounts as $acc)
                <option value="{{ $acc->id }}">{{ $acc->code }} · {{ $acc->name }}</option>
            @endforeach
        </select>
        @error('ledgerAccountId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
    </label>

    <label class="block text-sm">
        <span class="text-gray-600">BTW-code <span class="text-gray-400">(optioneel)</span></span>
        <select wire:model="btwCodeId" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
            <option value="">— Onbelast —</option>
            @foreach ($btwCodes as $code)
                <option value="{{ $code->id }}">{{ $code->name }} ({{ number_format((float) $code->percentage, 2, ',', '.') }}%)</option>
            @endforeach
        </select>
        @error('btwCodeId') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
    </label>

    <div class="text-sm">
        <label class="inline-flex items-center gap-2 h-6">
            <input type="checkbox" wire:model.live="isRecurring" class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
            Terugkerend product
        </label>
        @if ($isRecurring)
            <select wire:model="recurrence" class="mt-1 block w-full border-gray-300 rounded shadow-sm text-sm">
                <option value="">— Kies herhaalschema —</option>
                @foreach ($recurrences as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </select>
            @error('recurrence') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
        @endif
    </div>
</div>

<div class="text-sm">
    <span class="text-gray-600">Contributie voor lidmaatschapsvorm(en)</span>
    <div class="mt-1 grid gap-1 sm:grid-cols-2 max-h-40 overflow-y-auto border border-gray-200 rounded p-2">
        @foreach ($membershipTypes as $mt)
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="linkedMembershipTypeIds" value="{{ $mt->id }}"
                    class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                <span>{{ $mt->name }}</span>
                @if ($mt->product_id && (! $editingId || $mt->product_id !== $editingId))
                    <span class="text-xs text-gray-400">(ander product)</span>
                @endif
            </label>
        @endforeach
    </div>
</div>
