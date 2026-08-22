<?php

namespace App\Services\Proposals\Handlers;

use App\Enums\PageVersionStatus;
use App\Models\PageVersion;
use App\Models\Proposal;
use App\Services\Proposals\Contracts\ProposalHandler;
use App\Services\Proposals\Contracts\WithdrawableProposalHandler;
use App\Services\Proposals\Exceptions\ProposalConflictException;

/**
 * Past een goedgekeurde PageVersion toe als de gepubliceerde versie van een pagina.
 *
 * subject_type: 'cms.page_version'
 * subject_id:   ID van de PageVersion die gepubliceerd moet worden
 * payload:      ['page_id' => int] — alleen voor validatie
 */
class PageVersionProposalHandler implements ProposalHandler, WithdrawableProposalHandler
{
    public const string SUBJECT_TYPE = 'cms.page_version';

    public function revalidate(Proposal $proposal): void
    {
        $version = $this->resolveVersion($proposal);

        if ($version->status === PageVersionStatus::Published) {
            throw new ProposalConflictException('Deze versie is al gepubliceerd.');
        }

        if ($version->status === PageVersionStatus::Archived) {
            throw new ProposalConflictException('Deze versie is gearchiveerd en kan niet meer worden gepubliceerd.');
        }

        $page = $version->page;
        $currentPublishedId = $page->published_version_id;

        if ($currentPublishedId !== null && $currentPublishedId > $version->id) {
            throw new ProposalConflictException(
                'Een nieuwere versie van deze pagina is intussen gepubliceerd.',
            );
        }
    }

    public function apply(Proposal $proposal): void
    {
        $version = $this->resolveVersion($proposal);
        $page = $version->page;

        if ($page->published_version_id !== null && $page->published_version_id !== $version->id) {
            PageVersion::query()
                ->whereKey($page->published_version_id)
                ->update(['status' => PageVersionStatus::Archived->value]);
        }

        $version->update(['status' => PageVersionStatus::Published]);

        $page->update(['published_version_id' => $version->id]);
    }

    /**
     * Bij intrekken blijft de PageVersion anders permanent op in_review staan
     * (niets anders zet 'm terug) — waardoor de bewerker de conceptversie
     * nooit meer terugvindt (PageEditorController::resolveEditableVersion()
     * zoekt alleen naar status Draft) en in plaats daarvan een verse, lege
     * conceptversie aanmaakt. Alleen terugzetten als hij nog in_review is —
     * een inmiddels gepubliceerde/gearchiveerde versie blijft onaangeroerd.
     */
    public function onWithdrawn(Proposal $proposal): void
    {
        if ($proposal->subject_id === null) {
            return;
        }

        PageVersion::query()
            ->whereKey($proposal->subject_id)
            ->where('status', PageVersionStatus::InReview->value)
            ->update(['status' => PageVersionStatus::Draft->value]);
    }

    private function resolveVersion(Proposal $proposal): PageVersion
    {
        if ($proposal->subject_id === null) {
            throw new ProposalConflictException('Voorstel heeft geen subject_id.');
        }

        return PageVersion::query()->with('page')->findOrFail($proposal->subject_id);
    }
}
