@php
    /** @var \App\Models\ReservableObject|null $object */
    /** @var \App\Models\Person|null $beneficiary */
    /** @var \App\Models\Person|null $requestedBy */
    /** @var \Illuminate\Support\Carbon|null $startsAt */
    /** @var \Illuminate\Support\Carbon|null $endsAt */
    /** @var string|null $note */
    /** @var list<array{rule_id?: mixed, rule_name?: string, message?: string}> $violations */
@endphp

<dl class="text-sm text-gray-700 grid grid-cols-[auto,1fr] gap-x-3 gap-y-1">
    <dt class="text-gray-500">Object</dt>
    <dd>{{ $object?->name ?? 'onbekend object' }}</dd>

    <dt class="text-gray-500">Periode</dt>
    <dd>{{ $startsAt?->format('d-m-Y H:i') }} &ndash; {{ $endsAt?->format('d-m-Y H:i') }}</dd>

    <dt class="text-gray-500">Voor</dt>
    <dd>{{ $beneficiary?->fullName() ?? 'onbekend' }}</dd>

    @if ($requestedBy && (! $beneficiary || $requestedBy->id !== $beneficiary->id))
        <dt class="text-gray-500">Ingediend door</dt>
        <dd>{{ $requestedBy->fullName() }}</dd>
    @endif

    @if ($note)
        <dt class="text-gray-500">Notitie</dt>
        <dd>{{ $note }}</dd>
    @endif

    @if (! empty($violations))
        <dt class="text-gray-500">Overtredingen</dt>
        <dd>
            <ul class="list-disc list-inside">
                @foreach ($violations as $violation)
                    <li>{{ $violation['message'] ?? ($violation['rule_name'] ?? 'onbekende regel') }}</li>
                @endforeach
            </ul>
        </dd>
    @endif
</dl>
