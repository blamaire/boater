{{-- Hoofdmenu van de publieke site, gevuld door PublicNavComposer (handmatige
     NavItems, anders automatisch uit de paginaboom). Twee weergaven:
     - $variant 'desktop': horizontale lijst in de header (vanaf lg), met
       uitklapmenu voor onderliggende pagina's;
     - $variant 'mobile': verticale lijst in het uitklapbare menupaneel;
     - $variant 'balk': horizontale balk op alle breedtes (ledenportaal,
       layouts/app). --}}
@php
    $variant = $variant ?? 'desktop';
    $navItems =collect($publicNav ?? [])->map(function ($item) {
        $isNav = $item instanceof \App\Models\NavItem;

        return [
            'label' => $isNav ? $item->displayLabel() : $item->title,
            'href' => ($isNav ? $item->url() : $item->publicUrl()) ?: null,
            'children' => collect($item->children ?? [])->map(fn ($child) => [
                'label' => $isNav ? $child->displayLabel() : $child->title,
                'href' => ($isNav ? $child->url() : $child->publicUrl()) ?: '#',
            ])->all(),
        ];
    });
@endphp

@if ($navItems->isNotEmpty())
    @if ($variant === 'desktop')
        <nav aria-label="Hoofdmenu" class="hidden lg:block">
            <ul class="flex items-center gap-1 text-[0.95rem] font-medium">
                @foreach ($navItems as $item)
                    <li class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                        @if ($item['children'] === [])
                            <a href="{{ $item['href'] ?? '#' }}" class="block px-3 py-2 rounded-md text-gray-900 hover:text-rzvg-600">{{ $item['label'] }}</a>
                        @else
                            <button type="button" @click="open = !open" :aria-expanded="open"
                                class="inline-flex items-center gap-1 px-3 py-2 rounded-md text-gray-900 hover:text-rzvg-600">
                                {{ $item['label'] }}
                                <svg aria-hidden="true" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                            </button>
                            <ul x-show="open" x-cloak @click.outside="open = false" x-transition
                                class="absolute z-30 left-0 mt-1 bg-white border border-sand-200 rounded-xl shadow-lg min-w-[13rem] py-2">
                                @if ($item['href'])
                                    <li><a href="{{ $item['href'] }}" class="block px-4 py-2 hover:bg-sand-100">{{ $item['label'] }}</a></li>
                                @endif
                                @foreach ($item['children'] as $child)
                                    <li><a href="{{ $child['href'] }}" class="block px-4 py-2 hover:bg-sand-100">{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @elseif ($variant === 'balk')
        <nav aria-label="Hoofdmenu" class="border-t border-gray-100 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <ul class="flex flex-wrap items-center gap-2 py-2 text-sm">
                    <li>
                        <a href="{{ route('public.home') }}" class="px-2 py-1 hover:text-rzvg-600">Home</a>
                    </li>
                    @foreach ($navItems as $item)
                        <li class="relative" x-data="{ open: false }">
                            @if ($item['children'] === [])
                                <a href="{{ $item['href'] ?? '#' }}" class="px-2 py-1 hover:text-rzvg-600">{{ $item['label'] }}</a>
                            @else
                                <button type="button" @click="open = !open" class="px-2 py-1 hover:text-rzvg-600 inline-flex items-center gap-1">
                                    {{ $item['label'] }}
                                    <svg aria-hidden="true" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                                </button>
                                <ul x-show="open" @click.outside="open = false" x-transition class="absolute z-10 left-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg min-w-[12rem] py-1">
                                    @if ($item['href'])
                                        <li><a href="{{ $item['href'] }}" class="block px-3 py-1.5 hover:bg-gray-50">{{ $item['label'] }}</a></li>
                                    @endif
                                    @foreach ($item['children'] as $child)
                                        <li><a href="{{ $child['href'] }}" class="block px-3 py-1.5 hover:bg-gray-50">{{ $child['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </nav>
    @else
        <nav aria-label="Hoofdmenu" class="max-w-6xl mx-auto px-4 sm:px-6 py-3">
            <ul class="divide-y divide-sand-200 text-lg">
                @foreach ($navItems as $item)
                    <li class="py-1">
                        <a href="{{ $item['href'] ?? '#' }}" class="block py-2 font-medium text-gray-900 hover:text-rzvg-600">{{ $item['label'] }}</a>
                        @if ($item['children'] !== [])
                            <ul class="pb-2 ps-4 text-base">
                                @foreach ($item['children'] as $child)
                                    <li><a href="{{ $child['href'] }}" class="block py-1.5 text-gray-600 hover:text-rzvg-600">{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
@endif
