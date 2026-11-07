{{-- Keuzelijst voor <x-content-icon>-namen. Een bestaande waarde die geen
     bekende naam is (bv. een oud emoji-teken) blijft als eigen optie staan. --}}
@php($iconOptions = [
    'information-circle' => 'Informatie',
    'check-circle' => 'Vinkje',
    'users' => 'Mensen',
    'sail' => 'Zeilboot',
    'heart' => 'Hart',
    'compass' => 'Kompas',
    'cup' => 'Kopje (sociëteit)',
    'shield-check' => 'Schild (veiligheid)',
    'calendar' => 'Kalender',
    'clock' => 'Klok',
    'map-pin' => 'Locatie',
    'envelope' => 'E-mail',
    'phone' => 'Telefoon',
    'document' => 'Document',
])
@php($currentIcon = (string) ($editingContent['icon'] ?? ''))
<div>
    <x-input-label for="{{ $id }}" value="Icoon" />
    <select id="{{ $id }}" wire:model="editingContent.icon" class="mt-1 block w-full border-gray-300 rounded-md">
        @if ($allowEmpty ?? false)
            <option value="">— geen —</option>
        @endif
        @if ($currentIcon !== '' && ! array_key_exists($currentIcon, $iconOptions))
            <option value="{{ $currentIcon }}">{{ $currentIcon }} (huidig)</option>
        @endif
        @foreach ($iconOptions as $iconName => $iconLabel)
            <option value="{{ $iconName }}">{{ $iconLabel }}</option>
        @endforeach
    </select>
</div>
