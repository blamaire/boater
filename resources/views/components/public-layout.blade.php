@props(['title' => null, 'previewBanner' => null, 'description' => null, 'ogTitle' => null, 'ogDescription' => null, 'ogImage' => null, 'canonicalUrl' => null, 'page' => null, 'version' => null, 'memberStripInline' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' — RZVG' : config('app.name', 'RZVG') }}</title>

        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="RZVG">
        <meta property="og:title" content="{{ $ogTitle ?: ($title ?: config('app.name')) }}">
        @if ($ogDescription ?: $description)
            <meta property="og:description" content="{{ $ogDescription ?: $description }}">
        @endif
        <meta property="og:image" content="{{ $ogImage ?: asset('img/branding/rzvg-logo.jpg') }}">
        @if ($canonicalUrl)
            <meta property="og:url" content="{{ $canonicalUrl }}">
            <link rel="canonical" href="{{ $canonicalUrl }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=alike:400|inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased text-gray-900 bg-white">
        <x-local-branch-bar />
        @if ($previewBanner)
            <div class="bg-red-200 text-red-900 text-sm text-center py-2 px-4 font-medium">
                {{ $previewBanner }}
            </div>
        @endif
        <div class="min-h-screen flex flex-col" x-data="{ sidebarOpen: false, menuOpen: false }">
            @unless ($memberStripInline)
                @include('public._ledenstrook')
            @endunless
            <header class="border-b border-sand-200 bg-white relative z-20">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4">
                    <a href="/" class="flex items-center gap-3 shrink-0">
                        <img src="{{ asset('img/branding/rzvg-logo.jpg') }}" alt="RZVG — Roei- en Zeilvereniging Gouda" class="h-12 w-auto">
                    </a>

                    @include('public._nav', ['variant' => 'desktop'])

                    <x-search-box id="zoek-header" class="hidden lg:block w-44 shrink-0" />

                    <div class="flex items-center gap-2 sm:gap-3 text-sm">
                        @auth
                            <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center px-3 py-1.5 rounded-md border border-rzvg-200 text-rzvg-700 hover:bg-rzvg-50">Ledenpagina</a>
                            <span class="hidden xl:inline text-gray-700 max-w-[10rem] truncate">{{ Auth::user()->name }}</span>
                            <button @click="sidebarOpen = ! sidebarOpen"
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100"
                                aria-label="Ledenmenu openen/sluiten">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0" />
                                </svg>
                            </button>
                        @else
                            <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center px-4 py-2 rounded-md bg-white border border-rzvg-500 text-rzvg-600 hover:bg-rzvg-50">Inloggen</a>
                            <a href="{{ route('lid-worden') }}" class="inline-flex items-center px-4 py-2 rounded-md bg-rzvg-500 text-white hover:bg-rzvg-600">Lid worden</a>
                        @endauth
                        <button type="button" @click="menuOpen = ! menuOpen" :aria-expanded="menuOpen" aria-controls="mobiel-menu"
                            class="lg:hidden inline-flex items-center justify-center h-11 w-11 rounded-lg border border-sand-200 text-gray-900 hover:bg-sand-100"
                            aria-label="Hoofdmenu openen/sluiten">
                            <svg aria-hidden="true" class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path x-show="! menuOpen" stroke-linecap="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16" />
                                <path x-show="menuOpen" x-cloak stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div id="mobiel-menu" x-show="menuOpen" x-cloak x-transition.origin.top @click.outside="menuOpen = false"
                    class="lg:hidden absolute inset-x-0 top-full bg-white border-b border-sand-200 shadow-lg">
                    <div class="max-w-6xl mx-auto px-4 pt-4">
                        <x-search-box id="zoek-mobiel" class="w-full" />
                    </div>
                    @include('public._nav', ['variant' => 'mobile'])
                    @guest
                        {{-- Op smalle schermen past de Inloggen-knop niet in de header. --}}
                        <div class="sm:hidden max-w-6xl mx-auto px-4 pb-4">
                            <a href="{{ route('login') }}" class="flex w-full items-center justify-center px-4 py-2 rounded-md bg-white border border-rzvg-500 text-rzvg-600 hover:bg-rzvg-50">Inloggen</a>
                        </div>
                    @endguest
                </div>
            </header>

            <div class="flex-1 flex">
                {{-- Geen max-w-wrapper hier: die zou een vaste (100vw-relatieve)
                     breedte veronderstellen. Met de rechter-sidebar (hieronder)
                     als echte flex-sibling is <main> zelf al correct breed —
                     inclusief wanneer de sidebar openklapt. Pagina's die een
                     gecentreerde leesbreedte willen, wikkelen hun eigen content
                     zelf in max-w-*/mx-auto (zie public/page.blade.php per band,
                     of activities/show.blade.php). --}}
                <main class="flex-1 min-w-0">
                    {{ $slot }}
                </main>

                @auth
                    {{-- Zelfde rechter sidebar als de ledenpagina (layouts/app.blade.php),
                         zodat ingelogde leden ook vanaf publieke pagina's bij het portaal-
                         en beheermenu kunnen. --}}
                    <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
                        class="md:hidden fixed inset-0 bg-black/40 z-30" style="display: none;"></div>
                    <aside
                        x-show="sidebarOpen"
                        x-cloak
                        class="w-64 shrink-0 bg-white border-l border-gray-200 z-40 fixed md:sticky inset-y-0 right-0 md:top-0 md:self-stretch md:max-h-screen overflow-y-auto">
                        @include('layouts.navigation')
                    </aside>
                @endauth

                {{-- bottom via inline style (i.p.v. een nieuwe bottom-*-utility): een
                     net-geïntroduceerde klasse duikt soms pas op ná een Tailwind-scan
                     (zie feedback-widget's eerdere fixed/bottom-4/right-4-probleem), en
                     deze knoppenrij mag nooit onzichtbaar staan te wachten daarop. Extra
                     hoog boven bottom-0 zodat de rij niet over de footer-onderbalk
                     ("Roei- en Zeilvereniging Gouda") heen valt bij volledig scrollen. --}}
                <div class="fixed right-4 z-40 flex flex-wrap items-center justify-end gap-3" style="bottom: 4.5rem;">
                    {{-- "Lid worden" staat voor gasten nu vast in de header; hier
                         alleen nog de contactknop. --}}
                    <x-contact-button />
                    @auth
                        @livewire('feedback-widget', ['pageId' => $page?->id, 'pageVersionId' => $version?->id])
                    @endauth
                </div>
            </div>

            @include('public._footer')
        </div>
        @livewireScripts
    </body>
</html>
