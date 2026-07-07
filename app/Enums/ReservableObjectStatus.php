<?php

namespace App\Enums;

enum ReservableObjectStatus: string
{
    case Available = 'beschikbaar';
    case OutOfService = 'buiten_gebruik';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Beschikbaar',
            self::OutOfService => 'Buiten gebruik',
        };
    }
}
