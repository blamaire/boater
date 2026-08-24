<?php

namespace App\Services\Activities;

use App\Models\Activity;
use App\Models\Person;
use App\Notifications\ActivityChanged;
use App\Notifications\ActivityEnrollmentChanged;
use Illuminate\Notifications\Notification as NotificationClass;
use Illuminate\Support\Facades\Notification;

/**
 * Stuurt mailnotificaties naar de gedelegeerde beheerders van een activiteit
 * — losse personen (`Activity::managers()`) en leden van gekoppelde
 * goedkeuringsgroepen (`Activity::managerGroups()`) — die `notify=true`
 * hebben staan.
 */
class ActivityManagerNotifier
{
    public function notifyChanged(Activity $activity): void
    {
        $this->send($activity, new ActivityChanged($activity));
    }

    public function notifyEnrollment(Activity $activity, Person $person, bool $enrolled): void
    {
        $this->send($activity, new ActivityEnrollmentChanged($activity, $person, $enrolled));
    }

    private function send(Activity $activity, NotificationClass $notification): void
    {
        $recipients = $activity->managers()->wherePivot('notify', true)->get();

        foreach ($activity->managerGroups()->wherePivot('notify', true)->get() as $group) {
            $recipients = $recipients->merge($group->members);
        }

        foreach ($recipients->unique('id') as $manager) {
            if ($manager->email === null || $manager->email === '') {
                continue;
            }
            Notification::route('mail', $manager->email)->notify($notification);
        }
    }
}
