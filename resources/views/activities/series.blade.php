<x-public-layout :title="$series->title">
    <div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
        <nav class="text-sm text-gray-500">
            <a href="{{ url('/') }}" class="hover:text-gray-700">Home</a>
            <span class="mx-1">›</span>
            <span class="text-gray-700">{{ $series->category->name }}</span>
        </nav>

        <header class="space-y-2">
            <h1 class="font-display text-3xl text-gray-900">{{ $series->title }}</h1>
            <div class="text-sm text-gray-600 flex flex-wrap gap-x-6 gap-y-1">
                @if ($series->location)
                    <span>{{ $series->location }}</span>
                @endif
                <span>{{ $series->category->name }}</span>
                <span>{{ $series->activities->count() }} voorkomen(s)</span>
            </div>
        </header>

        @if ($series->description)
            <article class="prose max-w-none">
                {!! nl2br(e($series->description)) !!}
            </article>
        @endif

        @if ($series->enrollment_level->allowsSerie())
            <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="font-display text-lg text-gray-900 mb-2">Inschrijven voor de hele reeks</h2>
                @livewire('public.serie-inschrijven', ['seriesId' => $series->id], key('serie-inschrijven-'.$series->id))
            </section>
        @endif

        <section class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <h2 class="font-display text-lg text-gray-900 px-6 pt-6">Voorkomens</h2>
            <ul class="divide-y divide-gray-100 mt-2">
                @foreach ($series->activities as $occurrence)
                    <li class="px-6 py-3 flex items-center justify-between text-sm">
                        <a href="{{ route('activiteit.show', $occurrence) }}" class="text-rzvg-600 hover:text-rzvg-800">
                            {{ $occurrence->starts_at->translatedFormat('l j F Y H:i') }}
                        </a>
                        <span class="text-gray-500">
                            @if ($occurrence->capacity)
                                {{ $occurrence->enrolledCount() }} / {{ $occurrence->capacity }} plekken
                            @endif
                            @if ($series->enrollment_level->allowsPerVoorkomen())
                                <a href="{{ route('activiteit.show', $occurrence) }}" class="ml-2 text-rzvg-600 hover:text-rzvg-800 underline">inschrijven</a>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-public-layout>
