<?php

namespace App\Services\Proposals\Contracts;

use App\Models\Proposal;

interface ProposalHandler
{
    /**
     * Hervalideer het voorstel tegen de actuele staat van de onderliggende data.
     * Gooi een ProposalConflictException als de data intussen is veranderd op een
     * manier die het toepassen onveilig maakt (§20.4 — apply-time hervalidatie).
     */
    public function revalidate(Proposal $proposal): void;

    /**
     * Effectueer het voorstel: pas de wijziging toe op de onderliggende module.
     * Wordt aangeroepen ná hervalidatie en binnen de transactie van de motor.
     */
    public function apply(Proposal $proposal): void;
}
