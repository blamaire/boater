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
            @if ($activity->activityPage && $activity->activityPage->page->published_version_id && $activity->activityPage->page->isVisibleTo(auth()->user()))
                <div class="text-sm text-gray-600">
                    Onderdeel van
                    <a href="{{ $activity->activityPage->page->publicUrl() }}" class="text-rzvg-600 hover:text-rzvg-800 underline">
                        {{ $activity->activityPage->page->title }}
                    </a>
                </div>
            @endif
            {{-- Een losse activiteit hangt technisch ook aan een ActivitySeries
                (met precies dit ene voorkomen) — dat telt niet als "reeks". --}}
            @if ($activity->series && $activity->series->activities_count > 1)
                <div class="text-sm text-gray-600">
                    Onderdeel van reeks
                    <a href="{{ route('activiteitenreeks.show', $activity->series) }}" class="text-rzvg-600 hover:text-rzvg-800 underline">
                        {{ $activity->series->title }}
                    </a>
                </div>
            @endif

            <x-activity-timeline
                :dates="[['start' => $activity->starts_at, 'end' => $activity->ends_at]]"
                :publish-from="$activity->publish_from"
                :publish-until="$activity->publish_until"
                :enrollment-opens-at="$activity->enrollment_opens_at"
                :enrollment-closes-at="$activity->enrollment_closes_at"
                :cancellation-deadline="$activity->cancellation_deadline"
            />
        </header>

        @if ($activity->description)
            <article class="prose max-w-none">
                {!! $activity->description !!}
            </article>
        @endif

        @if ($activity->files->isNotEmpty())
            <section>
                <h2 class="font-display text-lg text-gray-900 mb-2">Bijlagen</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($activity->files as $file)
                        <li>
                            <a href="{{ $file->displayUrl() }}" class="text-rzvg-600 hover:text-rzvg-800 underline">{{ $file->original_name }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($activity->status !== \App\Enums\ActivityStatus::Cancelled)
            <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                @if ($activity->series && ! $activity->series->enrollment_level->allowsPerVoorkomen())
                    <h2 class="font-display text-lg text-gray-900 mb-2">Inschrijven</h2>
                    <p class="text-sm text-gray-600">
                        Voor dit voorkomen kun je niet los inschrijven — dat kan alleen voor de hele reeks, via
                        <a href="{{ route('activiteitenreeks.show', $activity->series) }}" class="text-rzvg-600 hover:text-rzvg-800 underline">{{ $activity->series->title }}</a>.
                    </p>
                @else
                    <h2 class="font-display text-lg text-gray-900 mb-2">Inschrijven</h2>
                    @livewire('public.activiteit-inschrijven', ['activityId' => $activity->id], key('inschrijven-'.$activity->id))
                @endif
            </section>
        @endif
    </div>
</x-public-layout>
