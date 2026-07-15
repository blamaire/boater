<x-public-layout :title="$activity->title">
    <div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
        <nav class="text-sm text-gray-500">
            <a href="{{ url('/') }}" class="hover:text-gray-700">Home</a>
            <span class="mx-1">›</span>
            <span class="text-gray-700">{{ $activity->category->name }}</span>
        </nav>

        <header class="space-y-2">
            <h1 class="font-display text-3xl text-gray-900">{{ $activity->title }}</h1>
            <div class="text-sm text-gray-600 flex flex-wrap gap-x-6 gap-y-1">
                <span>{{ $activity->starts_at->translatedFormat('l j F Y H:i') }}@if ($activity->ends_at) – {{ $activity->ends_at->translatedFormat('H:i') }}@endif</span>
                @if ($activity->location)
                    <span>{{ $activity->location }}</span>
                @endif
                <span>{{ $activity->category->name }}</span>
                @if ($activity->capacity)
                    <span>{{ $activity->enrolledCount() }} / {{ $activity->capacity }} plekken</span>
                @endif
            </div>
            @if ($activity->status === \App\Enums\ActivityStatus::Cancelled)
                <div class="rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-2">
                    Deze activiteit is afgelast.
                </div>
            @endif
        </header>

        @if ($activity->description)
            <article class="prose max-w-none">
                {!! nl2br(e($activity->description)) !!}
            </article>
        @endif

        @if ($activity->status !== \App\Enums\ActivityStatus::Cancelled)
            <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="font-display text-lg text-gray-900 mb-2">Inschrijven</h2>
                @livewire('public.activiteit-inschrijven', ['activityId' => $activity->id], key('inschrijven-'.$activity->id))
            </section>
        @endif
    </div>
</x-public-layout>
