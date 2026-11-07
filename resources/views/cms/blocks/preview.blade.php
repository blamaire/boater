{{-- Defense-in-depth: saniteer ook hier de vrije-HTML-velden (tekst/html,
     feature_sectie/body), zodat een eventueel gemiste opslagroute (nu of in
     de toekomst) geen ongesaniteerde HTML alsnog op de site laat verschijnen. --}}
@php($c = app(\App\Services\Cms\BlockContentSanitizer::class)->sanitize($block->type, $block->content))
@php($fullBleed = $fullBleed ?? true)
{{-- Knopstijlen (knop-blok, hero). 'wit' en 'wit_omlijnd' zijn bedoeld voor
     banden met een rode of donkere achtergrond. --}}
@php($buttonStyles = [
    'primary' => 'bg-rzvg-500 text-white hover:bg-rzvg-600',
    'secondary' => 'bg-white border-2 border-rzvg-500 text-rzvg-600 hover:bg-rzvg-50',
    'donker' => 'bg-donker-900 text-white hover:bg-donker-700',
    'wit' => 'bg-white text-rzvg-600 hover:bg-rzvg-50',
    'wit_omlijnd' => 'border-2 border-white text-white hover:bg-white/10',
])
@php($buttonBase = 'inline-flex items-center justify-center min-h-11 px-6 py-3 rounded-full font-semibold transition-colors')
@switch($block->type->value)
    @case('tekst')
        <div class="prose prose-lg max-w-prose band-ink">{!! $c['html'] ?? '<em class="text-gray-400">— leeg —</em>' !!}</div>
        @break
    @case('kop')
        @php($level = (int) ($c['level'] ?? 2))
        @php($tag = 'h'.max(1, min(3, $level)))
        @php($sizes = ['1' => 'text-4xl md:text-5xl', '2' => 'text-3xl md:text-[2.625rem]', '3' => 'text-2xl'])
        <{{ $tag }} class="font-display text-balance leading-tight text-gray-900 band-ink {{ $sizes[$tag[1]] ?? 'text-2xl' }}">{{ $c['text'] ?? '' ?: '—' }}</{{ $tag }}>
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
            <div class="flex flex-wrap gap-3">
                <a href="{{ $c['href'] ?: '#' }}" class="{{ $buttonBase }} {{ $buttonStyles[$c['style'] ?? 'primary'] ?? $buttonStyles['primary'] }}">
                    {{ $c['label'] }}
                </a>
                @if (! empty($c['label2']))
                    <a href="{{ $c['href2'] ?: '#' }}" class="{{ $buttonBase }} {{ $buttonStyles[$c['style2'] ?? 'secondary'] ?? $buttonStyles['secondary'] }}">
                        {{ $c['label2'] }}
                    </a>
                @endif
            </div>
        @else
            <span class="text-gray-400 italic">— knop zonder label —</span>
        @endif
        @break
    @case('kaart')
        @php($cardImageUrl = \App\Models\MediaAsset::resolveUrl($c['image_media_asset_id'] ?? null, $c['image_url'] ?? null))
        @php($cardTag = ! empty($c['href']) ? 'a' : 'div')
        @php($linkLabel = ($c['link_label'] ?? '') !== '' ? $c['link_label'] : 'Lees meer')
        @if (($c['variant'] ?? 'foto') === 'compact')
            {{-- Compacte tegel: icoon links, zandkleurige achtergrond — voor
                 secundaire ingangen (bv. "Of zoek je iets anders?"). --}}
            <{{ $cardTag }} @if ($cardTag === 'a') href="{{ $c['href'] }}" @endif
                class="group h-full flex gap-4 items-start rounded-2xl bg-sand-100 p-6 text-gray-900 no-underline transition-colors hover:bg-sand-200">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-rzvg-600">
                    <x-content-icon :name="($c['icon'] ?? '') ?: 'information-circle'" />
                </span>
                <span class="flex flex-col gap-1.5">
                    <span class="font-semibold text-lg">{{ $c['title'] ?? '' ?: '—' }}</span>
                    <span class="text-gray-600">{{ $c['body'] ?? '' }}</span>
                    @if ($cardTag === 'a')
                        <span class="font-semibold text-rzvg-600 group-hover:text-rzvg-700">{{ $linkLabel }}</span>
                    @endif
                </span>
            </{{ $cardTag }}>
        @else
            <{{ $cardTag }} @if ($cardTag === 'a') href="{{ $c['href'] }}" @endif @class([
                'group h-full flex flex-col overflow-hidden rounded-2xl border border-sand-200 bg-white text-gray-900 no-underline transition-shadow',
                'hover:shadow-md' => $cardTag === 'a',
            ])>
                @if ($cardImageUrl)
                    <img src="{{ $cardImageUrl }}" alt="" class="w-full aspect-[16/10] object-cover">
                @else
                    @include('cms.blocks.partials.photo-placeholder', ['aspect' => 'aspect-[16/10]'])
                @endif
                <span class="flex-1 flex flex-col gap-2 p-6">
                    <span class="font-display text-2xl md:text-[1.75rem] leading-tight">{{ $c['title'] ?? '' ?: '—' }}</span>
                    <span class="text-gray-600 flex-1">{{ $c['body'] ?? '' }}</span>
                    @if ($cardTag === 'a')
                        <span class="pt-2 font-semibold text-rzvg-600 group-hover:text-rzvg-700 inline-flex items-center gap-1.5">
                            {{ $linkLabel }} <span aria-hidden="true" class="transition-transform group-hover:translate-x-0.5">→</span>
                        </span>
                    @endif
                </span>
            </{{ $cardTag }}>
        @endif
        @break
    @case('icoon_tekst')
        <div class="flex gap-4 items-start">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-rzvg-50 text-rzvg-600">
                <x-content-icon :name="$c['icon'] ?? 'information-circle'" />
            </span>
            <div class="pt-1">
                <h4 class="font-semibold text-gray-900 band-ink">{{ $c['title'] ?? '' ?: '—' }}</h4>
                <p class="text-gray-600 mt-0.5 band-ink-muted">{{ $c['body'] ?? '' }}</p>
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
        @if (($c['style'] ?? 'standaard') === 'verhaal')
            {{-- Ledenverhaal: kaart met (optionele) foto, citaat, naam en rol. --}}
            @php($photoUrl = \App\Models\MediaAsset::resolveUrl($c['photo_media_asset_id'] ?? null, $c['photo_url'] ?? null))
            <figure class="h-full flex flex-col overflow-hidden rounded-2xl bg-white text-gray-900">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="" class="w-full aspect-[16/10] object-cover">
                @else
                    @include('cms.blocks.partials.photo-placeholder', ['aspect' => 'aspect-[16/10]'])
                @endif
                <div class="flex flex-col gap-4 p-7">
                    <svg aria-hidden="true" class="h-9 w-9 text-rzvg-500" viewBox="0 0 24 24" fill="currentColor"><path d="M9.5 6C6.5 7 4.5 9.6 4.5 13v5h6v-6H7.6c.3-2 1.5-3.4 3.4-4.2L9.5 6Zm9 0c-3 1-5 3.6-5 7v5h6v-6h-2.9c.3-2 1.5-3.4 3.4-4.2L18.5 6Z"/></svg>
                    <blockquote class="font-display text-2xl leading-snug">{{ $c['text'] ?? '' ?: '—' }}</blockquote>
                    @if (! empty($c['source']))
                        <figcaption class="text-gray-600">
                            {{ $c['source'] }}@if (! empty($c['role'])), {{ $c['role'] }}@endif
                        </figcaption>
                    @endif
                </div>
            </figure>
        @else
            <blockquote class="border-l-4 border-rzvg-500 ps-3 italic band-ink">
                <p>{{ $c['text'] ?? '' ?: '—' }}</p>
                @if (! empty($c['source']))
                    <footer class="text-xs text-gray-500 mt-1 not-italic band-ink-muted">— {{ $c['source'] }}</footer>
                @endif
            </blockquote>
        @endif
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
        {{-- Tekst linksonder op de foto (wervende hero). Zonder foto valt de
             achtergrond terug op een donkere tint, zodat de witte tekst
             leesbaar blijft. --}}
        <section @class([
            'relative flex items-end overflow-hidden bg-slate-800 w-full text-white',
            'min-h-[560px] md:min-h-[640px]' => $fullBleed,
            'min-h-64 md:min-h-80' => ! $fullBleed,
        ])>
            @if ($heroUrl)
                <img src="{{ $heroUrl }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute inset-0 bg-black/40" aria-hidden="true"></div>
            @else
                <div class="absolute top-4 right-4 text-sm text-slate-300">
                    <span class="border border-dashed border-slate-400 px-3 py-1.5 rounded">Foto ontbreekt — kies er een in de bibliotheek</span>
                </div>
            @endif
            {{-- max-w-[72rem] = dezelfde breedte als de max-w-6xl-leesbreedte van
                 banden, maar bewust een eigen klasse: de band om een hero is
                 onbegrensd (zie BlockPreviewFullBleedTest). --}}
            <div @class([
                'relative z-10 w-full max-w-[72rem] mx-auto px-4 sm:px-6 lg:px-8',
                'pt-24 pb-16 md:pb-20' => $fullBleed,
                'py-10' => ! $fullBleed,
            ])>
                <div class="max-w-2xl flex flex-col gap-5">
                    @if (! empty($c['kicker']))
                        <p class="text-sm font-semibold uppercase tracking-widest text-rzvg-100">{{ $c['kicker'] }}</p>
                    @endif
                    <h1 @class([
                        'font-display leading-[1.05] text-balance',
                        'text-4xl md:text-6xl' => $fullBleed,
                        'text-3xl md:text-4xl' => ! $fullBleed,
                    ])>{{ $c['title'] ?? '' }}</h1>
                    @if (! empty($c['subtitle']))
                        <p class="text-lg md:text-xl text-white/90 max-w-xl">{{ $c['subtitle'] }}</p>
                    @endif
                    @if (! empty($c['cta_label']) || ! empty($c['cta2_label']))
                        <div class="mt-2 flex flex-wrap gap-3">
                            @if (! empty($c['cta_label']))
                                <a href="{{ $c['cta_href'] ?: '#' }}" class="{{ $buttonBase }} {{ $buttonStyles['primary'] }}">
                                    {{ $c['cta_label'] }}
                                </a>
                            @endif
                            @if (! empty($c['cta2_label']))
                                <a href="{{ $c['cta2_href'] ?: '#' }}" class="{{ $buttonBase }} {{ $buttonStyles['wit_omlijnd'] }}">
                                    {{ $c['cta2_label'] }}
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </section>
        @break
    @case('video')
        @php($videoUrl = \App\Models\MediaAsset::resolveUrl($c['media_asset_id'] ?? null, null))
        <section class="relative bg-black w-full">
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
        <section class="relative bg-white w-full">
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
    @case('agenda')
        @livewire('public.agenda-block', ['blockContent' => $c], key('agenda-'.$block->id))
        @break
    @case('stap')
        <div class="h-full flex flex-col gap-3 rounded-2xl border border-sand-200 bg-white p-7 text-gray-900">
            <span aria-hidden="true" class="flex h-12 w-12 items-center justify-center rounded-full bg-rzvg-500 font-display text-2xl text-white">{{ $c['number'] ?? '' }}</span>
            <h3 class="text-xl font-semibold">
                <span class="sr-only">Stap {{ $c['number'] ?? '' }}: </span>{{ $c['title'] ?? '' ?: '—' }}
            </h3>
            <p class="text-gray-600">{{ $c['body'] ?? '' }}</p>
        </div>
        @break
    @case('prijs')
        <div class="h-full flex flex-col gap-1.5 rounded-2xl border border-sand-200 bg-white p-7 text-gray-900">
            <h3 class="text-lg font-semibold text-gray-600">{{ $c['label'] ?? '' ?: '—' }}</h3>
            <p class="font-display text-5xl leading-tight">{{ $c['amount'] ?? '' }}</p>
            @if (! empty($c['body']))
                <p class="text-gray-600">{{ $c['body'] }}</p>
            @endif
        </div>
        @break
    @default
        <em class="text-gray-400">Onbekend bloktype.</em>
@endswitch
