<?php

namespace App\Enums;

/**
 * Wie mag deze activiteit zien en zich er (potentieel) op inschrijven.
 * Zelfde vocabulaire als CMS-pagina's (§5).
 */
enum ActivityVisibility: string
{
    case Public = 'public';
    case Members = 'members';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Openbaar (iedereen ziet)',
            self::Members => 'Alleen leden',
            self::Staff => 'Alleen beheer',
        };
    }
}
