<div class="border-t border-gray-200 pt-4 space-y-3">
    <h3 class="font-medium text-gray-900 text-sm">Standaard- en annuleringskosten</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div>
            <x-input-label for="{{ $prefix }}-standard-cost" value="Standaardkosten (optioneel)" />
            <div class="mt-1 relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-gray-500">€</span>
                <input type="number" step="0.01" min="0" id="{{ $prefix }}-standard-cost" wire:model="standardCostAmount"
                    class="w-full pl-6 border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
            </div>
            @error('standardCostAmount') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-input-label for="{{ $prefix }}-cancellation-cost" value="Annuleringskosten (optioneel)" />
            <div class="mt-1 relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-gray-500">€</span>
                <input type="number" step="0.01" min="0" id="{{ $prefix }}-cancellation-cost" wire:model="cancellationCostAmount"
                    class="w-full pl-6 border-gray-300 rounded shadow-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
            </div>
            @error('cancellationCostAmount') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    <p class="text-xs text-gray-500">
        Standaardkosten worden geboekt bij een bevestigde inschrijving (niet de wachtlijst), annuleringskosten bij het
        afmelden van een bevestigde plek.
    </p>
</div>
