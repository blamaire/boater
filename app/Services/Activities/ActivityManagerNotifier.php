<?php

namespace App\Services\Activities;

use App\Models\Activity;
use App\Models\Person;
use App\Services\Communication\MessageDispatcher;

/**
 * Stuurt mailnotificaties naar de gedelegeerde beheerders van een activiteit
 * — losse personen (`Activity::managers()`) en leden van gekoppelde
 * goedkeuringsgroepen (`Activity::managerGroups()`) — die `notify=true`
 * hebben staan.
 */
class ActivityManagerNotifier
{
    public function __construct(private readonly MessageDispatcher $dispatcher) {}

    public function notifyChanged(Activity $activity): void
    {
        $this->send($activity, 'activity_changed', [
            '{{titel}}' => $activity->title,
            '{{datum}}' => $activity->starts_at->translatedFormat('l j F Y H:i'),
            '{{activiteiten_url}}' => url('/beheer/activiteiten'),
        ]);
    }

    public function notifyEnrollment(Activity $activity, Person $person, bool $enrolled): void
    {
        $personLabel = trim($person->first_name.' '.$person->last_name);

        $this->send($activity, 'activity_enrollment_changed', [
            '{{onderwerp_actie}}' => $enrolled ? 'Nieuwe inschrijving' : 'Nieuwe afmelding',
            '{{persoon}}' => $personLabel,
            '{{actie}}' => $enrolled ? 'ingeschreven op' : 'afgemeld voor',
            '{{titel}}' => $activity->title,
            '{{activiteiten_url}}' => url('/beheer/activiteiten'),
        ]);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function send(Activity $activity, string $templateKey, array $variables): void
    {
        $recipients = $activity->managers()->wherePivot('notify', true)->get();

        foreach ($activity->managerGroups()->wherePivot('notify', true)->get() as $group) {
            $recipients = $recipients->merge($group->members);
        }

        foreach ($recipients->unique('id') as $manager) {
            if ($manager->email === null || $manager->email === '') {
                continue;
            }
            $this->dispatcher->send($templateKey, $manager->email, $variables, recipient: $manager, related: $activity);
        }
    }
}
