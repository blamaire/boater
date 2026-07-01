<?php

namespace App\Services\Cms;

use App\Models\Block;
use App\Models\PageVersion;

/**
 * Three-way merge voor pagina-versies op blok-niveau.
 *
 * Elk blok in de basisversie krijgt bij vertakking een origin_block_id-verwijzing;
 * daarmee kunnen we identieke blokken tussen concurrente concept-versies matchen.
 *
 * Per top-level content-key wordt vergeleken. Wijzigt A alleen 'title', B alleen
 * 'body' van hetzelfde kaartblok → automerge. Wijzigen beide 'title' verschillend
 * → conflict.
 */
class ConflictDetector
{
    public function detect(PageVersion $mine, PageVersion $theirs, ?PageVersion $base): ConflictReport
    {
        $baseBlocks = $base !== null ? $this->indexBlocks($base) : [];
        $myBlocks = $this->indexBlocks($mine);
        $theirBlocks = $this->indexBlocks($theirs);

        $originIds = array_unique(array_merge(
            array_keys($myBlocks),
            array_keys($theirBlocks),
            array_keys($baseBlocks),
        ));

        $entries = collect();

        foreach ($originIds as $originId) {
            /** @var Block|null $baseBlock */
            $baseBlock = $baseBlocks[$originId] ?? null;
            /** @var Block|null $mineBlock */
            $mineBlock = $myBlocks[$originId] ?? null;
            /** @var Block|null $theirsBlock */
            $theirsBlock = $theirBlocks[$originId] ?? null;

            $entries->push($this->classifyBlock($originId, $baseBlock, $mineBlock, $theirsBlock));
        }

        return new ConflictReport($entries);
    }

    /**
     * @return array<int, Block> — indexed by origin_block_id (of eigen id als root)
     */
    private function indexBlocks(PageVersion $version): array
    {
        $out = [];
        foreach ($version->bands()->with('blocks')->get() as $band) {
            foreach ($band->blocks as $block) {
                $key = $block->origin_block_id ?? $block->id;
                $out[$key] = $block;
            }
        }

        return $out;
    }

    /**
     * @param  Block|null  $base
     * @param  Block|null  $mine
     * @param  Block|null  $theirs
     */
    private function classifyBlock(int $originId, $base, $mine, $theirs): BlockDiff
    {
        $signature = ($base ? 'B' : '-').($mine ? 'M' : '-').($theirs ? 'T' : '-');

        // Zelfde origin_block_id in mine en theirs zonder base = zeldzaam (parallel toegevoegd);
        // behandel als edit-edit-conflict.
        if ($signature === '-MT') {
            return new BlockDiff($originId, 'conflict_edit_edit', null, $mine, $theirs, array_keys($mine->content));
        }

        if ($signature === '-M-') {
            return new BlockDiff($originId, 'added_by_me', null, $mine, null, []);
        }

        if ($signature === '--T') {
            return new BlockDiff($originId, 'added_by_theirs', null, null, $theirs, []);
        }

        if ($signature === 'B--') {
            return new BlockDiff($originId, 'deleted_both', $base, null, null, []);
        }

        if ($signature === 'B-T') {
            return $this->contentEquals($theirs->content, $base->content)
                ? new BlockDiff($originId, 'deleted_by_me', $base, null, $theirs, [])
                : new BlockDiff($originId, 'conflict_delete_edit', $base, null, $theirs, []);
        }

        if ($signature === 'BM-') {
            return $this->contentEquals($mine->content, $base->content)
                ? new BlockDiff($originId, 'deleted_by_theirs', $base, $mine, null, [])
                : new BlockDiff($originId, 'conflict_delete_edit', $base, $mine, null, []);
        }

        // 'BMT' — alle drie bestaan; combineer changed-flags in een 2-char state
        $mineJson = json_encode($mine->content);
        $theirsJson = json_encode($theirs->content);
        $baseJson = json_encode($base->content);
        $state = ($mineJson !== $baseJson ? 'M' : '-').($theirsJson !== $baseJson ? 'T' : '-');

        return match ($state) {
            '--' => new BlockDiff($originId, 'unchanged', $base, $mine, $theirs, []),
            'M-' => new BlockDiff($originId, 'edited_by_me', $base, $mine, $theirs, []),
            '-T' => new BlockDiff($originId, 'edited_by_theirs', $base, $mine, $theirs, []),
            default => $this->classifyKeyLevel($originId, $base, $mine, $theirs),
        };
    }

    private function classifyKeyLevel(int $originId, ?Block $base, Block $mine, Block $theirs): BlockDiff
    {
        $conflictingKeys = [];
        $baseContent = $base !== null ? $base->content : [];
        $myContent = $mine->content;
        $theirContent = $theirs->content;

        $keys = collect(array_keys($myContent))
            ->merge(array_keys($theirContent))
            ->merge(array_keys($baseContent))
            ->unique();

        foreach ($keys as $key) {
            $mineKey = $myContent[$key] ?? null;
            $theirsKey = $theirContent[$key] ?? null;
            $baseKey = $baseContent[$key] ?? null;

            $mineChanged = $mineKey !== $baseKey;
            $theirsChanged = $theirsKey !== $baseKey;

            if ($mineChanged && $theirsChanged && $mineKey !== $theirsKey) {
                $conflictingKeys[] = $key;
            }
        }

        $type = $conflictingKeys === [] ? 'auto_mergeable' : 'conflict_edit_edit';

        return new BlockDiff($originId, $type, $base, $mine, $theirs, $conflictingKeys);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function contentEquals(array $a, array $b): bool
    {
        return json_encode($a) === json_encode($b);
    }
}
