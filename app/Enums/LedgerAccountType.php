<?php

namespace App\Enums;

/**
 * Rubriek van een grootboekrekening (§23.3). Bepaalt of een rekening op de
 * balans (activa/passiva) of in de exploitatie (opbrengsten/kosten) staat.
 */
enum LedgerAccountType: string
{
    case Activa = 'activa';
    case Passiva = 'passiva';
    case Opbrengsten = 'opbrengsten';
    case Kosten = 'kosten';

    public function label(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Passiva => 'Passiva',
            self::Opbrengsten => 'Opbrengsten',
            self::Kosten => 'Kosten',
        };
    }
}
