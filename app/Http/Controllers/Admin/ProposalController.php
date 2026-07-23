<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Services\Proposals\ProposalPresenter;
use Illuminate\View\View;

/**
 * Alleen-lezen inzage op een voorstel. Ontsloten vanuit het auditlogboek (§31);
 * mutaties lopen uitsluitend via de ProposalEngine, niet hier.
 */
class ProposalController extends Controller
{
    public function __construct(private readonly ProposalPresenter $presenter) {}

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
        $ctx = $this->presenter->pageVersionDiffContext($proposal);
        if ($ctx === null) {
            return null;
        }

        $diffUrl = $ctx['previous'] !== null
            ? route('admin.pages.history.diff', [
                'page' => $ctx['page'],
                'version' => $ctx['previous'],
                'other' => $ctx['version'],
            ])
            : null;

        return [
            'label' => $ctx['label'],
            'diffUrl' => $diffUrl,
        ];
    }
}
