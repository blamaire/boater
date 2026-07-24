<?php

namespace App\Livewire\Admin;

use App\Models\PageVersion;
use App\Services\Cms\ConflictDetector;
use App\Services\Cms\PageVersionMerger;
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

    public function resolve(PageVersionMerger $merger): void
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

        $resolved = $merger->merge($mine, $theirs, $report, $person, $this->choices, $this->manualJson);

        $this->redirectRoute('admin.pages.versions.submit.confirm', ['page' => $mine->page_id, 'version' => $resolved->id], navigate: false);
    }
}
