<?php

namespace App\Livewire\Admin;

use App\Enums\PageVersionStatus;
use App\Models\Block;
use App\Models\PageVersion;
use App\Services\Cms\BlockDiff;
use App\Services\Cms\ConflictDetector;
use App\Services\Cms\ConflictReport;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class PageConflictResolver extends Component
{
    public int $mineId;

    public int $theirsId;

    /**
     * Keuzes per conflicting blok. Key = origin_block_id, value = 'mine' | 'theirs' | 'manual'.
     *
     * @var array<int, string>
     */
    public array $choices = [];

    /**
     * Handmatige JSON per blok (alleen gebruikt als choice == 'manual').
     *
     * @var array<int, string>
     */
    public array $manualJson = [];

    public ?string $saveError = null;

    public function mount(int $mineId, int $theirsId): void
    {
        $this->mineId = $mineId;
        $this->theirsId = $theirsId;
    }

    public function render(): View
    {
        $mine = PageVersion::query()->findOrFail($this->mineId);
        $theirs = PageVersion::query()->findOrFail($this->theirsId);
        $base = $mine->base_version_id !== null
            ? PageVersion::query()->find($mine->base_version_id)
            : null;

        $report = app(ConflictDetector::class)->detect($mine, $theirs, $base);

        return view('livewire.admin.page-conflict-resolver', [
            'mine' => $mine,
            'theirs' => $theirs,
            'base' => $base,
            'report' => $report,
        ]);
    }

    public function resolve(): void
    {
        $this->saveError = null;

        $mine = PageVersion::query()->findOrFail($this->mineId);
        $theirs = PageVersion::query()->findOrFail($this->theirsId);
        $base = $mine->base_version_id !== null
            ? PageVersion::query()->find($mine->base_version_id)
            : null;

        $report = app(ConflictDetector::class)->detect($mine, $theirs, $base);
        $person = auth()->user()?->person;

        if ($person === null) {
            abort(403, 'Account is niet gekoppeld aan een persoon.');
        }

        // Elk conflict moet een keuze hebben
        foreach ($report->conflicts() as $diff) {
            if (! isset($this->choices[$diff->originBlockId])) {
                $this->saveError = 'Kies voor elk conflict een resolutie.';

                return;
            }
        }

        DB::transaction(function () use ($mine, $theirs, $report, $person) {
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
                'created_by_person_id' => $person->id,
            ]);

            $this->buildResolvedContent($resolved, $report);
        });

        $this->redirectRoute('admin.pages.editor', ['page' => $mine->page_id], navigate: false);
    }

    private function buildResolvedContent(PageVersion $target, ConflictReport $report): void
    {
        // Voor elk blok kiezen we een 'winnend' Block-model (mine of theirs) op basis van de resolutie.
        $bandsByOrigin = [];

        foreach ($report->entries as $diff) {
            $chosen = $this->pickBlockFor($diff);
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
                'content' => $this->resolveContent($diff, $chosen),
                'visibility' => $chosen->visibility,
            ]);
        }
    }

    private function pickBlockFor(BlockDiff $diff): ?Block
    {
        if ($diff->isNoop()) {
            return null;
        }

        if ($diff->isConflict()) {
            $choice = $this->choices[$diff->originBlockId] ?? 'mine';

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
     * @return array<string, mixed>
     */
    private function resolveContent(BlockDiff $diff, Block $chosen): array
    {
        if (! $diff->isConflict()) {
            return $chosen->content;
        }

        $choice = $this->choices[$diff->originBlockId] ?? 'mine';

        if ($choice === 'manual') {
            $decoded = json_decode($this->manualJson[$diff->originBlockId] ?? '{}', true);

            return is_array($decoded) ? $decoded : $chosen->content;
        }

        return $chosen->content;
    }
}
