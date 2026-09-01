<?php

namespace App\Services\Communication;

use App\Models\InAppNotification;
use App\Models\Person;

/**
 * Schrijft in-app-meldingen weg (§24.2) — bewust alleen voor de gevallen die
 * het ontwerp expliciet noemt (goedkeuringen, vrijgekomen wachtlijstplekken,
 * herinneringen), niet bij elke mailtrigger.
 */
class InAppNotifier
{
    public function notify(Person $person, string $type, string $subject, ?string $link = null): InAppNotification
    {
        return InAppNotification::query()->create([
            'person_id' => $person->id,
            'type' => $type,
            'subject' => $subject,
            'link' => $link,
        ]);
    }
}
