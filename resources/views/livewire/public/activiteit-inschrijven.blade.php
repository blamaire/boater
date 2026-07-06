<div class="space-y-3">
    @if ($statusMessage)
        <div class="rounded-md bg-blue-50 border border-blue-200 text-blue-800 text-sm px-4 py-2" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    @auth
        @if ($eligible->count() > 1)
            <label class="text-sm text-gray-700">
                Voor wie schrijf je in?
                <select wire:model.live="selectedPersonId"
                    class="mt-1 border-gray-300 rounded shadow-sm text-sm focus:border-rzvg-600 focus:ring-rzvg-600">
                    @foreach ($eligible as $person)
                        <option value="{{ $person->id }}">{{ $person->first_name }}{{ $person->last_name_prefix ? ' '.$person->last_name_prefix : '' }} {{ $person->last_name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($currentEnrollment)
            <div class="text-sm">
                Status: <span class="font-medium">{{ $currentEnrollment->status->label() }}</span>
            </div>
            <button type="button" wire:click="cancel"
                class="text-sm px-4 py-2 rounded border border-red-300 text-red-700 hover:bg-red-50">
                Afmelden
            </button>
        @else
            @php
                $hasSpot = $activity->hasFreeSpot();
                $labelBtn = $hasSpot ? 'Inschrijven' : 'Op wachtlijst zetten';
            @endphp
            <button type="button" wire:click="enroll"
                class="text-sm px-4 py-2 rounded bg-rzvg-600 text-white hover:bg-rzvg-700">
                {{ $labelBtn }}
            </button>
            @if (! $hasSpot)
                <p class="text-xs text-gray-500">Deze activiteit is vol. Je komt op de wachtlijst en schuift door zodra iemand afmeldt.</p>
            @endif
        @endif
    @else
        <p class="text-sm text-gray-600">
            <a href="{{ route('login') }}" class="text-rzvg-600 hover:text-rzvg-800 underline">Log in</a> om je in te schrijven.
        </p>
    @endauth
</div>
