<?php

namespace App\Services\Cms;

use App\Enums\PageVersionStatus;
use App\Http\Controllers\Admin\PageEditorController;
use App\Livewire\Admin\PageConflictResolver;
use App\Models\Block;
use App\Models\PageVersion;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

/**
 * Bouwt een nieuwe conceptversie die twee vertakte {@see PageVersion}'s samenvoegt
 * op basis van een {@see ConflictReport}: niet-conflicterende blokken worden
 * automatisch gekozen, conflicterende blokken volgen expliciete keuzes.
 *
 * Gedeeld door de handmatige conflictresolver ({@see PageConflictResolver})
 * en de automatische rebase bij het indienen zonder overlappend conflict
 * ({@see PageEditorController}).
 */
class PageVersionMerger
{
    /**
     * @param  array<int, string>  $choices
     * @param  array<int, string>  $manualJson
     */
    public function merge(PageVersion $mine, PageVersion $theirs, ConflictReport $report, Person $createdBy, array $choices = [], array $manualJson = []): PageVersion
    {
        return DB::transaction(function () use ($mine, $theirs, $report, $createdBy, $choices, $manualJson) {
            $latest = PageVersion::query()
                ->where('page_id', $mine->page_id)
                ->orderByDesc('version_no')
                ->first();
            $nextVersionNo = ($latest !== null ? $latest->version_no : 0) + 1;

            $resolved = PageVersion::create([
                'page_id' => $mine->page_id,
                'version_no' => $nextVersionNo,
                'status' => PageVersionStatus::Draft,
                'base_version_id' => $theirs->id,
                'created_by_person_id' => $createdBy->id,
            ]);

            $this->buildResolvedContent($resolved, $report, $choices, $manualJson);

            return $resolved;
        });
    }

    /**
     * @param  array<int, string>  $choices
     * @param  array<int, string>  $manualJson
     */
    private function buildResolvedContent(PageVersion $target, ConflictReport $report, array $choices, array $manualJson): void
    {
        // Voor elk blok kiezen we een 'winnend' Block-model (mine of theirs) op basis van de resolutie.
        $bandsByOrigin = [];

        foreach ($report->entries as $diff) {
            $chosen = $this->pickBlockFor($diff, $choices);
            if ($chosen === null) {
                continue;
            }

            $originBandId = $chosen->band->origin_band_id ?? $chosen->band->id;

            if (! isset($bandsByOrigin[$originBandId])) {
                $bandsByOrigin[$originBandId] = $target->bands()->create([
                    'origin_band_id' => $originBandId,
                    'zone' => $chosen->band->zone,
                    'layout' => $chosen->band->layout,
                    'sort_order' => $chosen->band->sort_order,
                ]);
            }

            $bandsByOrigin[$originBandId]->blocks()->create([
                'origin_block_id' => $chosen->origin_block_id ?? $chosen->id,
                'column_index' => $chosen->column_index,
                'sort_order' => $chosen->sort_order,
                'type' => $chosen->type,
                'content' => $this->resolveContent($diff, $chosen, $choices, $manualJson),
                'visibility' => $chosen->visibility,
            ]);
        }
    }

    /**
     * @param  array<int, string>  $choices
     */
    private function pickBlockFor(BlockDiff $diff, array $choices): ?Block
    {
        if ($diff->isNoop()) {
            return null;
        }

        if ($diff->isConflict()) {
            $choice = $choices[$diff->originBlockId] ?? 'mine';

            return match ($choice) {
                'theirs' => $diff->theirs,
                'manual' => $diff->mine ?? $diff->theirs,
                default => $diff->mine ?? $diff->theirs,
            };
        }

        return match ($diff->type) {
            'added_by_me', 'edited_by_me' => $diff->mine,
            'added_by_theirs', 'edited_by_theirs' => $diff->theirs,
            default => $diff->mine ?? $diff->theirs,
        };
    }

    /**
     * @param  array<int, string>  $choices
     * @param  array<int, string>  $manualJson
     * @return array<string, mixed>
     */
    private function resolveContent(BlockDiff $diff, Block $chosen, array $choices, array $manualJson): array
    {
        if (! $diff->isConflict()) {
            return $chosen->content;
        }

        $choice = $choices[$diff->originBlockId] ?? 'mine';

        if ($choice === 'manual') {
            $decoded = json_decode($manualJson[$diff->originBlockId] ?? '{}', true);

            return is_array($decoded) ? $decoded : $chosen->content;
        }

        return $chosen->content;
    }
}
