<?php

namespace App\Livewire\Admin;

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Models\Band;
use App\Models\Block;
use App\Models\PageVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class PageEditor extends Component
{
    public int $versionId;

    public ?int $editingBlockId = null;

    /** @var array<string, mixed> */
    public array $editingContent = [];

    public function mount(int $versionId): void
    {
        $this->versionId = $versionId;
    }

    #[Computed]
    public function version(): PageVersion
    {
        return PageVersion::query()
            ->with(['page', 'bands.blocks'])
            ->findOrFail($this->versionId);
    }

    public function addBand(int $position, int $layout = 1): void
    {
        $this->guardEditable();
        $bandLayout = BandLayout::from($layout);

        DB::transaction(function () use ($position, $bandLayout) {
            $this->version()->bands()
                ->where('sort_order', '>=', $position)
                ->each(fn (Band $b) => $b->update(['sort_order' => $b->sort_order + 1]));

            $this->version()->bands()->create([
                'zone' => 'hoofd',
                'layout' => $bandLayout,
                'sort_order' => $position,
            ]);
        });

        unset($this->version);
    }

    public function removeBand(int $bandId): void
    {
        $this->guardEditable();
        $band = $this->band($bandId);
        $band->delete();
        unset($this->version);
    }

    public function moveBand(int $bandId, string $direction): void
    {
        $this->guardEditable();
        $band = $this->band($bandId);
        $delta = $direction === 'up' ? -1 : 1;

        $sibling = $this->version()->bands()
            ->where('sort_order', $band->sort_order + $delta)
            ->first();

        if ($sibling === null) {
            return;
        }

        DB::transaction(function () use ($band, $sibling) {
            $a = $band->sort_order;
            $b = $sibling->sort_order;
            $band->update(['sort_order' => $b]);
            $sibling->update(['sort_order' => $a]);
        });

        unset($this->version);
    }

    public function setBandLayout(int $bandId, int $layout): void
    {
        $this->guardEditable();
        $this->band($bandId)->update(['layout' => BandLayout::from($layout)]);
        unset($this->version);
    }

    public function addBlock(int $bandId, int $column, string $type): void
    {
        $this->guardEditable();
        $blockType = BlockType::from($type);

        $maxOrder = Block::query()
            ->where('band_id', $bandId)
            ->where('column_index', $column)
            ->max('sort_order') ?? -1;

        Block::create([
            'band_id' => $bandId,
            'column_index' => $column,
            'sort_order' => $maxOrder + 1,
            'type' => $blockType,
            'content' => $blockType->defaultContent(),
        ]);

        unset($this->version);
    }

    public function removeBlock(int $blockId): void
    {
        $this->guardEditable();
        Block::query()->whereKey($blockId)->delete();
        unset($this->version);
    }

    public function startEditBlock(int $blockId): void
    {
        $this->guardEditable();
        $block = Block::query()->findOrFail($blockId);
        $this->editingBlockId = $block->id;
        $this->editingContent = $this->hydrateForEdit($block);
    }

    public function cancelEditBlock(): void
    {
        $this->editingBlockId = null;
        $this->editingContent = [];
    }

    public function saveBlock(): void
    {
        $this->guardEditable();
        if ($this->editingBlockId === null) {
            return;
        }

        $block = Block::query()->findOrFail($this->editingBlockId);

        Block::query()
            ->whereKey($this->editingBlockId)
            ->update(['content' => $this->serializeOnSave($block->type, $this->editingContent)]);

        $this->editingBlockId = null;
        $this->editingContent = [];
        unset($this->version);
    }

    /**
     * @return array<string, mixed>
     */
    private function hydrateForEdit(Block $block): array
    {
        $content = $block->content;

        if ($block->type === BlockType::Gallery) {
            $content['images_raw'] = collect($content['images'] ?? [])
                ->map(fn ($img) => trim(($img['url'] ?? '').' || '.($img['alt'] ?? ''), ' |'))
                ->implode("\n");
        }

        if ($block->type === BlockType::Accordion) {
            $content['items_raw'] = collect($content['items'] ?? [])
                ->map(fn ($item) => trim(($item['question'] ?? '').' || '.($item['answer'] ?? ''), ' |'))
                ->implode("\n");
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function serializeOnSave(BlockType $type, array $content): array
    {
        if ($type === BlockType::Gallery) {
            $content['images'] = $this->parsePipeLines($content['images_raw'] ?? '', ['url', 'alt']);
            unset($content['images_raw']);
        }

        if ($type === BlockType::Accordion) {
            $content['items'] = $this->parsePipeLines($content['items_raw'] ?? '', ['question', 'answer']);
            unset($content['items_raw']);
        }

        return $content;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, array<string, string>>
     */
    private function parsePipeLines(string $raw, array $keys): array
    {
        return collect(preg_split('/\R/', $raw) ?: [])
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(function ($line) use ($keys) {
                $parts = array_map('trim', preg_split('/\s*(?:\|\||‖)\s*/', $line) ?: [$line]);
                $out = [];
                foreach ($keys as $i => $key) {
                    $out[$key] = $parts[$i] ?? '';
                }

                return $out;
            })
            ->values()
            ->all();
    }

    #[On('media-selected')]
    public function handleMediaSelected(string $contextId, int $assetId, string $url, ?string $thumbnailUrl = null, ?string $alt = null, ?string $originalName = null): void
    {
        if ($this->editingBlockId === null) {
            return;
        }

        match ($contextId) {
            'image' => $this->editingContent = array_merge($this->editingContent, [
                'url' => $url,
                'media_asset_id' => $assetId,
                'alt' => $this->editingContent['alt'] ?? $alt ?? '',
            ]),
            'card-image' => $this->editingContent = array_merge($this->editingContent, [
                'image_url' => $url,
                'image_media_asset_id' => $assetId,
            ]),
            'file' => $this->editingContent = array_merge($this->editingContent, [
                'url' => $url,
                'media_asset_id' => $assetId,
                'label' => $this->editingContent['label'] ?? $originalName ?? '',
            ]),
            default => null,
        };
    }

    public function moveBlock(int $blockId, string $direction): void
    {
        $this->guardEditable();
        $block = Block::query()->findOrFail($blockId);
        $delta = $direction === 'up' ? -1 : 1;

        $sibling = Block::query()
            ->where('band_id', $block->band_id)
            ->where('column_index', $block->column_index)
            ->where('sort_order', $block->sort_order + $delta)
            ->first();

        if ($sibling === null) {
            return;
        }

        DB::transaction(function () use ($block, $sibling) {
            $a = $block->sort_order;
            $b = $sibling->sort_order;
            $block->update(['sort_order' => $b]);
            $sibling->update(['sort_order' => $a]);
        });

        unset($this->version);
    }

    public function render(): View
    {
        return view('livewire.admin.page-editor', [
            'version' => $this->version(),
            'blockTypes' => BlockType::cases(),
            'editingBlock' => $this->editingBlockId !== null
                ? Block::query()->find($this->editingBlockId)
                : null,
        ]);
    }

    private function band(int $bandId): Band
    {
        return Band::query()
            ->whereKey($bandId)
            ->where('page_version_id', $this->versionId)
            ->firstOrFail();
    }

    private function guardEditable(): void
    {
        if (! $this->version()->status->isEditable()) {
            abort(403, 'Deze versie is niet meer bewerkbaar.');
        }
    }
}
