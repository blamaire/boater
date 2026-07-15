<?php

namespace App\Enums;

/**
 * Zichtbaarheid voor pagina's, blokken en media-assets.
 *
 * - Public (`publiek`): openbaar te zien, ook zonder inlog.
 * - Restricted (`beperkt`): alleen te zien voor ingelogde gebruikers met
 *   een actief lidmaatschap of een rol (zoals redacteur/beheerder) die
 *   het bijbehorende inzage-recht heeft. Oud-leden zonder actief
 *   lidmaatschap en niet-leden krijgen géén toegang.
 */
enum PageVisibility: string
{
    case Public = 'publiek';
    case Restricted = 'beperkt';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Publiek',
            self::Restricted => 'Beperkt',
        };
    }
}
