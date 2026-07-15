<x-public-layout :title="$page->title">
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

    <article>
        @foreach ($version->bands as $band)
            <section @class([
                'grid',
                'grid-cols-1' => $band->layout->value === 1,
                'md:grid-cols-2' => $band->layout->value === 2,
                'md:grid-cols-3' => $band->layout->value === 3,
            ])>
                @for ($col = 0; $col < $band->layout->columnCount(); $col++)
                    <div>
                        @foreach ($band->blocks->where('column_index', $col)->sortBy('sort_order') as $block)
                            @include('cms.blocks.preview', ['block' => $block])
                        @endforeach
                    </div>
                @endfor
            </section>
        @endforeach
    </article>
</x-public-layout>
