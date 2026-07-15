<?php

namespace App\Services\Reservations;

use App\Models\ReservationRule;

/**
 * Waardobject dat één overtreden regel beschrijft. `message` is
 * gebruikersgerichte tekst voor in het formulier ("Je bent al ingelogd
 * op je tweede boot vandaag" — soort van); `rule` is de bron.
 */
final class RuleViolation
{
    public function __construct(
        public readonly ReservationRule $rule,
        public readonly string $message,
    ) {}
}
