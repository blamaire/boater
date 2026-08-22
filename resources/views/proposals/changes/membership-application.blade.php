@php
    /** @var array<string, mixed> $person */
    /** @var array<string, mixed> $address */
    /** @var string|null $membershipTypeName */
    /** @var string|null $overrideReason */
    /** @var bool $isMinor */
    /** @var array<string, mixed>|null $guardian */
    $applicantName = trim(collect([$person['first_name'] ?? null, $person['last_name_prefix'] ?? null, $person['last_name'] ?? null])->filter()->implode(' '));
    $addressLine = trim(collect([$address['street'] ?? null, $address['house_number'] ?? null, $address['house_number_addition'] ?? null])->filter()->implode(' '));
@endphp

<dl class="text-sm text-gray-700 grid grid-cols-[auto,1fr] gap-x-3 gap-y-1">
    <dt class="text-gray-500">Naam</dt>
    <dd>{{ $applicantName !== '' ? $applicantName : '—' }}</dd>

    <dt class="text-gray-500">Geboortedatum</dt>
    <dd>{{ $person['date_of_birth'] ?? '—' }}</dd>

    <dt class="text-gray-500">E-mail</dt>
    <dd>{{ $person['email'] ?? '—' }}</dd>

    <dt class="text-gray-500">Telefoon</dt>
    <dd>{{ $person['phone'] ?? '—' }}</dd>

    <dt class="text-gray-500">Adres</dt>
    <dd>{{ $addressLine !== '' ? $addressLine : '—' }}@if ($addressLine !== ''), @endif{{ $address['postal_code'] ?? '' }} {{ $address['city'] ?? '' }}</dd>

    <dt class="text-gray-500">Lidmaatschapsvorm</dt>
    <dd>
        {{ $membershipTypeName ?? '—' }}
        @if ($overrideReason)
            <span class="block text-xs text-gray-500 italic">Reden afwijkende vorm: {{ $overrideReason }}</span>
        @endif
    </dd>

    @if ($isMinor && $guardian)
        <dt class="text-gray-500">Ouder/verzorger</dt>
        <dd>
            @if (! empty($guardian['existing_person_id']))
                Bestaand lid (#{{ $guardian['existing_person_id'] }})
            @else
                {{ trim(collect([$guardian['first_name'] ?? null, $guardian['last_name_prefix'] ?? null, $guardian['last_name'] ?? null])->filter()->implode(' ')) }}
                @if (! empty($guardian['email']))
                    · {{ $guardian['email'] }}
                @endif
            @endif
        </dd>
    @endif
</dl>
