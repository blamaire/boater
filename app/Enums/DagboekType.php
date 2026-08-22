<?php

namespace App\Enums;

/**
 * Soort dagboek. Verkoop/Inkoop/Memoriaal zijn singleton (precies één per
 * administratie); Bank/Kas mogen er meerdere zijn (bv. per bankrekening/kas).
 */
enum DagboekType: string
{
    case Inkoop = 'inkoop';
    case Verkoop = 'verkoop';
    case Bank = 'bank';
    case Kas = 'kas';
    case Memoriaal = 'memoriaal';

    public function label(): string
    {
        return match ($this) {
            self::Inkoop => 'Inkoop',
            self::Verkoop => 'Verkoop',
            self::Bank => 'Bank',
            self::Kas => 'Kas',
            self::Memoriaal => 'Memoriaal',
        };
    }

    public function isSingleton(): bool
    {
        return match ($this) {
            self::Verkoop, self::Inkoop, self::Memoriaal => true,
            self::Bank, self::Kas => false,
        };
    }
}
