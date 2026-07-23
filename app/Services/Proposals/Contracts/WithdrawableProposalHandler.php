<?php

namespace App\Services\Proposals\Contracts;

use App\Models\Proposal;

/**
 * Optionele uitbreiding op ProposalHandler voor subject_types die bij
 * intrekken een eigen "in behandeling"-status moeten terugzetten (bv. een
 * PageVersion die van in_review weer naar concept moet). ProposalEngine
 * roept dit alleen aan als de geregistreerde handler deze interface
 * implementeert — geen breaking change voor de overige handlers.
 */
interface WithdrawableProposalHandler
{
    public function onWithdrawn(Proposal $proposal): void;
}
