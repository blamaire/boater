<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Confirmed = 'bevestigd';
    case Cancelled = 'geannuleerd';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Bevestigd',
            self::Cancelled => 'Geannuleerd',
        };
    }
}
