@props([
    'href' => null,
    'active' => false,
    'soon' => false,
])

@php
    $base = 'w-full flex items-center justify-between ps-3 pe-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out focus:outline-none';
    $classes = $active
        ? $base.' border-rzvg-500 text-rzvg-700 bg-rzvg-50 focus:text-rzvg-800 focus:bg-rzvg-100 focus:border-rzvg-700'
        : $base.' border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300';
@endphp

@if ($soon)
    <span class="{{ $base }} border-transparent text-gray-400 cursor-not-allowed" aria-disabled="true">
        <span>{{ $slot }}</span>
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">binnenkort</span>
    </span>
@else
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@endif
