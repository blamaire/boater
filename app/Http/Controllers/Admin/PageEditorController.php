<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChangeType;
use App\Enums\PageVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Services\Cms\ConflictDetector;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\ProposalEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PageEditorController extends Controller
{
    public function __construct(
        private readonly ProposalEngine $proposalEngine,
        private readonly ConflictDetector $conflictDetector,
    ) {}

    public function show(Request $request, Page $page): View
    {
        $person = $request->user()?->person;
        abort_unless($person !== null, 403, 'Account is niet gekoppeld aan een persoon.');

        $version = $this->resolveEditableVersion($page, $person);

        return view('admin.pages.editor', [
            'page' => $page,
            'version' => $version,
            'hasUnpublishedChanges' => $version->hasUnpublishedChanges(),
        ]);
    }

    public function startDraft(Request $request, Page $page): RedirectResponse
    {
        $person = $request->user()?->person;
        abort_unless($person !== null, 403, 'Account is niet gekoppeld aan een persoon.');

        $this->createDraftFor($page, $person);

        return redirect()->route('admin.pages.editor', $page)
            ->with('status', 'Nieuwe concept-versie aangemaakt.');
    }

    public function submit(Request $request, Page $page, PageVersion $version): RedirectResponse
    {
        abort_unless($version->page_id === $page->id, 404);

        $person = $request->user()?->person;
        abort_unless($person !== null, 403, 'Account is niet gekoppeld aan een persoon.');

        if (! $version->status->isEditable()) {
            return back()->with('error', 'Alleen concept-versies kunnen worden ingediend.');
        }

        $published = $page->publishedVersion;

        if ($published !== null && $version->base_version_id !== null && $version->base_version_id !== $published->id) {
            $report = $this->conflictDetector->detect(
                mine: $version,
                theirs: $published,
                base: PageVersion::query()->find($version->base_version_id),
            );

            if ($report->hasConflicts()) {
                return redirect()->route('admin.pages.conflict.show', [
                    'page' => $page,
                    'version' => $version,
                    'other' => $published,
                ])->with('warning', 'De pagina is intussen bijgewerkt; los de conflicten op voor je opnieuw indient.');
            }
        }

        $version->update(['status' => PageVersionStatus::InReview]);

        $this->proposalEngine->submit(
            subjectType: PageVersionProposalHandler::SUBJECT_TYPE,
            changeType: $page->published_version_id === null ? ChangeType::Create : ChangeType::Update,
            payload: ['page_id' => $page->id],
            proposer: $person,
            subjectId: $version->id,
        );

        return redirect()->route('admin.pages.editor', $page)
            ->with('status', 'Versie ingediend ter goedkeuring.');
    }

    /**
     * Zoek de conceptversie van deze persoon voor deze pagina — of maak er één aan.
     */
    private function resolveEditableVersion(Page $page, Person $person): PageVersion
    {
        $draft = PageVersion::query()
            ->where('page_id', $page->id)
            ->where('status', PageVersionStatus::Draft)
            ->where('created_by_person_id', $person->id)
            ->orderByDesc('version_no')
            ->first();

        if ($draft !== null) {
            return $draft;
        }

        return $this->createDraftFor($page, $person);
    }

    private function createDraftFor(Page $page, Person $person): PageVersion
    {
        $latest = PageVersion::query()
            ->where('page_id', $page->id)
            ->orderByDesc('version_no')
            ->first();
        $nextVersionNo = ($latest !== null ? $latest->version_no : 0) + 1;

        $base = $page->publishedVersion;
        if ($base === null) {
            $base = $latest;
        }

        $version = PageVersion::create([
            'page_id' => $page->id,
            'version_no' => $nextVersionNo,
            'status' => PageVersionStatus::Draft,
            'base_version_id' => $base?->id,
            'created_by_person_id' => $person->id,
        ]);

        if ($base !== null) {
            $this->copyContent($base, $version);
        }

        return $version;
    }

    private function copyContent(PageVersion $source, PageVersion $target): void
    {
        foreach ($source->bands()->with('blocks')->get() as $band) {
            $newBand = $target->bands()->create([
                'origin_band_id' => $band->origin_band_id ?? $band->id,
                'zone' => $band->zone,
                'layout' => $band->layout,
                'sort_order' => $band->sort_order,
            ]);

            foreach ($band->blocks as $block) {
                $newBand->blocks()->create([
                    'origin_block_id' => $block->origin_block_id ?? $block->id,
                    'column_index' => $block->column_index,
                    'sort_order' => $block->sort_order,
                    'type' => $block->type,
                    'content' => $block->content,
                    'visibility' => $block->visibility,
                ]);
            }
        }
    }
}
