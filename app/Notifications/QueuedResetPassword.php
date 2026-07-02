<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Queueable variant van Laravel's default wachtwoord-reset-notificatie.
 *
 * Door {@see ShouldQueue} te implementeren wordt de mail via de
 * `queue`-connection verzonden, zodat de HTTP-request die het
 * reset-verzoek afhandelt niet blokkeert op de SMTP-call.
 *
 * @see User::sendPasswordResetNotification()
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * Bouw het mailbericht in het Nederlands.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject(__('Wachtwoord opnieuw instellen'))
            ->greeting(__('Hallo!'))
            ->line(__('Je ontvangt deze e-mail omdat we een verzoek hebben ontvangen om je wachtwoord opnieuw in te stellen.'))
            ->action(__('Wachtwoord opnieuw instellen'), $url)
            ->line(__('Deze link verloopt over :count minuten.', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
            ->line(__('Heb je geen verzoek ingediend? Dan hoef je niets te doen.'))
            ->salutation(__('Met vriendelijke groet, Roei- en Zeilvereniging Gouda'));
    }
}
