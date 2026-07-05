<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Welkomst-mail voor een door een beheerder aangemaakte gebruiker. Bevat
 * dezelfde token-flow als een wachtwoord-reset, maar met een uitnodigende
 * tekst zodat de ontvanger begrijpt dat het een nieuw account is en geen
 * "je hebt om een reset gevraagd"-mail.
 */
class AccountInvitation extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Welkom bij RZVG — kies je wachtwoord')
            ->greeting('Hallo!')
            ->line('Er is voor jou een account aangemaakt op de website van Roei- en Zeilvereniging Gouda.')
            ->line('Klik op onderstaande knop om je eigen wachtwoord in te stellen. Daarna kun je direct inloggen.')
            ->action('Wachtwoord instellen', $url)
            ->line("Deze uitnodiging is {$minutes} minuten geldig. Vraag de beheerder om een nieuwe link als je te laat bent.")
            ->salutation('Met vriendelijke groet, Roei- en Zeilvereniging Gouda');
    }
}
