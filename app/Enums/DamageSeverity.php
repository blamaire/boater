<?php

namespace App\Enums;

enum DamageSeverity: string
{
    case Low = 'laag';
    case Medium = 'middel';
    case High = 'hoog';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Laag',
            self::Medium => 'Middel',
            self::High => 'Hoog',
        };
    }
}
