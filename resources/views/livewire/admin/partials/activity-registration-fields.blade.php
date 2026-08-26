@php
    $typeLabels = ['text' => 'Tekst', 'choice' => 'Keuze', 'count' => 'Aantal'];
    $fields = $mode === 'pending' ? $pendingRegistrationFields : [];
@endphp

<div class="{{ $mode === 'pending' ? 'border-t border-gray-200 pt-4' : '' }} space-y-3">
    @if ($mode === 'pending')
        <h3 class="font-medium text-gray-900 text-sm">Extra inschrijfvelden (optioneel)</h3>
    @endif

    <div class="space-y-1">
        @if ($mode === 'pending')
            @forelse ($pendingRegistrationFields as $i => $f)
                <div class="flex items-center justify-between text-xs border border-gray-200 rounded-md px-3 py-1.5">
                    <span>
                        <span class="font-medium">{{ $f['label'] }}</span>
                        <span class="text-gray-500">({{ $typeLabels[$f['type']] }}{{ $f['required'] ? ', verplicht' : '' }})</span>
                        @if ($f['type'] === 'count' && $f['price_per_unit'])
                            <span class="text-gray-500">— €{{ number_format($f['price_per_unit'], 2, ',', '.') }} per stuk</span>
                        @elseif ($f['type'] === 'choice')
                            <span class="text-gray-500">— {{ collect($f['options'])->map(fn ($o) => $o['label'].($o['price'] ? ' (€'.number_format($o['price'], 2, ',', '.').')' : ''))->join(', ') }}</span>
                        @endif
                    </span>
                    <button type="button" wire:click="removePendingRegistrationField({{ $i }})" class="text-red-600 hover:text-red-800">Verwijderen</button>
                </div>
            @empty
                <span class="text-xs text-gray-400 italic">Nog geen extra inschrijfvelden.</span>
            @endforelse
        @else
            @forelse ($existingFields as $ef)
                <div class="flex items-center justify-between text-xs border border-gray-200 rounded-md px-3 py-1.5">
                    <span>
                        <span class="font-medium">{{ $ef->label }}</span>
                        <span class="text-gray-500">({{ $typeLabels[$ef->type] }}{{ $ef->required ? ', verplicht' : '' }})</span>
                        @if ($ef->type === 'count' && $ef->price_per_unit)
                            <span class="text-gray-500">— €{{ number_format($ef->price_per_unit, 2, ',', '.') }} per stuk</span>
                        @elseif ($ef->type === 'choice')
                            <span class="text-gray-500">— {{ $ef->options->map(fn ($o) => $o->label.($o->price ? ' (€'.number_format($o->price, 2, ',', '.').')' : ''))->join(', ') }}</span>
                        @endif
                    </span>
                    <button type="button" wire:click="removeRegistrationField({{ $activityId }}, {{ $ef->id }})"
                        onclick="return confirm('Veld verwijderen? Eerder ingevulde antwoorden gaan ook verloren.');"
                        class="text-red-600 hover:text-red-800">Verwijderen</button>
                </div>
            @empty
                <span class="text-xs text-gray-400 italic">Nog geen extra inschrijfvelden.</span>
            @endforelse
        @endif
    </div>

    <div class="border border-gray-200 rounded-md p-3 space-y-3">
        <div class="flex flex-wrap gap-2">
            @foreach ($typeLabels as $value => $label)
                <button type="button" wire:click="selectNewFieldType('{{ $value }}')"
                    @class([
                        'px-3 py-1.5 rounded-full border text-xs font-medium',
                        'border-rzvg-600 bg-rzvg-50 text-rzvg-700' => $newFieldType === $value,
                        'border-gray-300 text-gray-600 hover:border-gray-400' => $newFieldType !== $value,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <x-input-label value="Label" />
                <x-text-input wire:model="newFieldLabel" class="mt-1 w-full text-sm" />
                @error('newFieldLabel') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <label class="inline-flex items-center gap-2 mt-6 text-sm text-gray-700">
                <input type="checkbox" wire:model="newFieldRequired" class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                Verplicht
            </label>
        </div>

        @if ($newFieldType === 'count')
            <div class="grid grid-cols-2 gap-3 max-w-sm">
                <div>
                    <x-input-label value="Prijs per stuk (optioneel)" />
                    <input type="number" step="0.01" min="0" wire:model="newFieldPricePerUnit" class="mt-1 w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('newFieldPricePerUnit') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input-label value="Maximum aantal (optioneel)" />
                    <input type="number" min="1" wire:model="newFieldMaxCount" class="mt-1 w-full border-gray-300 rounded shadow-sm text-sm" />
                    @error('newFieldMaxCount') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        @endif

        @if ($newFieldType === 'choice')
            <div class="space-y-2">
                <x-input-label value="Keuzeopties" />
                @if (count($newFieldOptions) > 0)
                    <ul class="space-y-1">
                        @foreach ($newFieldOptions as $oi => $opt)
                            <li class="flex items-center justify-between text-xs border border-gray-200 rounded px-2 py-1">
                                <span>{{ $opt['label'] }}{{ $opt['price'] ? ' — €'.number_format($opt['price'], 2, ',', '.') : '' }}</span>
                                <button type="button" wire:click="removeNewFieldOption({{ $oi }})" class="text-red-600 hover:text-red-800">×</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="flex items-center gap-2">
                    <input type="text" wire:model="newFieldOptionLabel" placeholder="Optie" class="border-gray-300 rounded shadow-sm text-sm" />
                    <input type="number" step="0.01" min="0" wire:model="newFieldOptionPrice" placeholder="Prijs (optioneel)" class="border-gray-300 rounded shadow-sm text-sm w-32" />
                    <button type="button" wire:click="addNewFieldOption" class="text-xs px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                        Optie toevoegen
                    </button>
                </div>
                @error('newFieldOptionLabel') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                @error('newFieldOptions') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <button type="button"
            wire:click="{{ $mode === 'pending' ? 'addPendingRegistrationField' : 'addRegistrationFieldToActivity('.$activityId.')' }}"
            class="text-xs px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
            Veld toevoegen
        </button>
    </div>
</div>
