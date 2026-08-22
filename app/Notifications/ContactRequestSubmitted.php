<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Meldt de verantwoordelijke van een contact-onderwerp dat er een nieuw
 * contactverzoek is binnengekomen. Wordt in queue verstuurd zodat een
 * langzame mailserver het indienen niet ophoudt.
 */
class ContactRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContactRequest $contactRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $request = $this->contactRequest->loadMissing('topic');
        $adminUrl = url('/beheer/contactverzoeken/'.$request->id);

        $mail = (new MailMessage)
            ->subject('Nieuw contactverzoek: '.$request->topic->name)
            ->greeting('Hallo,')
            ->line('Er is een nieuw contactverzoek binnengekomen voor "'.$request->topic->name.'".')
            ->line('Naam: '.$request->name)
            ->line('Voorkeur: '.$request->contactMethodLabel());

        if ($request->phone) {
            $mail->line('Telefoon: '.$request->phone);
        }
        if ($request->email) {
            $mail->line('E-mail: '.$request->email);
        }

        return $mail
            ->line('Bericht:')
            ->line($request->message)
            ->action('Verzoek openen', $adminUrl)
            ->salutation('— RZVG');
    }
}
