<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-gray-900">
            {{ trim(($person->first_name ?? '').' '.($person->last_name_prefix ? $person->last_name_prefix.' ' : '').($person->last_name ?? '')) }}
        </h1>
        <p class="text-sm text-gray-500">Profielkaart (alleen-lezen).</p>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <a href="{{ route('portal.leden.index') }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">&larr; Terug naar zoeken</a>

        <dl class="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100">
            <div class="px-4 py-3 flex justify-between text-sm">
                <dt class="font-medium text-gray-700">Naam</dt>
                <dd class="text-gray-900">
                    {{ trim(($person->first_name ?? '').' '.($person->last_name_prefix ? $person->last_name_prefix.' ' : '').($person->last_name ?? '')) }}
                </dd>
            </div>
            @if (in_array('email', $visibleFields, true) && $person->email)
                <div class="px-4 py-3 flex justify-between text-sm">
                    <dt class="font-medium text-gray-700">E-mailadres</dt>
                    <dd class="text-gray-900">{{ $person->email }}</dd>
                </div>
            @endif
            @if (in_array('phone', $visibleFields, true) && $person->phone)
                <div class="px-4 py-3 flex justify-between text-sm">
                    <dt class="font-medium text-gray-700">Telefoonnummer</dt>
                    <dd class="text-gray-900">{{ $person->phone }}</dd>
                </div>
            @endif
            @if (in_array('date_of_birth', $visibleFields, true) && $person->date_of_birth)
                <div class="px-4 py-3 flex justify-between text-sm">
                    <dt class="font-medium text-gray-700">Geboortedatum</dt>
                    <dd class="text-gray-900">{{ $person->date_of_birth->format('Y-m-d') }}</dd>
                </div>
            @endif
            @if (in_array('membership_type', $visibleFields, true) && $person->memberships->isNotEmpty())
                <div class="px-4 py-3 flex justify-between text-sm">
                    <dt class="font-medium text-gray-700">Lidmaatschap(pen)</dt>
                    <dd class="text-gray-900">
                        @foreach ($person->memberships as $lidm)
                            <div>{{ $lidm->type?->name }}</div>
                        @endforeach
                    </dd>
                </div>
            @endif
        </dl>
    </div>
</div>
