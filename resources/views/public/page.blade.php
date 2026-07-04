<x-public-layout :title="$page->title">
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
