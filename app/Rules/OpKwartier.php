<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Reserveringstijden liggen op een kwartier (:00, :15, :30, :45), zodat ze
 * precies op de kwartiercellen van het reserveringsrooster vallen (§18.1).
 */
class OpKwartier implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $time = Carbon::parse((string) $value);
        } catch (Throwable) {
            return; // Ongeldige datum meldt de 'date'-regel al.
        }

        if ($time->minute % 15 !== 0 || $time->second !== 0) {
            $fail('Kies een tijd op een kwartier (:00, :15, :30 of :45).');
        }
    }
}
