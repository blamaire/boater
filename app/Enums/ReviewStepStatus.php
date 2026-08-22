<?php

namespace App\Enums;

enum ReviewStepStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In afwachting',
            self::Approved => 'Goedgekeurd',
            self::Rejected => 'Afgewezen',
            self::Returned => 'Teruggestuurd',
            self::Skipped => 'Overgeslagen',
        };
    }
}
