<?php

namespace App\Enums;

/**
 * Status van een gestagede WordPress-import-item (§25).
 */
enum WordpressImportStatus: string
{
    case New = 'nieuw';
    case Imported = 'overgenomen';
    case Archived = 'gearchiveerd';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nieuw',
            self::Imported => 'Overgenomen',
            self::Archived => 'Gearchiveerd',
        };
    }
}
