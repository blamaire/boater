<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Draft = 'concept';
    case Published = 'gepubliceerd';
    case Cancelled = 'afgelast';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Concept',
            self::Published => 'Gepubliceerd',
            self::Cancelled => 'Afgelast',
        };
    }
}
