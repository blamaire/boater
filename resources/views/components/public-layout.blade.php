@props(['title' => null])

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
        <div class="min-h-screen flex flex-col">
            <header class="border-b border-gray-200 bg-white">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between gap-6">
                    <a href="/" class="flex items-center gap-3">
                        <img src="{{ asset('img/branding/rzvg-logo.jpg') }}" alt="RZVG" class="h-10 w-auto">
                        <span class="hidden sm:inline font-display text-xl text-rzvg-600">Roei- en Zeilvereniging Gouda</span>
                    </a>
                    @auth
                        <div class="flex items-center gap-3 text-sm">
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-rzvg-200 text-rzvg-700 hover:bg-rzvg-50">Ledenpagina</a>
                            <span class="text-gray-700 max-w-[10rem] truncate">{{ Auth::user()->name }}</span>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">Inloggen</a>
                    @endauth
                </div>

                @include('public._nav')
            </header>

            <main class="flex-1 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                {{ $slot }}
            </main>

            @include('public._footer')
        </div>
        @livewireScripts
    </body>
</html>
