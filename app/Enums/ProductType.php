<?php

namespace App\Enums;

/**
 * Soort artikel (§23.3). Contributie, activiteitsbijdrage en advertentie zijn
 * de bekende bronnen; `overig` vangt incidentele posten op.
 */
enum ProductType: string
{
    case Contributie = 'contributie';
    case ActiviteitsBijdrage = 'activiteitsbijdrage';
    case Advertentie = 'advertentie';
    case Overig = 'overig';

    public function label(): string
    {
        return match ($this) {
            self::Contributie => 'Contributie',
            self::ActiviteitsBijdrage => 'Activiteitsbijdrage',
            self::Advertentie => 'Advertentie',
            self::Overig => 'Overig',
        };
    }
}
