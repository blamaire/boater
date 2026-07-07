<?php

namespace App\Livewire\Admin;

use App\Enums\BandLayout;
use App\Enums\BlockType;
use App\Enums\PageVisibility;
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

    public bool $showJsonPanel = false;

    public string $importJsonText = '';

    public ?string $jsonStatus = null;

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
    public function handleMediaSelected(string $contextId, int $assetId, string $url, string $thumbnailUrl = '', string $alt = '', string $originalName = ''): void
    {
        if ($this->editingBlockId === null) {
            return;
        }

        match ($contextId) {
            'image' => $this->editingContent = array_merge($this->editingContent, [
                'url' => $url,
                'media_asset_id' => $assetId,
                'alt' => $this->editingContent['alt'] ?? $alt,
            ]),
            'card-image' => $this->editingContent = array_merge($this->editingContent, [
                'image_url' => $url,
                'image_media_asset_id' => $assetId,
            ]),
            'file' => $this->editingContent = array_merge($this->editingContent, [
                'url' => $url,
                'media_asset_id' => $assetId,
                'label' => $this->editingContent['label'] ?? $originalName,
            ]),
            'hero-image', 'video-asset', 'feature-image' => $this->editingContent = array_merge($this->editingContent, [
                'media_asset_id' => $assetId,
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

    public function toggleJsonPanel(): void
    {
        $this->showJsonPanel = ! $this->showJsonPanel;
        if ($this->showJsonPanel) {
            $this->importJsonText = $this->currentJson();
            $this->jsonStatus = null;
        }
    }

    /**
     * Serialiseert de huidige versie tot een pagina-broncode JSON: de
     * complete banden- en blokstructuur zonder database-IDs, zodat je 'm
     * elders terug kunt importeren.
     */
    public function currentJson(): string
    {
        $version = $this->version();
        $version->load(['bands.blocks']);

        $payload = [
            'bands' => $version->bands->sortBy('sort_order')->values()->map(fn (Band $band) => [
                'zone' => $band->zone,
                'layout' => $band->layout->value,
                'sort_order' => $band->sort_order,
                'blocks' => $band->blocks->sortBy('sort_order')->values()->map(fn (Block $block) => [
                    'type' => $block->type->value,
                    'column_index' => $block->column_index,
                    'sort_order' => $block->sort_order,
                    'content' => $block->content,
                    'visibility' => $block->visibility->value,
                ])->all(),
            ])->all(),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * Vervangt de banden- en blokstructuur van de huidige (bewerkbare) versie
     * door de meegegeven JSON. Bestaande bands en blocks van deze versie
     * worden verwijderd — dit is een replace-import, geen merge.
     */
    public function applyImportedJson(): void
    {
        $this->guardEditable();

        $decoded = json_decode($this->importJsonText, true);
        if (! is_array($decoded) || ! isset($decoded['bands']) || ! is_array($decoded['bands'])) {
            $this->jsonStatus = 'Kon de JSON niet lezen. Zorg dat je een object met een "bands"-lijst plakt.';

            return;
        }

        // Valideer eerst alle enum-waardes zodat we geen half-toegepaste
        // structuur achterlaten in de DB én de gebruiker een nette melding
        // krijgt in plaats van een 500.
        try {
            $prepared = $this->validateAndPrepareBands($decoded['bands']);
        } catch (\RuntimeException $e) {
            $this->jsonStatus = 'Kon de JSON niet toepassen: '.$e->getMessage();

            return;
        }

        // Loskoppelen van de computed-cache, zodat we op verse DB-state werken
        // (niet op een gedecacheerde collection uit de vorige render).
        unset($this->version);
        $version = $this->version();

        DB::transaction(function () use ($prepared, $version): void {
            foreach ($version->bands as $band) {
                $band->delete();
            }

            foreach ($prepared as $bandData) {
                $band = $version->bands()->create([
                    'zone' => $bandData['zone'],
                    'layout' => $bandData['layout'],
                    'sort_order' => $bandData['sort_order'],
                ]);

                foreach ($bandData['blocks'] as $blockData) {
                    Block::create([
                        'band_id' => $band->id,
                        'column_index' => $blockData['column_index'],
                        'sort_order' => $blockData['sort_order'],
                        'type' => $blockData['type'],
                        'content' => $blockData['content'],
                        'visibility' => $blockData['visibility'],
                    ]);
                }
            }
        });

        // Nogmaals cache wissen zodat de volgende render ook de zojuist
        // gemaakte bands en blocks laadt.
        unset($this->version);
        // Paneel dicht — anders zie je alleen de textarea en niet de
        // ge-updatete blokken eronder.
        $this->showJsonPanel = false;
        $this->importJsonText = '';
        $this->jsonStatus = 'Broncode toegepast op deze conceptversie.';
    }

    /**
     * Zet ruwe JSON-arrays om in gevalideerde payload-arrays voor Band en
     * Block. Gooit een RuntimeException met duidelijke boodschap zodra er
     * een onbekende enum-waarde (layout, type, visibility) wordt geraakt.
     *
     * @param  array<int, mixed>  $rawBands
     * @return array<int, array{zone: string, layout: BandLayout, sort_order: int, blocks: array<int, array{column_index: int, sort_order: int, type: BlockType, content: array<string, mixed>, visibility: PageVisibility}>}>
     */
    private function validateAndPrepareBands(array $rawBands): array
    {
        $result = [];
        foreach ($rawBands as $bandIndex => $bandData) {
            if (! is_array($bandData)) {
                throw new \RuntimeException("band #{$bandIndex} is geen object.");
            }
            $layoutValue = (int) ($bandData['layout'] ?? 1);
            $layout = BandLayout::tryFrom($layoutValue);
            if ($layout === null) {
                throw new \RuntimeException("band #{$bandIndex}: onbekende layout [{$layoutValue}].");
            }

            $blocksRaw = $bandData['blocks'] ?? [];
            if (! is_array($blocksRaw)) {
                throw new \RuntimeException("band #{$bandIndex}: 'blocks' is geen lijst.");
            }
            $blocks = [];
            foreach ($blocksRaw as $blockIndex => $blockData) {
                if (! is_array($blockData)) {
                    throw new \RuntimeException("band #{$bandIndex}, block #{$blockIndex} is geen object.");
                }
                $typeValue = (string) ($blockData['type'] ?? '');
                $type = BlockType::tryFrom($typeValue);
                if ($type === null) {
                    throw new \RuntimeException("band #{$bandIndex}, block #{$blockIndex}: onbekend type [{$typeValue}].");
                }
                $visibilityValue = (string) ($blockData['visibility'] ?? 'public');
                $visibility = PageVisibility::tryFrom($visibilityValue);
                if ($visibility === null) {
                    throw new \RuntimeException("band #{$bandIndex}, block #{$blockIndex}: onbekende visibility [{$visibilityValue}].");
                }

                $blocks[] = [
                    'column_index' => (int) ($blockData['column_index'] ?? 0),
                    'sort_order' => (int) ($blockData['sort_order'] ?? 0),
                    'type' => $type,
                    'content' => (array) ($blockData['content'] ?? []),
                    'visibility' => $visibility,
                ];
            }

            $result[] = [
                'zone' => (string) ($bandData['zone'] ?? 'hoofd'),
                'layout' => $layout,
                'sort_order' => (int) ($bandData['sort_order'] ?? 0),
                'blocks' => $blocks,
            ];
        }

        return $result;
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
