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
            {{-- Top-bar: logo links, rechts de wisselknop naar de openbare
                 pagina + gebruikersnaam + hamburger. Zelfde plaatsing als de
                 publieke layout (`components/public-layout.blade.php`). --}}
            <header class="bg-white border-b border-gray-200">
                <div class="mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                        <x-application-logo class="block h-10 w-auto" alt="RZVG" />
                        <span class="hidden md:inline font-display text-lg text-rzvg-600">RZVG</span>
                    </a>
                    <div class="flex items-center gap-3 text-sm">
                        <a href="{{ route('public.home') }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-rzvg-200 text-rzvg-700 hover:bg-rzvg-50">Openbare pagina</a>
                        @livewire('portal.notification-bell')
                        <div style="width: 10rem"></div>
                        <span class="text-gray-700 max-w-[10rem] truncate">{{ Auth::user()->name }}</span>
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
            </header>

            <div class="flex-1 flex">
                {{-- Main-kolom bevat de paginatitel-strook, de navigatie en
                     het slot, zodat alle drie meebewegen met de rechter sidebar
                     (aside) en de aside meteen náást de titel-balk begint. --}}
                <div class="flex-1 min-w-0 flex flex-col">
                    <div class="bg-white border-b border-gray-200">
                        <div class="mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            <h1 class="font-display text-3xl text-rzvg-600">
                                {{ $header ?? config('app.name', 'RZVG') }}
                            </h1>
                        </div>
                    </div>

                    {{-- Navigatie met dezelfde zichtbaarheid-bewuste partial/composer
                         (`public._nav` / `PublicNavComposer`) als de publieke site, zodat
                         het beheerde NavItem-menu (/beheer/menu) — incl. submenu's — ook
                         in de portal verschijnt, i.p.v. een losse platte lijst van
                         root-Beperkt-pagina's. --}}
                    @include('public._nav')

                    <main class="flex-1 min-w-0">
                        {{ $slot }}
                    </main>
                </div>

                {{-- Overlay op mobile wanneer drawer open is --}}
                <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
                    class="md:hidden fixed inset-0 bg-black/40 z-30" style="display: none;"></div>

                {{-- Verticale sidebar rechts (inklapbaar op elk schermformaat).
                     Op desktop stretcht de aside mee met de flex-container tot
                     aan de bovenkant van de footer; sticky positionering houdt
                     hem in beeld bij lange pagina's. --}}
                <aside
                    x-show="sidebarOpen"
                    x-cloak
                    class="w-64 shrink-0 bg-white border-l border-gray-200 z-40 fixed md:sticky inset-y-0 right-0 md:top-0 md:self-stretch md:max-h-screen overflow-y-auto">
                    @include('layouts.navigation')
                </aside>

                {{-- bottom via inline style, zie components/public-layout.blade.php voor
                     de uitleg (Tailwind-scan-risico bij een net-nieuwe bottom-*-klasse +
                     ruimte boven de footer-onderbalk). Geen pagina-/versie-context: portaal
                     en beheer zijn geen CMS-pagina's. --}}
                <div class="fixed right-4 z-40 flex items-center gap-3" style="bottom: 4.5rem;">
                    <x-contact-button />
                    @livewire('feedback-widget')
                </div>
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
