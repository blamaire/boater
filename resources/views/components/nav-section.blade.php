@props([
    'href' => null,
    'active' => false,
    'soon' => false,
])

@php
    $base = 'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none';
    $classes = $active
        ? $base.' border-rzvg-500 text-gray-900 focus:border-rzvg-700'
        : $base.' border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300';
@endphp

@if ($soon)
    <span class="{{ $base }} border-transparent text-gray-400 cursor-not-allowed" aria-disabled="true" title="Binnenkort beschikbaar">
        {{ $slot }}
        <span class="ms-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">binnenkort</span>
    </span>
@else
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@endif
