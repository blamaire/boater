<section class="space-y-4">
    @if (! empty($blockContent['title']))
        <h2 class="font-display text-2xl text-gray-900">{{ $blockContent['title'] }}</h2>
    @endif

    @if ($allowUserFilter)
        <div class="flex flex-wrap gap-3 items-end text-sm">
            @if ($filterCategories->count() > 1)
                <div>
                    <label class="text-xs text-gray-500 block">Categorieën</label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach ($filterCategories as $cat)
                            <label class="inline-flex items-center gap-1 border border-gray-200 rounded px-2 py-1 hover:bg-gray-50">
                                <input type="checkbox" value="{{ $cat->id }}" wire:model.live="userCategoryIds"
                                    class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                                <span>{{ $cat->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
            <div>
                <label class="text-xs text-gray-500 block">Zoeken</label>
                <input type="text" wire:model.live.debounce.300ms="userSearch" placeholder="Titel of locatie…"
                    class="mt-1 border-gray-300 rounded shadow-sm text-sm focus:border-rzvg-600 focus:ring-rzvg-600" />
            </div>
            <label class="inline-flex items-center gap-2 pb-1">
                <input type="checkbox" wire:model.live="userHideHistory"
                    class="rounded border-gray-300 text-rzvg-600 focus:ring-rzvg-600" />
                <span>Historie verbergen</span>
            </label>
        </div>
    @endif

    <ul class="divide-y divide-gray-100 border border-gray-100 rounded">
        @forelse ($activities as $activity)
            <li class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 hover:bg-gray-50">
                <div>
                    <a href="{{ route('activiteit.show', $activity) }}" class="font-medium text-rzvg-700 hover:text-rzvg-900">
                        {{ $activity->title }}
                    </a>
                    <div class="text-xs text-gray-500 mt-0.5">
                        <span>{{ $activity->starts_at->translatedFormat('D j M Y H:i') }}</span>
                        @if ($activity->location)
                            <span class="mx-1">·</span><span>{{ $activity->location }}</span>
                        @endif
                        <span class="mx-1">·</span><span>{{ $activity->category->name }}</span>
                        @if ($activity->capacity)
                            <span class="mx-1">·</span><span>{{ $activity->enrolledCount() }}/{{ $activity->capacity }}</span>
                        @endif
                    </div>
                </div>
                <a href="{{ route('activiteit.show', $activity) }}"
                    class="text-sm text-rzvg-600 hover:text-rzvg-800 whitespace-nowrap">
                    Details →
                </a>
            </li>
        @empty
            <li class="p-6 text-center text-sm text-gray-500 italic">
                Geen activiteiten in de agenda.
            </li>
        @endforelse
    </ul>
</section>
