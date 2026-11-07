@php
    // Eerst escapen, dan markeren: de <mark>-tags zijn de enige HTML in de uitvoer.
    $highlight = function (?string $text) use ($query): string {
        $escaped = e((string) $text);
        $needle = e($query);
        if ($needle === '') {
            return $escaped;
        }

        // Entiteiten (&amp; e.d.) blijven heel; alleen de tekstdelen worden gemarkeerd.
        $parts = preg_split('/(&#?[a-z0-9]+;)/i', $escaped, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$escaped];
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = preg_replace('/'.preg_quote($needle, '/').'/iu', '<mark class="bg-rzvg-100 text-gray-900">$0</mark>', $part) ?? $part;
            }
        }

        return implode('', $parts);
    };
@endphp

<x-public-layout title="Zoeken">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="font-display text-3xl text-rzvg-600">Zoeken</h1>

        <x-search-box id="zoek-pagina" :value="$query" class="mt-6 w-full max-w-xl" />

        @if ($query !== '')
            @if (mb_strlen($query) < 2)
                <p class="mt-6 text-gray-700">Vul minstens 2 tekens in om te zoeken.</p>
            @elseif ($total === 0)
                <div class="mt-8 rounded-md border border-sand-200 bg-sand-50 p-6">
                    <h2 class="font-display text-xl text-gray-900">Geen resultaten</h2>
                    <p class="mt-2 text-gray-700">Er is niets gevonden voor ‘{{ $query }}’. Controleer de spelling of probeer een korter of algemener zoekwoord.</p>
                </div>
            @else
                <p class="mt-6 text-gray-700">{{ $total }} {{ $total === 1 ? 'resultaat' : 'resultaten' }} voor ‘{{ $query }}’</p>

                @foreach ($groups as $group)
                    <section class="mt-8" aria-labelledby="groep-{{ $group['key'] }}">
                        <h2 id="groep-{{ $group['key'] }}" class="font-display text-2xl text-gray-900">{{ $group['label'] }}</h2>
                        <ul class="mt-3 divide-y divide-sand-200 border-y border-sand-200">
                            @foreach ($group['results'] as $result)
                                <li class="py-4">
                                    <a href="{{ $result->url }}" class="text-lg font-medium text-rzvg-600 hover:text-rzvg-700 hover:underline">{!! $highlight($result->title) !!}</a>
                                    @if ($result->meta)
                                        <p class="text-sm text-gray-500">{{ $result->meta }}</p>
                                    @endif
                                    @if ($result->snippet)
                                        <p class="mt-1 text-gray-700">{!! $highlight($result->snippet) !!}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            @endif
        @endif
    </div>
</x-public-layout>
