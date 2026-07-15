<?php

namespace App\Notifications;

use App\Models\DamageReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Meldt een schade-verantwoordelijke (§22.4 CATEGORY_RESPONSIBLE) dat er
 * een nieuwe schademelding is binnengekomen op een object in zijn/haar
 * categorie. Wordt in queue verstuurd zodat een langzame mail-server het
 * indienen niet ophoudt.
 */
class DamageReportSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly DamageReport $report) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $report = $this->report->loadMissing(['object.category', 'reportedBy']);
        $objectLabel = $report->object->name;
        $reporterLabel = trim(($report->reportedBy->first_name ?? '').' '.($report->reportedBy->last_name ?? ''));
        $adminUrl = url('/beheer/schademeldingen/'.$report->id);

        $mail = (new MailMessage)
            ->subject('Nieuwe schademelding op '.$objectLabel)
            ->greeting('Hallo,')
            ->line('Er is een nieuwe schademelding binnengekomen op een object in jouw categorie.')
            ->line('Object: '.$objectLabel)
            ->line('Melder: '.($reporterLabel !== '' ? $reporterLabel : 'onbekend'))
            ->line('Ernst: '.$report->severity->label());

        if ($report->reporter_marked_unusable) {
            $mail->line('De melder heeft "niet bruikbaar" aangevinkt. Het object staat nu op "buiten gebruik" en is onreserveerbaar totdat een behandelaar dat terugdraait.');
        }

        return $mail
            ->action('Melding openen', $adminUrl)
            ->salutation('— RZVG');
    }
}
