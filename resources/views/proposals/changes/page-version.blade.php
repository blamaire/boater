@php
    /** @var \App\Models\Page|null $page */
    /** @var \App\Models\PageVersion|null $version */
    /** @var string|null $diffUrl */
@endphp

<div class="text-sm text-gray-600">
    @if ($page && $version)
        Versie <span class="font-medium">v{{ $version->version_no }}</span> van pagina
        <span class="font-medium">{{ $page->title }}</span>
        @if ($diffUrl)
            · <a href="{{ $diffUrl }}" class="text-rzvg-600 hover:text-rzvg-800 hover:underline font-medium">Bekijk inhoudswijziging &rarr;</a>
        @else
            <span class="text-gray-400 italic">(nieuwe pagina — niets om mee te vergelijken)</span>
        @endif
    @else
        <span class="text-gray-400 italic">Pagina niet meer gevonden.</span>
    @endif
</div>
