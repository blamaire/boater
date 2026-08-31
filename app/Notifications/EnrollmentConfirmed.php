<?php

namespace App\Notifications;

use App\Enums\EnrollmentStatus;
use App\Models\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bevestigt aan de ingeschreven persoon zelf dat de inschrijving is verwerkt
 * (aangemeld of op de wachtlijst), Fase B. Wordt in queue verstuurd.
 */
class EnrollmentConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Activity $activity,
        public readonly EnrollmentStatus $status,
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
        $onWaitlist = $this->status === EnrollmentStatus::Waitlist;

        $mail = (new MailMessage)
            ->subject(($onWaitlist ? 'Op de wachtlijst: ' : 'Inschrijfbevestiging: ').$this->activity->title)
            ->greeting('Hallo,')
            ->line($onWaitlist
                ? 'Je staat op de wachtlijst voor "'.$this->activity->title.'". Zodra er een plek vrijkomt, hoor je van ons.'
                : 'Je inschrijving voor "'.$this->activity->title.'" is bevestigd.')
            ->line('Datum: '.$this->activity->starts_at->translatedFormat('l j F Y H:i'));

        if ($this->activity->location !== null) {
            $mail->line('Locatie: '.$this->activity->location);
        }

        return $mail
            ->action('Bekijk de activiteit', route('activiteit.show', $this->activity))
            ->salutation('— RZVG');
    }
}
