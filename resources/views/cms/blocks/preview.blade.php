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
    @case('hero')
        @php($heroUrl = \App\Models\MediaAsset::resolveUrl($c['media_asset_id'] ?? null, null))
        <section class="relative w-screen left-1/2 -translate-x-1/2 h-screen min-h-[560px] flex items-center justify-center overflow-hidden bg-gray-300">
            @if ($heroUrl)
                <img src="{{ $heroUrl }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            @else
                <div class="absolute inset-0 flex items-center justify-center text-gray-500 text-lg">
                    <span class="border-2 border-dashed border-gray-500 px-6 py-3 rounded">Foto ontbreekt — kies er een in de bibliotheek</span>
                </div>
            @endif
            <div class="relative z-10 max-w-3xl text-center px-6 py-8">
                <h1 class="font-display text-4xl md:text-6xl text-white leading-tight drop-shadow-lg">{{ $c['title'] ?? '' }}</h1>
                @if (! empty($c['subtitle']))
                    <p class="mt-4 text-lg text-white drop-shadow-md">{{ $c['subtitle'] }}</p>
                @endif
                <div class="mt-6 flex flex-wrap gap-3 justify-center">
                    @if (! empty($c['cta_label']))
                        <a href="{{ $c['cta_href'] ?: '#' }}" class="inline-flex items-center px-6 py-3 rounded bg-rzvg-600 text-white font-medium shadow hover:bg-rzvg-700">
                            {{ $c['cta_label'] }}
                        </a>
                    @endif
                    @if (! empty($c['cta2_label']))
                        <a href="{{ $c['cta2_href'] ?: '#' }}" class="inline-flex items-center px-6 py-3 rounded border border-rzvg-600 text-rzvg-700 hover:bg-rzvg-50">
                            {{ $c['cta2_label'] }}
                        </a>
                    @endif
                </div>
            </div>
        </section>
        @break
    @case('video')
        @php($videoUrl = \App\Models\MediaAsset::resolveUrl($c['media_asset_id'] ?? null, null))
        <section class="relative w-screen left-1/2 -translate-x-1/2 bg-black">
            @if ($videoUrl)
                <video src="{{ $videoUrl }}"
                    autoplay muted loop playsinline preload="metadata"
                    class="block w-full h-auto"></video>
            @else
                <div class="aspect-video flex items-center justify-center border-2 border-dashed border-gray-500 text-gray-300">
                    Video ontbreekt — kies er een in de bibliotheek
                </div>
            @endif
        </section>
        @break
    @case('feature_sectie')
        @php($featUrl = \App\Models\MediaAsset::resolveUrl($c['media_asset_id'] ?? null, null))
        @php($side = ($c['image_side'] ?? 'left') === 'right' ? 'right' : 'left')
        <section class="relative w-screen left-1/2 -translate-x-1/2 bg-white">
            <div class="grid grid-cols-1 md:grid-cols-2 items-stretch">
                <div @class(['aspect-[4/3] md:aspect-auto md:min-h-[28rem] overflow-hidden bg-gray-200', 'md:order-2' => $side === 'right'])>
                    @if ($featUrl)
                        <img src="{{ $featUrl }}" alt="" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center border border-dashed border-gray-400 text-gray-500">
                            Foto ontbreekt
                        </div>
                    @endif
                </div>
                <div @class(['flex items-center px-6 sm:px-10 lg:px-16 py-16', 'md:order-1' => $side === 'right'])>
                    <div class="max-w-xl">
                    <h2 class="font-display text-3xl md:text-4xl text-rzvg-700">{{ $c['title'] ?? '' }}</h2>
                    @if (! empty($c['body']))
                        <div class="mt-4 prose max-w-none text-gray-700">{!! $c['body'] !!}</div>
                    @endif
                    @if (! empty($c['cta_label']))
                        <div class="mt-6">
                            <a href="{{ $c['cta_href'] ?: '#' }}" class="inline-flex items-center px-5 py-2.5 rounded border border-rzvg-600 text-rzvg-700 hover:bg-rzvg-50">
                                {{ $c['cta_label'] }} →
                            </a>
                        </div>
                    @endif
                    </div>
                </div>
            </div>
        </section>
        @break
    @default
        <em class="text-gray-400">Onbekend bloktype.</em>
@endswitch
