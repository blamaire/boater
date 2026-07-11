<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RZVG') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=alike:400|inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased text-gray-900 bg-gray-50">
        <div class="min-h-screen flex flex-col" x-data="{ sidebarOpen: window.innerWidth >= 768 }">
            {{-- Top-bar: logo links --}}
            <header class="bg-white border-b border-gray-200">
                <div class="mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                        <x-application-logo class="block h-10 w-auto" alt="RZVG" />
                        <span class="hidden md:inline font-display text-lg text-rzvg-600">RZVG</span>
                    </a>
                </div>
            </header>

            {{-- Vaste pagina-header: titel + gebruikersnaam + hamburger/kruis.
                 Container-breedte is gelijk aan de logoheader hierboven: volle
                 breedte met dezelfde px-*-schaal, zodat de titel netjes recht
                 onder het logo begint zonder extra witruimte links/rechts. --}}
            <div class="bg-white border-b border-gray-200">
                <div class="mx-auto py-6 px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-3">
                    <h1 class="font-display text-3xl text-rzvg-600">
                        {{ $header ?? config('app.name', 'RZVG') }}
                    </h1>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-700 max-w-[10rem] truncate">{{ Auth::user()->name }}</span>
                        <button @click="sidebarOpen = ! sidebarOpen"
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100"
                            aria-label="Menu openen/sluiten">
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{'hidden': sidebarOpen, 'inline-flex': ! sidebarOpen }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{'hidden': ! sidebarOpen, 'inline-flex': sidebarOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Portaalbalk met beperkt-zichtbare CMS-pagina's. Composer
                 `PortalPagesComposer` vult `$portalPages` alleen als de user
                 er toegang toe zou hebben (ingelogd + actief lid, of inzage-
                 rol); anders blijft de balk uit beeld. Opmaak volgt het
                 publieke topmenu (grijstinten) — children als hover-uitklap. --}}
            @if (! empty($portalPages) && $portalPages->isNotEmpty())
                <nav class="bg-white border-b border-gray-200">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <ul class="flex flex-wrap items-center gap-2 py-2 text-sm">
                            @foreach ($portalPages as $portalPage)
                                <li class="relative group">
                                    @if ($portalPage->children->isEmpty())
                                        <a href="{{ $portalPage->publicUrl() }}" class="px-2 py-1 hover:text-gray-900">{{ $portalPage->title }}</a>
                                    @else
                                        <a href="{{ $portalPage->publicUrl() }}" class="px-2 py-1 hover:text-gray-900 inline-flex items-center gap-1">
                                            {{ $portalPage->title }}
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                                        </a>
                                        <ul class="hidden group-hover:block group-focus-within:block absolute z-10 left-0 top-full bg-white border border-gray-200 rounded-md shadow-lg min-w-[12rem] py-1">
                                            @foreach ($portalPage->children as $child)
                                                <li><a href="{{ $child->publicUrl() }}" class="block px-3 py-1.5 hover:bg-gray-50">{{ $child->title }}</a></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </nav>
            @endif

            <div class="flex-1 flex">
                <main class="flex-1 min-w-0">
                    {{ $slot }}
                </main>

                {{-- Overlay op mobile wanneer drawer open is --}}
                <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
                    class="md:hidden fixed inset-0 bg-black/40 z-30" style="display: none;"></div>

                {{-- Verticale sidebar rechts (inklapbaar op elk schermformaat) --}}
                <aside
                    x-show="sidebarOpen"
                    x-cloak
                    class="w-64 shrink-0 bg-white border-l border-gray-200 z-40 fixed md:sticky inset-y-0 right-0 md:top-0 md:self-start md:max-h-screen overflow-y-auto">
                    @include('layouts.navigation')
                </aside>
            </div>

            <footer class="bg-white border-t border-gray-200 mt-auto">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between text-sm text-gray-500">
                    <span>&copy; {{ now()->year }} RZVG</span>
                    <span class="font-display text-rzvg-600">Roei- en Zeilvereniging Gouda</span>
                </div>
            </footer>
        </div>
        @livewireScripts
    </body>
</html>
