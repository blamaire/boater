<?php

namespace App\Notifications;

use App\Models\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Meldt een gedelegeerd beheerder (`Activity::managers()`, notify=true) dat
 * de activiteit is gewijzigd. Wordt in queue verstuurd.
 */
class ActivityChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Activity $activity) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Activiteit gewijzigd: '.$this->activity->title)
            ->greeting('Hallo,')
            ->line('De activiteit "'.$this->activity->title.'" is zojuist gewijzigd.')
            ->line('Datum: '.$this->activity->starts_at->translatedFormat('l j F Y H:i'))
            ->action('Activiteit bekijken', url('/beheer/activiteiten'))
            ->salutation('— RZVG');
    }
}
