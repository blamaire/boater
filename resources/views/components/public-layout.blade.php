@props(['title' => null, 'previewBanner' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' — RZVG' : config('app.name', 'RZVG') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=alike:400|inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased text-gray-900 bg-white">
        @if ($previewBanner)
            <div class="bg-red-200 text-red-900 text-sm text-center py-2 px-4 font-medium">
                {{ $previewBanner }}
            </div>
        @endif
        <div class="min-h-screen flex flex-col" x-data="{ sidebarOpen: false }">
            <header class="border-b border-gray-200 bg-white">
                <div class="mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
                    <a href="/" class="flex items-center gap-3">
                        <img src="{{ asset('img/branding/rzvg-logo.jpg') }}" alt="RZVG" class="h-10 w-auto">
                        <span class="hidden sm:inline font-display text-xl text-rzvg-600">Roei- en Zeilvereniging Gouda</span>
                    </a>
                    @auth
                        <div class="flex items-center gap-3 text-sm">
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-rzvg-200 text-rzvg-700 hover:bg-rzvg-50">Ledenpagina</a>
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
                    @else
                        <a href="{{ route('login') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">Inloggen</a>
                    @endauth
                </div>

                @include('public._nav')
            </header>

            <div class="flex-1 flex">
                <main class="flex-1 min-w-0">
                    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                        {{ $slot }}
                    </div>
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
            </div>

            @include('public._footer')
        </div>
        @livewireScripts
    </body>
</html>
