@php
    $level = (int) ($content['level'] ?? 2);
    $fontSize = match ($level) {
        1 => '24px',
        3 => '16px',
        default => '20px',
    };
    $tag = 'h'.max(1, min(3, $level));
@endphp
<{{ $tag }} style="margin:0 0 12px; color:#111827; font-size:{{ $fontSize }}; line-height:1.3; font-weight:700;">{{ $content['text'] ?? '' }}</{{ $tag }}>
