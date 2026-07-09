<?php

namespace App\Enums;

enum DamageReportStatus: string
{
    case Reported = 'gemeld';
    case InProgress = 'in_behandeling';
    case Resolved = 'opgelost';
    case Rejected = 'afgewezen';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Gemeld',
            self::InProgress => 'In behandeling',
            self::Resolved => 'Opgelost',
            self::Rejected => 'Afgewezen',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Reported || $this === self::InProgress;
    }
}
