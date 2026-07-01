<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChangeType;
use App\Enums\PageVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageVersion;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\ProposalEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PageEditorController extends Controller
{
    public function __construct(private readonly ProposalEngine $proposalEngine) {}

    public function show(Page $page): View
    {
        $version = $this->resolveEditableVersion($page);

        return view('admin.pages.editor', [
            'page' => $page,
            'version' => $version,
        ]);
    }

    public function startDraft(Request $request, Page $page): RedirectResponse
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
            'created_by_person_id' => $request->user()?->person?->id,
        ]);

        if ($base) {
            $this->copyContent($base, $version);
        }

        return redirect()->route('admin.pages.editor', $page)
            ->with('status', 'Nieuwe concept-versie (v'.$version->version_no.') aangemaakt.');
    }

    public function submit(Request $request, Page $page, PageVersion $version): RedirectResponse
    {
        abort_unless($version->page_id === $page->id, 404);

        $person = $request->user()?->person;
        abort_unless($person !== null, 403, 'Account is niet gekoppeld aan een persoon.');

        if (! $version->status->isEditable()) {
            return back()->with('error', 'Alleen concept-versies kunnen worden ingediend.');
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

    private function resolveEditableVersion(Page $page): PageVersion
    {
        $draft = PageVersion::query()
            ->where('page_id', $page->id)
            ->where('status', PageVersionStatus::Draft)
            ->orderByDesc('version_no')
            ->first();

        if ($draft) {
            return $draft;
        }

        $latest = PageVersion::query()
            ->where('page_id', $page->id)
            ->orderByDesc('version_no')
            ->first();
        $nextVersionNo = ($latest !== null ? $latest->version_no : 0) + 1;

        $baseVersionId = null;
        if ($page->publishedVersion !== null) {
            $baseVersionId = $page->publishedVersion->id;
        } elseif ($latest !== null) {
            $baseVersionId = $latest->id;
        }

        $version = PageVersion::create([
            'page_id' => $page->id,
            'version_no' => $nextVersionNo,
            'status' => PageVersionStatus::Draft,
            'base_version_id' => $baseVersionId,
            'created_by_person_id' => request()->user()?->person?->id,
        ]);

        if ($page->publishedVersion) {
            $this->copyContent($page->publishedVersion, $version);
        }

        return $version;
    }

    private function copyContent(PageVersion $source, PageVersion $target): void
    {
        foreach ($source->bands()->with('blocks')->get() as $band) {
            $newBand = $target->bands()->create([
                'zone' => $band->zone,
                'layout' => $band->layout,
                'sort_order' => $band->sort_order,
            ]);

            foreach ($band->blocks as $block) {
                $newBand->blocks()->create([
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
