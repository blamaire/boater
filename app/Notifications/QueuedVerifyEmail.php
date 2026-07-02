<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Queueable variant van Laravel's default e-mailverificatie-notificatie.
 *
 * Door {@see ShouldQueue} te implementeren wordt de mail via de
 * `queue`-connection (zie `.env`: `QUEUE_CONNECTION`) verzonden, zodat de
 * HTTP-request die de registratie afhandelt niet blokkeert op de SMTP-call
 * naar de externe mailprovider.
 *
 * @see User::sendEmailVerificationNotification()
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Bouw het mailbericht in het Nederlands.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(__('Bevestig je e-mailadres'))
            ->greeting(__('Hallo!'))
            ->line(__('Klik op onderstaande knop om je e-mailadres te bevestigen.'))
            ->action(__('E-mailadres bevestigen'), $verificationUrl)
            ->line(__('Heb je geen account aangemaakt? Dan hoef je niets te doen.'))
            ->salutation(__('Met vriendelijke groet, Roei- en Zeilvereniging Gouda'));
    }
}
