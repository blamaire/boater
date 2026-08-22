@props(['icon' => null, 'title' => null, 'href' => null, 'click' => null, 'confirm' => null, 'variant' => 'default'])

@php
    $colors = [
        'default' => 'text-gray-600 hover:text-gray-900',
        'primary' => 'text-rzvg-600 hover:text-rzvg-800',
        'danger' => 'text-red-600 hover:text-red-800',
        'success' => 'text-green-600 hover:text-green-800',
    ];
@endphp

{{-- Vaste breedte per actie, ook als $icon ontbreekt: zo staat dezelfde actie
     altijd op dezelfde plek, ongeacht welke acties een specifieke rij toont. --}}
<td class="w-8 py-2 text-center">
    @if ($icon)
        @if ($href)
            <a href="{{ $href }}" title="{{ $title }}" class="{{ $colors[$variant] }}">
                <x-action-icon name="{{ $icon }}" />
            </a>
        @else
            <button type="button" wire:click="{{ $click }}"
                @if ($confirm) onclick="return confirm('{{ $confirm }}');" @endif
                title="{{ $title }}" class="{{ $colors[$variant] }}">
                <x-action-icon name="{{ $icon }}" />
            </button>
        @endif
    @endif
</td>
