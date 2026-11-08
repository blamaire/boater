{{-- Ontwikkelpad: server-side SVG. Desktop = niveaus als kolommen, mobiel = stappen onder elkaar. --}}
@php
    $graph = app(\App\Services\Cms\PathwayGraph::class);
    $pw = $graph->normalize($content);
    $uid = $block->id ?? spl_object_id($block);
    $labels = collect($pw['nodes'])->pluck('label', 'key');
    $summary = collect($pw['nodes'])->map(fn ($n) => $n['label'])->implode(', ');
    try {
        $layouts = ['desktop' => $graph->layout($pw, true), 'mobiel' => $graph->layout($pw, false)];
        $error = null;
    } catch (InvalidArgumentException $e) {
        $layouts = [];
        $error = $e->getMessage();
    }
@endphp
@if ($pw['nodes'] === [])
    <em class="text-gray-400">Nog geen stappen.</em>
@else
    @if ($error !== null)
        <em class="text-gray-400">Het ontwikkelpad kan niet worden getoond: {{ $error }}</em>
    @else
        @foreach ([['desktop', true, 'hidden md:block'], ['mobiel', false, 'md:hidden']] as [$variant, $horizontal, $visibility])
            @php($g = $layouts[$variant])
            @php($markerId = "pw-{$variant}-{$uid}")
            <svg viewBox="0 0 {{ $g['width'] }} {{ $g['height'] }}" role="img"
                aria-label="Ontwikkelpad met de stappen: {{ $summary }}"
                class="{{ $visibility }} w-full h-auto {{ $horizontal ? '' : 'max-w-sm' }}" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <marker id="{{ $markerId }}" viewBox="0 0 10 10" refX="10" refY="5" markerWidth="10" markerHeight="10" markerUnits="userSpaceOnUse" orient="auto">
                        <path d="M0 0 L10 5 L0 10 z" fill="#b51e20" />
                    </marker>
                </defs>
                @foreach ($g['edges'] as $edge)
                    <path d="{{ $edge['d'] }}" fill="none" stroke="#b51e20" stroke-width="2" marker-end="url(#{{ $markerId }})" />
                @endforeach
                @foreach ($g['nodes'] as $node)
                    @php($textY = $node['y'] + 10 + 15)
                    @if ($node['href'])
                        <a href="{{ $node['href'] }}" class="group">
                    @endif
                    <g>
                        <rect x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="{{ $g['nodeWidth'] }}" height="{{ $g['nodeHeight'] }}" rx="12"
                            fill="#ffffff" stroke="#e12628" stroke-width="2" @class(['group-hover:fill-rzvg-50' => $node['href']]) />
                        <text x="{{ $node['x'] + $g['nodeWidth'] / 2 }}" y="{{ $textY }}" text-anchor="middle" font-size="16" font-weight="700" fill="#111827">
                            @foreach ($node['lines'] as $i => $line)
                                <tspan x="{{ $node['x'] + $g['nodeWidth'] / 2 }}" dy="{{ $i === 0 ? 0 : 19 }}">{{ $line }}</tspan>
                            @endforeach
                        </text>
                        @if ($node['sublines'] !== [])
                            <text x="{{ $node['x'] + $g['nodeWidth'] / 2 }}" y="{{ $textY + (count($node['lines']) - 1) * 19 + 20 }}" text-anchor="middle" font-size="13" fill="#4b5563">
                                @foreach ($node['sublines'] as $i => $line)
                                    <tspan x="{{ $node['x'] + $g['nodeWidth'] / 2 }}" dy="{{ $i === 0 ? 0 : 16 }}">{{ $line }}</tspan>
                                @endforeach
                            </text>
                        @endif
                    </g>
                    @if ($node['href'])
                        </a>
                    @endif
                @endforeach
            </svg>
        @endforeach
    @endif

    <ol class="sr-only">
        @foreach ($pw['nodes'] as $node)
            @php($next = collect($pw['edges'])->where('from', $node['key'])->map(fn ($e) => $labels[$e['to']] ?? null)->filter()->implode(', '))
            <li>{{ $node['label'] }}@if ($node['sub']) — {{ $node['sub'] }}@endif.@if ($next) Daarna: {{ $next }}.@endif</li>
        @endforeach
    </ol>
@endif
