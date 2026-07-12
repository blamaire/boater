<?php

namespace App\Enums;

/**
 * Herhaalschema van een terugkerend product (§23.3). Alleen van toepassing
 * als `Product.is_recurring` waar is; anders blijft de waarde leeg.
 */
enum ProductRecurrence: string
{
    case Jaarlijks = 'jaarlijks';
    case PerKwartaal = 'per_kwartaal';
    case Maandelijks = 'maandelijks';

    public function label(): string
    {
        return match ($this) {
            self::Jaarlijks => 'Jaarlijks',
            self::PerKwartaal => 'Per kwartaal',
            self::Maandelijks => 'Maandelijks',
        };
    }
}
