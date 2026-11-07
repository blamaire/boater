<?php

namespace App\Enums;

/**
 * Achtergrond van een band (§5 — design-systeem). Een band met een andere
 * achtergrond dan wit vult de volle paginabreedte; de inhoud blijft binnen
 * de gecentreerde leesbreedte.
 */
enum BandBackground: string
{
    case White = 'wit';
    case Sand = 'zand';
    case Red = 'rood';
    case Dark = 'donker';

    public function label(): string
    {
        return match ($this) {
            self::White => 'Wit',
            self::Sand => 'Zand',
            self::Red => 'Rood',
            self::Dark => 'Donker',
        };
    }

    /** Tailwind-klassen voor de volle-breedte-wrapper van de band. */
    public function sectionClasses(): string
    {
        return match ($this) {
            self::White => 'bg-white',
            self::Sand => 'bg-sand-100',
            self::Red => 'bg-rzvg-500 text-white band-inverted',
            self::Dark => 'bg-donker-900 text-white band-inverted',
        };
    }

    /** Krijgt de band ruime verticale padding (sectie-ritme)? Witte banden niet, zodat bestaande pagina's niet verschuiven. */
    public function isTinted(): bool
    {
        return $this !== self::White;
    }
}
