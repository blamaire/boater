<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Generieke drager voor een reeds-gesubstitueerd, reeds-gesaneerd
 * sjabloonbericht (§24) — gebouwd door `App\Services\Communication\MessageDispatcher`,
 * nooit rechtstreeks met ruwe/ongesaneerde HTML.
 */
class TemplatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $bodyHtml,
        public readonly ?string $unsubscribeUrl = null,
        public readonly ?string $trackingPixelUrl = null,
    ) {}

    public function build(): self
    {
        return $this->subject($this->mailSubject)
            ->view('mail.template', [
                'bodyHtml' => $this->bodyHtml,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'trackingPixelUrl' => $this->trackingPixelUrl,
            ]);
    }
}
