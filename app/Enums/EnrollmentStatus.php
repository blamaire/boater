<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Enrolled = 'aangemeld';
    case Waitlist = 'wachtlijst';
    case Cancelled = 'afgemeld';

    public function label(): string
    {
        return match ($this) {
            self::Enrolled => 'Aangemeld',
            self::Waitlist => 'Op wachtlijst',
            self::Cancelled => 'Afgemeld',
        };
    }
}
