<?php

namespace App\Enums;

/**
 * Richting van een BTW-code: verkoop (af te dragen aan de fiscus) of
 * inkoop (voor te vorderen bij de fiscus).
 */
enum BtwCodeDirection: string
{
    case AfTeDragen = 'af_te_dragen';
    case VoorTeVorderen = 'voor_te_vorderen';

    public function label(): string
    {
        return match ($this) {
            self::AfTeDragen => 'Af te dragen (verkoop)',
            self::VoorTeVorderen => 'Voor te vorderen (inkoop)',
        };
    }
}
