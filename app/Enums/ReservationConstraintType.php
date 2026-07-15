<?php

namespace App\Enums;

/**
 * §18.3 constraint_type. Bepaalt hoe `limit_value` op een
 * `reservation_rule` wordt geïnterpreteerd.
 */
enum ReservationConstraintType: string
{
    case Simultaneous = 'gelijktijdig';
    case PerDay = 'per_dag';
    case Duration = 'duur';

    public function label(): string
    {
        return match ($this) {
            self::Simultaneous => 'Max gelijktijdig',
            self::PerDay => 'Max per dag',
            self::Duration => 'Max duur (minuten)',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Simultaneous => 'reserveringen',
            self::PerDay => 'reserveringen',
            self::Duration => 'minuten',
        };
    }
}
