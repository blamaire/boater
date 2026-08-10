<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChangeType;
use App\Enums\PageVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Person;
use App\Services\Cms\ConflictDetector;
use App\Services\Cms\PageVersionCloner;
use App\Services\Cms\PageVersionMerger;
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
        private readonly PageVersionMerger $merger,
        private readonly PageVersionCloner $cloner,
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

    /**
     * Bevestigingspagina vóór indienen: toont de diff met de gepubliceerde
     * versie (indien aanwezig) en vraagt een verplichte omschrijving.
     */
    public function confirmSubmit(Request $request, Page $page, PageVersion $version): View|RedirectResponse
    {
        return $this->buildConfirmView($request, $page, $version, submitRouteName: 'admin.pages.versions.submit', actionLabel: 'Indienen ter publicatie');
    }

    /**
     * Bevestigingspagina vóór direct publiceren — zelfde opzet als
     * {@see confirmSubmit()}, maar postend naar de publish-route.
     */
    public function confirmPublish(Request $request, Page $page, PageVersion $version): View|RedirectResponse
    {
        return $this->buildConfirmView($request, $page, $version, submitRouteName: 'admin.pages.versions.publish', actionLabel: 'Direct publiceren');
    }

    /**
     * Return-type is een unie omdat een niet-bewerkbare versie terugleidt
     * naar de editor in plaats van de bevestigingsview te tonen — een pure
     * `View`-hint zou hier een runtime `TypeError` geven.
     */
    private function buildConfirmView(Request $request, Page $page, PageVersion $version, string $submitRouteName, string $actionLabel): View|RedirectResponse
    {
        abort_unless($version->page_id === $page->id, 404);

        if (! $version->status->isEditable()) {
            return redirect()->route('admin.pages.editor', $page)
                ->with('error', 'Alleen concept-versies kunnen worden ingediend.');
        }

        $person = $request->user()?->person;
        abort_unless($person !== null, 403, 'Account is niet gekoppeld aan een persoon.');

        $result = $this->resolveSubmittableVersion($page, $version, $person);

        if ($result instanceof RedirectResponse) {
            return $result;
        }

        $version = $result['version'];

        $published = $page->publishedVersion;
        $report = $published !== null
            ? $this->conflictDetector->detect(mine: $published, theirs: $version, base: null)
            : null;

        return view('admin.pages.versions-confirm', [
            'page' => $page,
            'version' => $version,
            'published' => $published,
            'report' => $report,
            'submitRouteName' => $submitRouteName,
            'actionLabel' => $actionLabel,
            'rebaseNotice' => $result['notice'],
        ]);
    }

    /**
     * @return array{version: PageVersion, notice: ?string}|RedirectResponse
     */
    private function resolveSubmittableVersion(Page $page, PageVersion $version, Person $person): RedirectResponse|array
    {
        $published = $page->publishedVersion;

        if ($published === null || $version->base_version_id === null || $version->base_version_id === $published->id) {
            return ['version' => $version, 'notice' => null];
        }

        $base = PageVersion::query()->find($version->base_version_id);
        $report = $this->conflictDetector->detect(mine: $version, theirs: $published, base: $base);

        if ($report->hasConflicts()) {
            return redirect()->route('admin.pages.conflict.show', [
                'page' => $page,
                'version' => $version,
                'other' => $published,
            ])->with('warning', 'De pagina is intussen bijgewerkt; los de conflicten op voor je opnieuw indient.');
        }

        $oldBaseVersionNo = $base?->version_no;
        $rebased = $this->merger->merge($version, $published, $report, $person);

        $notice = "Je conceptversie was gebaseerd op versie {$oldBaseVersionNo} en is automatisch bijgewerkt met tussentijdse wijzigingen van versie {$published->version_no} (huidige gepubliceerde versie).";

        return ['version' => $rebased, 'notice' => $notice];
    }

    /**
     * Standaardknop: altijd via de goedkeuringsmotor, ook voor wie een
     * bypass-permissie (`pages.publish`) heeft — die krijgt in plaats
     * daarvan de expliciete {@see publishDirectly()}-knop ernaast.
     */
    public function submit(Request $request, Page $page, PageVersion $version): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        return $this->submitVersion($request, $page, $version, ignoreBypass: true, successMessage: 'Versie ingediend ter goedkeuring.', note: $validated['note']);
    }

    /**
     * Expliciete knop voor wie `pages.publish` heeft (route-middleware):
     * dezelfde motor, maar met de bypass-permissie juist wél in werking —
     * publiceert direct zonder review, in plaats van dat impliciet te laten
     * gebeuren via de standaard "indienen"-knop.
     */
    public function publishDirectly(Request $request, Page $page, PageVersion $version): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        return $this->submitVersion($request, $page, $version, ignoreBypass: false, successMessage: 'Versie direct gepubliceerd zonder goedkeuring.', note: $validated['note']);
    }

    private function submitVersion(Request $request, Page $page, PageVersion $version, bool $ignoreBypass, string $successMessage, string $note): RedirectResponse
    {
        abort_unless($version->page_id === $page->id, 404);

        $person = $request->user()?->person;
        abort_unless($person !== null, 403, 'Account is niet gekoppeld aan een persoon.');

        if (! $version->status->isEditable()) {
            return back()->with('error', 'Alleen concept-versies kunnen worden ingediend.');
        }

        $result = $this->resolveSubmittableVersion($page, $version, $person);

        if ($result instanceof RedirectResponse) {
            return $result;
        }

        $version = $result['version'];

        $version->update(['status' => PageVersionStatus::InReview]);

        $this->proposalEngine->submit(
            subjectType: PageVersionProposalHandler::SUBJECT_TYPE,
            changeType: $page->published_version_id === null ? ChangeType::Create : ChangeType::Update,
            payload: ['page_id' => $page->id],
            note: $note,
            proposer: $person,
            subjectId: $version->id,
            ignoreBypass: $ignoreBypass,
        );

        return redirect()->route('portal.wijzigingsvoorstellen')
            ->with('status', $successMessage);
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
            $this->cloner->clone($base, $version);
        }

        return $version;
    }
}
