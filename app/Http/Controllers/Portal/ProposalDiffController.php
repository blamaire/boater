<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PageVersion;
use App\Models\Proposal;
use App\Services\Cms\ConflictDetector;
use App\Services\Proposals\Handlers\PageVersionProposalHandler;
use App\Services\Proposals\ReviewerResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Visuele inhoudsdiff van een eigen of te beoordelen paginavoorstel, voor
 * leden zonder `pages.view` (die permissie hoort bij paginabeheer, niet bij
 * het indienen/beoordelen van een voorstel — zie EffectivePermissions).
 * Autorisatie loopt daarom via eigenaarschap/beslissersrol op het voorstel
 * zelf, niet via een brede beheerpermissie.
 */
class ProposalDiffController extends Controller
{
    public function show(Request $request, Proposal $proposal, ConflictDetector $detector, ReviewerResolver $resolver): View
    {
        abort_unless($proposal->subject_type === PageVersionProposalHandler::SUBJECT_TYPE, 404);

        $person = $request->user()?->person;
        abort_if($person === null, 403, 'Je account is niet gekoppeld aan een persoon.');

        $isSubmitter = $proposal->proposed_by_person_id === $person->id;
        $isEligibleDecider = $proposal->steps->contains(fn ($step) => $resolver->canDecide($step, $person));
        abort_unless($isSubmitter || $isEligibleDecider, 403);

        $version = PageVersion::query()->with(['page', 'baseVersion'])->findOrFail($proposal->subject_id);
        $page = $version->page;
        $previous = $version->baseVersion ?? $page->publishedVersion;
        abort_if($previous === null || $previous->id === $version->id, 404);

        // Two-way diff: geen gemeenschappelijke voorouder, dus base=null.
        // $previous = "mine" (huidige inhoud), $version = "theirs" (voorstel),
        // zodat a/b in de view consequent voor/na betekenen.
        $report = $detector->detect($previous, $version, null);

        return view('portal.proposal-diff', [
            'proposal' => $proposal,
            'page' => $page,
            'a' => $previous,
            'b' => $version,
            'report' => $report,
        ]);
    }
}
