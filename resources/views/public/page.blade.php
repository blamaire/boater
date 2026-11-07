<x-public-layout :title="$page->title" :preview-banner="$previewLabel ?? null"
    :description="$version->meta_description"
    :og-title="$version->og_title"
    :og-description="$version->og_description"
    :og-image="\App\Models\MediaAsset::resolveUrl($version->og_image_media_asset_id, asset('img/branding/rzvg-logo.jpg'))"
    :canonical-url="rtrim(config('app.url'), '/').$page->publicUrl()"
    :page="$page" :version="$version">
    @unless($preview ?? false)
        @can('pages.propose')
            {{-- §5/§26.4 — leden mogen een wijziging voorstellen. De editor
                 gaat via de goedkeuringsmotor tenzij de gebruiker
                 `pages.publish` heeft (dan direct doorvoeren). --}}
            <div class="mb-4 flex justify-end">
                <a href="{{ route('admin.pages.editor', $page) }}"
                    class="inline-flex items-center gap-1 text-xs px-3 py-1 rounded border border-rzvg-300 text-rzvg-700 hover:bg-rzvg-50">
                    Wijziging voorstellen
                </a>
            </div>
        @endcan
    @endunless

    @php
        // Sectieritme: opeenvolgende banden met dezelfde achtergrond vormen
        // samen één sectie (bv. een kop-band boven een band met drie kaarten).
        // Alleen aan de buitenkant van zo'n groep komt ruime padding; daarbinnen
        // een kleinere tussenruimte. Full-bleed-banden (hero e.d.) breken een groep.
        $fullBleedBlockTypes = [\App\Enums\BlockType::Hero, \App\Enums\BlockType::Video, \App\Enums\BlockType::FeatureSection];
        $bandList = $version->bands->values();
        $bandGroupKeys = $bandList->map(
            fn ($b) => $b->blocks->whereIn('type', $fullBleedBlockTypes)->isNotEmpty() ? null : $b->background->value
        );
    @endphp
    <article>
        @foreach ($bandList as $band)
            @php
                $groupKey = $bandGroupKeys[$loop->index];
                $samePrev = $groupKey !== null && ($bandGroupKeys[$loop->index - 1] ?? null) === $groupKey;
                $sameNext = $groupKey !== null && ($bandGroupKeys[$loop->index + 1] ?? null) === $groupKey;
                // Hero/video/feature-sectieblokken zijn "full-bleed": de band
                // waar ze in staan vult bewust edge-to-edge de breedte van
                // <main> (dus zonder de gecentreerde max-w-6xl-leesbreedte
                // eromheen) — ook bij 2 of 3 kolommen, zodat bv. drie
                // feature-secties naast elkaar de volledige paginabreedte
                // kunnen innemen. De losse blokken proberen zelf nooit meer
                // buiten hun kolom te breken (geen viewport-brede truc meer,
                // zie cms/blocks/preview.blade.php), dus dit is veilig
                // ongeacht het aantal kolommen.
                $fullBleedTypes = [\App\Enums\BlockType::Hero, \App\Enums\BlockType::Video, \App\Enums\BlockType::FeatureSection];
                $bandHasFullBleedBlock = $band->blocks->whereIn('type', $fullBleedTypes)->isNotEmpty();
            @endphp
            {{-- De achtergrond (BandBackground) vult altijd de volle breedte;
                 de inhoud blijft binnen de leesbreedte. Banden zonder
                 full-bleed-blok krijgen een vast verticaal ritme, zodat
                 opeenvolgende banden als rustige secties lezen. --}}
            <div @class([
                $band->background->sectionClasses(),
                'pt-12 md:pt-20' => ! $bandHasFullBleedBlock && ! $samePrev,
                'pb-12 md:pb-20' => ! $bandHasFullBleedBlock && ! $sameNext,
                'pb-8 md:pb-10' => ! $bandHasFullBleedBlock && $sameNext,
            ])>
            <section @class([
                'grid gap-6 md:gap-8',
                'max-w-6xl mx-auto px-4 sm:px-6 lg:px-8' => ! $bandHasFullBleedBlock,
                'grid-cols-1' => $band->layout->value === 1,
                'md:grid-cols-2' => $band->layout->value === 2,
                'md:grid-cols-3' => $band->layout->value === 3,
            ])>
                @for ($col = 0; $col < $band->layout->columnCount(); $col++)
                    <div class="flex flex-col gap-4 min-w-0">
                        @foreach ($band->blocks->where('column_index', $col)->sortBy('sort_order') as $block)
                            @php($blockIsFullBleed = $bandHasFullBleedBlock && in_array($block->type, $fullBleedTypes, true))
                            @if ($bandHasFullBleedBlock && ! $blockIsFullBleed)
                                {{-- De band zelf is hier onbegrensd (full-bleed-blok erin); een
                                     ander blok in dezelfde band (bv. tekst) krijgt daarom zijn
                                     eigen leesbreedte-wrapper, in plaats van edge-to-edge mee te
                                     bleeden. --}}
                                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                                    @include('cms.blocks.preview', ['block' => $block, 'fullBleed' => false])
                                </div>
                            @else
                                @include('cms.blocks.preview', ['block' => $block, 'fullBleed' => $blockIsFullBleed])
                            @endif
                        @endforeach
                    </div>
                @endfor
            </section>
            </div>
        @endforeach
    </article>
</x-public-layout>
