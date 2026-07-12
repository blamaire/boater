<?php

namespace App\Enums;

/**
 * Status van een te factureren post (§23.3 CHARGE).
 */
enum ChargeStatus: string
{
    case Open = 'open';
    case Gefactureerd = 'gefactureerd';
    case Betaald = 'betaald';
    case Gecrediteerd = 'gecrediteerd';
    case Vervallen = 'vervallen';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Gefactureerd => 'Gefactureerd',
            self::Betaald => 'Betaald',
            self::Gecrediteerd => 'Gecrediteerd',
            self::Vervallen => 'Vervallen',
        };
    }
}
