@props([
    'dates' => [],
    'publishFrom' => null,
    'publishUntil' => null,
    'enrollmentOpensAt' => null,
    'enrollmentClosesAt' => null,
    'cancellationDeadline' => null,
])

@php
    use Illuminate\Support\Carbon;

    $points = collect();
    foreach ($dates as $d) {
        if (! empty($d['start'])) {
            $points->push($d['start']->getTimestamp());
        }
        if (! empty($d['end'])) {
            $points->push($d['end']->getTimestamp());
        }
    }
    if ($publishFrom) {
        $points->push($publishFrom->getTimestamp());
    }
    if ($publishUntil) {
        $points->push($publishUntil->getTimestamp());
    }
    if ($enrollmentOpensAt) {
        $points->push($enrollmentOpensAt->getTimestamp());
    }
    if ($enrollmentClosesAt) {
        $points->push($enrollmentClosesAt->getTimestamp());
    }
    if ($cancellationDeadline) {
        $points->push($cancellationDeadline->getTimestamp());
    }

    $hasPoints = $points->isNotEmpty();

    if ($hasPoints) {
        $min = $points->min();
        $max = $points->max();
        if ($min === $max) {
            $min -= 43200;
            $max += 43200;
        }
        $span = $max - $min;
        $pct = function (Carbon $carbon) use ($min, $span): float {
            return $span > 0 ? max(0, min(100, ($carbon->getTimestamp() - $min) / $span * 100)) : 0;
        };
    }
@endphp

<div class="space-y-3 text-xs text-gray-600">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
        <span class="inline-flex items-center gap-1.5">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-rzvg-500"></span>
            Wanneer de activiteit plaatsvindt
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-rzvg-200"></span>
            Wanneer deze zichtbaar is
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-400"></span>
            Wanneer ingeschreven kan worden
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="inline-block h-2.5 w-1 rounded-sm bg-amber-500"></span>
            Uiterste annuleringsdatum
        </span>
    </div>

    <div>
        <div class="mb-1 font-medium text-gray-700">Wanneer</div>
        <div class="relative h-3 bg-gray-100 rounded">
            @if ($hasPoints)
                @foreach ($dates as $d)
                    @continue(empty($d['start']))
                    @php
                        $left = $pct($d['start']);
                        $right = ! empty($d['end']) ? $pct($d['end']) : $left;
                        $width = max($right - $left, 1.2);
                    @endphp
                    <div class="absolute top-0 h-3 rounded bg-rzvg-500" style="left: {{ $left }}%; width: {{ $width }}%"
                        title="{{ $d['start']->translatedFormat('d-m-Y H:i') }}@if(! empty($d['end'])) – {{ $d['end']->translatedFormat('d-m-Y H:i') }}@endif"></div>
                @endforeach
            @endif
        </div>
    </div>

    <div>
        <div class="mb-1 font-medium text-gray-700">Zichtbaar (publicatie)</div>
        <div class="relative h-3 bg-gray-100 rounded">
            @if ($hasPoints && ($publishFrom || $publishUntil))
                @php
                    $left = $publishFrom ? $pct($publishFrom) : 0;
                    $right = $publishUntil ? $pct($publishUntil) : 100;
                @endphp
                <div class="absolute top-0 h-3 rounded bg-rzvg-200" style="left: {{ $left }}%; width: {{ max($right - $left, 1.2) }}%"
                    title="@if($publishFrom){{ $publishFrom->translatedFormat('d-m-Y H:i') }}@else Altijd @endif – @if($publishUntil){{ $publishUntil->translatedFormat('d-m-Y H:i') }}@else altijd @endif"></div>
            @endif
        </div>
    </div>

    <div>
        <div class="mb-1 font-medium text-gray-700">Inschrijven / annuleren</div>
        <div class="relative h-3 bg-gray-100 rounded">
            @if ($hasPoints && ($enrollmentOpensAt || $enrollmentClosesAt))
                @php
                    $left = $enrollmentOpensAt ? $pct($enrollmentOpensAt) : 0;
                    $right = $enrollmentClosesAt ? $pct($enrollmentClosesAt) : 100;
                @endphp
                <div class="absolute top-0 h-3 rounded bg-blue-400" style="left: {{ $left }}%; width: {{ max($right - $left, 1.2) }}%"
                    title="Inschrijven: @if($enrollmentOpensAt){{ $enrollmentOpensAt->translatedFormat('d-m-Y H:i') }}@else altijd @endif – @if($enrollmentClosesAt){{ $enrollmentClosesAt->translatedFormat('d-m-Y H:i') }}@else altijd @endif"></div>
            @endif
            @if ($hasPoints && $cancellationDeadline)
                <div class="absolute top-0 h-3 w-1 rounded-sm bg-amber-500" style="left: {{ $pct($cancellationDeadline) }}%"
                    title="Uiterste annuleringsdatum: {{ $cancellationDeadline->translatedFormat('d-m-Y H:i') }}"></div>
            @endif
        </div>
    </div>

    @if ($hasPoints)
        <div class="flex justify-between text-[0.65rem] text-gray-400">
            <span>{{ Carbon::createFromTimestamp($min)->format('d-m-Y') }}</span>
            <span>{{ Carbon::createFromTimestamp($max)->format('d-m-Y') }}</span>
        </div>
    @else
        <div class="text-[0.65rem] text-gray-400 italic">Nog geen datums ingevuld.</div>
    @endif
</div>
