@php($c = $block->content)
@switch($block->type->value)
    @case('tekst')
        <div class="prose max-w-none">{!! $c['html'] ?? '<em class="text-gray-400">— leeg —</em>' !!}</div>
        @break
    @case('kop')
        @php($level = (int) ($c['level'] ?? 2))
        @php($tag = 'h'.max(1, min(3, $level)))
        <{{ $tag }} class="font-display text-{{ ['1' => '2xl', '2' => 'xl', '3' => 'lg'][$tag[1]] ?? 'xl' }}">{{ $c['text'] ?? '' ?: '—' }}</{{ $tag }}>
        @break
    @case('afbeelding')
        @php($imageUrl = \App\Models\MediaAsset::resolveUrl($c['media_asset_id'] ?? null, $c['url'] ?? null))
        @if ($imageUrl)
            <figure>
                <img src="{{ $imageUrl }}" alt="{{ $c['alt'] ?? '' }}" class="max-w-full rounded">
                @if (! empty($c['caption']))
                    <figcaption class="text-xs text-gray-500 mt-1">{{ $c['caption'] }}</figcaption>
                @endif
            </figure>
        @else
            <span class="text-gray-400 italic">— geen afbeelding —</span>
        @endif
        @break
    @case('knop')
        @if (! empty($c['label']))
            <a href="{{ $c['href'] ?: '#' }}" @class([
                'inline-flex items-center px-4 py-2 rounded-md',
                'bg-rzvg-500 text-white hover:bg-rzvg-600' => ($c['style'] ?? 'primary') === 'primary',
                'bg-white border border-rzvg-500 text-rzvg-600 hover:bg-rzvg-50' => ($c['style'] ?? 'primary') === 'secondary',
            ])>
                {{ $c['label'] }}
            </a>
        @else
            <span class="text-gray-400 italic">— knop zonder label —</span>
        @endif
        @break
    @case('kaart')
        @php($cardImageUrl = \App\Models\MediaAsset::resolveUrl($c['image_media_asset_id'] ?? null, $c['image_url'] ?? null))
        <div class="border border-gray-200 rounded p-3 space-y-2">
            @if ($cardImageUrl)
                <img src="{{ $cardImageUrl }}" alt="" class="w-full rounded">
            @endif
            <h3 class="font-display text-lg">{{ $c['title'] ?? '' ?: '—' }}</h3>
            <p class="text-sm text-gray-600">{{ $c['body'] ?? '' }}</p>
            @if (! empty($c['href']))
                <a href="{{ $c['href'] }}" class="text-sm text-rzvg-600 hover:text-rzvg-800">Lees meer →</a>
            @endif
        </div>
        @break
    @case('icoon_tekst')
        <div class="flex gap-3 items-start">
            <span class="text-2xl text-rzvg-500">{{ $c['icon'] ?? '★' }}</span>
            <div>
                <h4 class="font-medium">{{ $c['title'] ?? '' ?: '—' }}</h4>
                <p class="text-sm text-gray-600">{{ $c['body'] ?? '' }}</p>
            </div>
        </div>
        @break
    @case('gallerij')
        <div class="grid grid-cols-3 gap-1">
            @forelse ($c['images'] ?? [] as $img)
                <img src="{{ $img['url'] ?? '' }}" alt="{{ $img['alt'] ?? '' }}" class="w-full h-20 object-cover rounded">
            @empty
                <span class="col-span-3 text-gray-400 italic text-sm">— geen afbeeldingen —</span>
            @endforelse
        </div>
        @break
    @case('accordeon')
        <div class="space-y-1">
            @forelse ($c['items'] ?? [] as $item)
                <details class="border border-gray-200 rounded p-2">
                    <summary class="font-medium text-sm cursor-pointer">{{ $item['question'] ?? '' ?: '—' }}</summary>
                    <p class="text-sm text-gray-600 mt-2">{{ $item['answer'] ?? '' }}</p>
                </details>
            @empty
                <span class="text-gray-400 italic text-sm">— geen items —</span>
            @endforelse
        </div>
        @break
    @case('citaat')
        <blockquote class="border-l-4 border-rzvg-500 ps-3 italic">
            <p>{{ $c['text'] ?? '' ?: '—' }}</p>
            @if (! empty($c['source']))
                <footer class="text-xs text-gray-500 mt-1 not-italic">— {{ $c['source'] }}</footer>
            @endif
        </blockquote>
        @break
    @case('video_embed')
        @if (! empty($c['embed_url']))
            <div class="aspect-video">
                <iframe src="{{ $c['embed_url'] }}" class="w-full h-full rounded" frameborder="0" allowfullscreen></iframe>
            </div>
        @else
            <span class="text-gray-400 italic">— geen embed URL —</span>
        @endif
        @break
    @case('bestand')
        @php($fileUrl = \App\Models\MediaAsset::resolveUrl($c['media_asset_id'] ?? null, $c['url'] ?? null))
        @if ($fileUrl)
            <a href="{{ $fileUrl }}" class="inline-flex items-center gap-2 text-rzvg-600 hover:text-rzvg-800">
                <span>📄</span>
                <span>{{ $c['label'] ?: $fileUrl }}</span>
                @if (! empty($c['size']))
                    <span class="text-xs text-gray-500">({{ $c['size'] }})</span>
                @endif
            </a>
        @else
            <span class="text-gray-400 italic">— geen bestand —</span>
        @endif
        @break
    @case('scheiding')
        <hr class="border-gray-200 my-2">
        @break
    @default
        <em class="text-gray-400">Onbekend bloktype.</em>
@endswitch
