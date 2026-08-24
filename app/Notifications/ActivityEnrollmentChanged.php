<?php

namespace App\Notifications;

use App\Models\Activity;
use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Meldt een gedelegeerd beheerder (`Activity::managers()`, notify=true) dat
 * iemand zich heeft in- of uitgeschreven. Wordt in queue verstuurd.
 */
class ActivityEnrollmentChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Activity $activity,
        public readonly Person $person,
        public readonly bool $enrolled,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $personLabel = trim($this->person->first_name.' '.$this->person->last_name);
        $action = $this->enrolled ? 'ingeschreven op' : 'afgemeld voor';

        return (new MailMessage)
            ->subject('Nieuwe '.($this->enrolled ? 'inschrijving' : 'afmelding').': '.$this->activity->title)
            ->greeting('Hallo,')
            ->line($personLabel.' heeft zich zojuist '.$action.' "'.$this->activity->title.'".')
            ->action('Activiteit bekijken', url('/beheer/activiteiten'))
            ->salutation('— RZVG');
    }
}
