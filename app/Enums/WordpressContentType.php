<?php

namespace App\Enums;

/**
 * Brontype van een gestagede WordPress-import-item (§25) — WordPress kent
 * geen aparte "nieuws"-module, dus zowel pagina's als berichten landen als
 * een normale CMS-`Page`.
 */
enum WordpressContentType: string
{
    case Page = 'page';
    case Post = 'post';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'Pagina',
            self::Post => 'Bericht',
        };
    }
}
