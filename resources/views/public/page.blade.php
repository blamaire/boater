<x-public-layout :title="$page->title">
    <article class="space-y-6 py-8">
        @foreach ($version->bands as $band)
            <section @class([
                'grid gap-4',
                'grid-cols-1' => $band->layout->value === 1,
                'md:grid-cols-2' => $band->layout->value === 2,
                'md:grid-cols-3' => $band->layout->value === 3,
            ])>
                @for ($col = 0; $col < $band->layout->columnCount(); $col++)
                    <div class="space-y-3">
                        @foreach ($band->blocks->where('column_index', $col)->sortBy('sort_order') as $block)
                            <div>
                                @include('cms.blocks.preview', ['block' => $block])
                            </div>
                        @endforeach
                    </div>
                @endfor
            </section>
        @endforeach
    </article>
</x-public-layout>
