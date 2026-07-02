<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bevestiging dat een "Lid worden"-aanvraag is ontvangen (§19).
 *
 * Wordt direct na een succesvolle indiening van de publieke aanmeldflow
 * verstuurd naar het opgegeven e-mailadres. De definitieve bevestiging
 * (of afwijzing) volgt na beoordeling door de ledenadministratie via
 * de goedkeuringsmotor.
 */
class MembershipApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $firstName,
        public readonly string $membershipTypeName,
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
        return (new MailMessage)
            ->subject('We hebben je aanmelding ontvangen')
            ->greeting('Hallo '.$this->firstName.'!')
            ->line('Bedankt voor je aanmelding bij de Roei- en Zeilvereniging Gouda.')
            ->line('Je hebt gekozen voor de lidmaatschapsvorm: '.$this->membershipTypeName.'.')
            ->line('Onze ledenadministratie beoordeelt je aanvraag. Zodra er een besluit is genomen, ontvang je een tweede e-mail met de definitieve bevestiging en verdere instructies (waaronder het instellen van een wachtwoord voor je account).')
            ->line('Heb je in de tussentijd een vraag? Beantwoord dan gewoon deze e-mail.')
            ->salutation('Met vriendelijke groet, Roei- en Zeilvereniging Gouda');
    }
}
