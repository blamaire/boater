<div class="border-t border-gray-200 pt-4 space-y-3">
    <h3 class="font-medium text-gray-900 text-sm">Standaard- en annuleringskosten</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div>
            <x-input-label for="{{ $prefix }}-standard-cost" value="Standaardkosten (optioneel)" />
            <select id="{{ $prefix }}-standard-cost" wire:model="standardCostProductId"
                class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                <option value="">— Geen —</option>
                @foreach ($costProducts as $p)
                    <option value="{{ $p->id }}">
                        {{ $p->name }}
                        @if ($p->currentPrice())
                            (€{{ number_format($p->currentPrice()->amount, 2, ',', '.') }})
                        @else
                            (geen prijs)
                        @endif
                    </option>
                @endforeach
            </select>
            @error('standardCostProductId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-input-label for="{{ $prefix }}-cancellation-cost" value="Annuleringskosten (optioneel)" />
            <select id="{{ $prefix }}-cancellation-cost" wire:model="cancellationCostProductId"
                class="mt-1 w-full border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                <option value="">— Geen —</option>
                @foreach ($costProducts as $p)
                    <option value="{{ $p->id }}">
                        {{ $p->name }}
                        @if ($p->currentPrice())
                            (€{{ number_format($p->currentPrice()->amount, 2, ',', '.') }})
                        @else
                            (geen prijs)
                        @endif
                    </option>
                @endforeach
            </select>
            @error('cancellationCostProductId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    <p class="text-xs text-gray-500">
        Het bedrag komt uit de actuele prijs van het gekozen product — die beheer je in
        <a href="{{ route('admin.products.index') }}" class="text-rzvg-600 hover:text-rzvg-800 underline">producten</a>.
        Standaardkosten worden geboekt bij een bevestigde inschrijving (niet de wachtlijst), annuleringskosten bij het
        afmelden van een bevestigde plek.
    </p>
</div>
