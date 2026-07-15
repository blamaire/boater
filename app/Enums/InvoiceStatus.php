<?php

namespace App\Enums;

/**
 * Status van een factuur (§23.3 INVOICE). Betaal-gerelateerde statussen
 * (betaald/deels_betaald) worden pas gezet in de betaal-fase.
 */
enum InvoiceStatus: string
{
    case Concept = 'concept';
    case Verzonden = 'verzonden';
    case Betaald = 'betaald';
    case DeelsBetaald = 'deels_betaald';
    case Vervallen = 'vervallen';
    case Gecrediteerd = 'gecrediteerd';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Verzonden => 'Verzonden',
            self::Betaald => 'Betaald',
            self::DeelsBetaald => 'Deels betaald',
            self::Vervallen => 'Vervallen',
            self::Gecrediteerd => 'Gecrediteerd',
        };
    }
}
