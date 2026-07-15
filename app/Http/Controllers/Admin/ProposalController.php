<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageVersion;
use App\Models\Proposal;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use Illuminate\View\View;

/**
 * Alleen-lezen inzage op een voorstel. Ontsloten vanuit het auditlogboek (§31);
 * mutaties lopen uitsluitend via de ProposalEngine, niet hier.
 */
class ProposalController extends Controller
{
    public function show(Proposal $proposal): View
    {
        $proposal->load(['proposedBy', 'policy', 'steps.decidedBy']);

        return view('admin.proposals.show', [
            'proposal' => $proposal,
            'cms' => $this->cmsChange($proposal),
        ]);
    }

    /**
     * Voor een CMS-paginaversie-voorstel: de context om de inhoudswijziging
     * t.o.v. de vorige gepubliceerde pagina te bekijken, via de bestaande
     * vergelijk-pagina. Null voor andere subject-types of als er geen vorige
     * versie is om mee te vergelijken (nieuwe pagina).
     *
     * @return array{label: string, diffUrl: string|null}|null
     */
    private function cmsChange(Proposal $proposal): ?array
    {
        if ($proposal->subject_type !== PageVersionProposalHandler::SUBJECT_TYPE || $proposal->subject_id === null) {
            return null;
        }

        $version = PageVersion::query()->with(['page', 'baseVersion'])->find($proposal->subject_id);
        if ($version === null) {
            return null;
        }

        $page = $version->page;
        // De versie waarop de bewerking is gebaseerd is doorgaans de toen
        // gepubliceerde pagina; valt terug op de nu gepubliceerde versie.
        $previous = $version->baseVersion ?? $page->publishedVersion;

        $diffUrl = null;
        if ($previous !== null && $previous->id !== $version->id) {
            $diffUrl = route('admin.pages.history.diff', [
                'page' => $page,
                'version' => $previous,
                'other' => $version,
            ]);
        }

        return [
            'label' => "{$page->title} — v{$version->version_no}",
            'diffUrl' => $diffUrl,
        ];
    }
}
