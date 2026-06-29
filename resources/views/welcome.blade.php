<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900">
        <div class="min-h-screen flex flex-col items-center justify-center p-6">
            <div class="max-w-xl w-full bg-white shadow rounded-lg p-8">
                <h1 class="text-2xl font-semibold mb-4">{{ config('app.name') }}</h1>
                <p class="text-gray-700 mb-6">{{ __('Welcome to the website of the rowing and sailing club "Gouda".') }}</p>
                <div class="flex gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="text-indigo-600 hover:text-indigo-800 underline">{{ __('Dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="text-indigo-600 hover:text-indigo-800 underline">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" class="text-indigo-600 hover:text-indigo-800 underline">{{ __('Register') }}</a>
                    @endauth
                </div>
            </div>
        </div>
    </body>
</html>
